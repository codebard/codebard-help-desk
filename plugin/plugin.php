<?php


class cb_p3_plugin extends cb_p3_core
{
	public function plugin_construct()
	{

		add_action('init', array(&$this, 'init'));
		
		add_action('upgrader_process_complete', array(&$this, 'upgrade'));
		
		register_activation_hook( __FILE__, array(&$this,'activate' ));
		
		register_deactivation_hook(__FILE__, array(&$this,'deactivate'));
		
		if(is_admin())
		{
			add_action('init', array(&$this, 'admin_init'));
		}
		else
		{
			add_action('init', array(&$this, 'frontend_init'),99);
		}
		
		add_action('activated_plugin',array(&$this,'check_redirect_to_setup_wizard'),99);		
		
		
	}
	public function add_admin_menus_p()
	{
		add_menu_page( 'Help Desk', 'Help Desk', 'administrator', 'settings_'.$this->internal['id'], array(&$this,'do_settings_pages'), $this->internal['plugin_url'].'images/admin_menu_icon.png', 86 );
		
	}
	public function admin_init_p()
	{
		
		// Updates are important - Add update nag if update exist
		add_filter( 'pre_set_site_transient_update_plugins', array(&$this, 'check_for_update' ),99 );
		
		// Do setup wizard if it was not done
		if(!isset($this->opt['setup_done']))
		{
			add_action($this->internal['prefix'].'action_before_do_settings_pages',array(&$this,'do_setup_wizard'),99,1);
		}		
	}
	public function frontend_init_p()
	{
		
	}
	public function init_p()
	{
		// Add rewrite rules:
		$this->add_rewrite_rules();
		
		$this->register_post_types();
		
		add_action( 'init', array(&$this, 'remove_custom_post_comment') );
		

		// Below function checks the request in any way necessary, and queues any action/filter depending on request. This way, we avoid filtering content or putting any actions in pages or operations not relevant to plugin
				
		add_action( 'wp', array(&$this, 'route_request'));
		
		add_action( 'wp_footer', array(&$this, 'save_user_last_seen'));
		
		add_action( 'mod_rewrite_rules', array(&$this, 'modify_htaccess'));

		add_action( 'template_redirect', array(&$this, 'template_redirections'));
		
		add_filter( 'previous_post_link', array(&$this, 'filter_next_prev_post_link') );
		
		add_filter( 'next_post_link', array(&$this, 'filter_next_prev_post_link') );
		
		$upload_dir = wp_upload_dir();
		
		$this->internal['attachments_dir'] = $upload_dir['basedir'] . '/'.$this->internal['prefix'].'ticket_attachments/';
	
		$this->internal['attachment_url'] =  $upload_dir['baseurl'] . '/'.$this->internal['prefix'].'ticket_attachments/';
		
		// Get relative attachment dir/url :
		
		$this->internal['attachment_relative_url']=substr(wp_make_link_relative($upload_dir['baseurl']),1).'/'.$this->internal['prefix'].'ticket_attachments/';
		
		$this->internal['plugin_slug'] =  plugin_basename( __FILE__ );
		
		$this->internal['plugin_update_url'] =  wp_nonce_url(get_admin_url().'update.php?action=upgrade-plugin&plugin='.$this->internal['plugin_id'].'/index.php','upgrade-plugin_'.$this->internal['plugin_id'].'/index.php');
		
	}
	public function load_options_p()
	{
		// Initialize and modify plugin related variables
		

		return $this->internal['core_return'];
		
	}
	
	// Plugin specific functions start

	public function insert_reply_p($v1,$v2)
	{
		global $wpdb;
		
		$type=$v1;
		$args=$v2;
		
		$modified = $this->insert_single($type,$args);
	
		
		// Modified last updated for ticket's post:
		$current_time = current_time( 'mysql');
		$gmt = current_time( 'mysql',1);
		
		$wpdb->query( "UPDATE ".$wpdb->posts." SET post_modified = '".$current_time."', post_modified_gmt = '".$gmt."'  WHERE ID = ".$modified."");
		
		return $modified;	
		
	}
	public function remove_custom_post_comment_p() 
	{
		remove_post_type_support( $this->internal['prefix'].'ticket', 'comments' );
	}	
	public function delete_this_ticket_p($v1)
	{
		$request=$v1;
		
		$post = get_post($request[$this->internal['prefix'].'ticket_id']);

		
		$current_user = wp_get_current_user();
		
		$user_id = $current_user->ID;
		
		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{
			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}
		
		$department = wp_get_post_terms( $post->ID, $this->internal['prefix'].'support' );
		
		
		if(!($this->is_user_department_admin($department[0]->term_id,$current_user->ID) OR $this->is_user_department_agent($department[0]->term_id,$current_user->ID) OR $this->is_user_wp_admin($user_id)))
		{
			$this->queue_notice($this->lang['you_dont_have_permission_for_this_action'],'error','you_dont_have_permission_for_this_action');		
			
		}
		
		
		// User has permission. Delete the ticket:
		
		$result = $this->delete_ticket($post->ID);
	
		
		if($result)
		{
			$this->queue_notice($this->lang['ticket_operation_delete_success'],'success','ticket_operation_delete_success');		
			
		}
		else
		{
			$this->queue_notice($this->lang['ticket_operation_delete_error'],'error','ticket_operation_delete_error');	
			
		}
		
		
		$this->queue_content_filters();
		
	}
	public function delete_ticket_p($v1)
	{

		$post_id=$v1;
		
		$post = get_post($post_id);
	

		$current_user = wp_get_current_user();
		
		$user_id = $current_user->ID;
		
		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{
			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}
				
		$department = wp_get_post_terms( $post->ID, $this->internal['prefix'].'support' );
		
		
		if(!($this->is_user_department_admin($department[0]->term_id,$current_user->ID) OR $this->is_user_department_agent($department[0]->term_id,$current_user->ID) OR $this->is_user_wp_admin($user_id)))
		{
	
			return false; 			
		}

		
		// Delete ticket replies
		
		// Get all replies first : 
		
		$replies = $this->get_replies($post->ID);
		
		if(is_array($replies) AND count($replies)>0)
		{
			foreach($replies as $reply)
			{
		
				// Get all attachments :
				
				$attachments = $this->get_reply_attachments($reply['reply_id']);

				
				if(is_array($attachments) AND count($attachments)>0)
				{
					foreach($attachments as $attachment)
					{
						
						$this->delete_all_item_meta($attachment['attachment_id'],'attachment');
						
						// Delete attachment file:
						if(file_exists($this->internal['attachments_dir'].$attachment['attachment_content']))
						{
							unlink($this->internal['attachments_dir'].$attachment['attachment_content']);
						}
						
						// Delete the attachment
						$this->delete_item($attachment['attachment_id'],'attachment');
						
					}
				}
				
				$this->delete_all_item_meta($reply['reply_id'],'reply');
				
				$this->delete_item($reply['reply_id'],'reply');
						
			}
			
		}
		
		
		$this->delete_all_item_meta($post->ID,'post');

		return wp_delete_post($post->ID,true);
		
		
		
	}
	public function close_ticket_p($v1)
	{
		$request=$v1;
		
		$post=get_post($request[$this->internal['prefix'].'ticket_id']);
		
		$user_id = get_current_user_id();
		
		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{		
			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}
		
		$department = wp_get_post_terms( $post->ID, $this->internal['prefix'].'support' );
		
		
		if(!($this->is_user_department_admin($department[0]->term_id,$user_id) OR $this->is_user_department_agent($department[0]->term_id,$user_id) OR $post->post_author = $user_id))
		{
			$this->queue_notice($this->lang['you_dont_have_permission_for_this_action'],'error','you_dont_have_permission_for_this_action');		
			
		}
		
		
		
		// User has permission. Lets set ticket to closed:
		
		$status = $this->update_meta_by_item_id($post->ID,'ticket_status','closed','text','post');
		
		
		if($status)
		{
			$this->queue_notice($this->lang['ticket_operation_close_success'],'success','ticket_operation_close_success');		
			
		}
		else
		{
			$this->queue_notice($this->lang['ticket_operation_close_error'],'error','ticket_operation_close_error');	
			
		}
		
		
		
		$this->queue_content_filters();
		
	}
	public function reopen_ticket_p($v1)
	{
		$request=$v1;
		$post=get_post($request[$this->internal['prefix'].'ticket_id']);
		
		$user_id = get_current_user_id();
		
		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{		
			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}
		
		
		$department = wp_get_post_terms( $post->ID, $this->internal['prefix'].'support' );
		
		
		if(!($this->is_user_department_admin($department[0]->term_id,$user_id) OR $this->is_user_department_agent($department[0]->term_id,$user_id)  OR $post->post_author = $user_id))
		{
			$this->queue_notice($this->lang['you_dont_have_permission_for_this_action'],'error','you_dont_have_permission_for_this_action');		
			
		}
		
		// User has permission. Lets set ticket to closed:
		
		$status = $this->update_meta_by_item_id($post->ID,'ticket_status','open','text','post');
		
		
		if($status)
		{
			$this->queue_notice($this->lang['ticket_operation_reopen_success'],'success','ticket_operation_reopen_success');		
			
		}
		else
		{
			$this->queue_notice($this->lang['ticket_operation_reopen_error'],'error','ticket_operation_reopen_error');	
			
		}
		
		
		
		$this->queue_content_filters();
		
	}
	public function insert_quick_reply_p($v1)
	{
		$request=$v1;
	
		$post=get_post($request[$this->internal['prefix'].'ticket_id']);

		$user_id = get_current_user_id();
		$reply = $request[$this->internal['prefix'].'ticket_content'];
		
		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{		
			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}
		
		$current_user=wp_get_current_user();
	
		$args = array(
				'reply_post'    => $post->ID,
				'reply_parent'    => 0,
				'reply_slug'    => $post->post_name,
				'reply_status'    => 1,
				'reply_created'    => date("Y-m-d H:i:s", time()),
				'reply_modified'    => date("Y-m-d H:i:s", time()),
				'reply_title'    => $post->post_title,
				'reply_content'    => $reply,
				'reply_user'    => $current_user->ID,
				'reply_group'    => 1,
		);		
		
		
		$reply_id = $this->insert_reply('reply',$args);
	
		
		if($reply_id)		
		{
			// We inserted our reply. 
			
			// Check if any attachments are posted:
			
			if((!empty($_FILES)) AND $_FILES[$this->internal['prefix'].'ticket_attachment']['tmp_name']!='')
			{
				if(isset($_FILES[$this->internal['prefix'].'ticket_attachment']))
				{
				
					// Insert the attachment :
					
					$attachment_save = $this->save_attachment($reply_id,$_FILES[$this->internal['prefix'].'ticket_attachment']['tmp_name']);
					
					
					if($attachment_save) 
					{
						
						$this->queue_notice($this->lang['ticket_attachment_saved'],'success','ticket_attachment_saved');
						
					}
					else
					{
							
						$this->queue_notice($this->lang['ticket_attachment_failed'],'error','ticket_attachment_failed');
						
						
					}
									
				
				
				}				
				
			}
			
			
			$this->queue_notice($this->lang['ticket_reply_inserted'],'success','ticket_reply_inserted');
			
		
			$this->send_ticket_email_notification($post->ID,$reply_id);			
			
			
			
		}
		else
		{
			$this->queue_notice($this->lang['ticket_reply_error'],'error','ticket_reply_error');
			$this->queue_notice($reply_id,'error','ticket_reply_error_message');
			
			
			
		}
		
		
	}
	public function send_ticket_email_notification_p($v1,$v2=false)
	{
		
		$ticket_reply_id=$v2;

		$post_id = $v1;
	
		// We are going to send an email notification since ticket was updated. Relevant parties are ticket owner and the support agent currently assigned to ticket:
		
		// Get the owner of the ticket:
		
		$post=get_post($post_id);

		$current_user = wp_get_current_user();
		
		$post_author=get_userdata($post->post_author);

		if($this->opt['send_ticket_update_email_notification_to_users']=='yes')
		{
		
			// We send notification to the ticket author
			
			if($post_author->ID != $current_user->ID)
			{
				
				if($post_author->user_email!='')
				{

					$user_template = $this->load_template('email_notification_ticket_update');
			
					
					$ticket_link='<a href="'.get_permalink($post->ID).'#'.$ticket_reply_id.'">'.$this->lang['click_to_view_updated_ticket'].'</a>';
					
					$user_template = $this->process_lang($user_template);
					
					$vars=array(
						'organization_name' => $this->opt['org_name'],
						'ticket_link' => $ticket_link,
					
					);
						
					$user_template = $this->process_vars_to_template($vars, $user_template);

					$headers = array(
						'Content-Type: text/html; charset=UTF-8',
						
					);
					
					$args=array(
					
						'to' => $post_author->display_name." <".$post_author->user_email.">",
						'subject' => $this->lang['ticket_post_type_singular_name'].' #'.$post_id.' - '.$this->lang['ticket_updated_email_notification_subject'],
						'message' => $user_template,
						'headers' => $headers,
						'from_name' => $this->opt['org_name'],
						'attachments' => false
					
					);
					
					$sent = $this->send_email($args);
					
				}	
			}
		}

		if($this->opt['send_ticket_update_email_notification_to_staff']=='yes')
		{
			// Get the assigned staff :
			
			$assigned_rep = $this->get_item_meta($post->ID,'assigned_rep','int','post');
		
			// If who updated the ticket is this staff member, dont send email notification:
			
			
			
			// Iterate through each saved rep
			foreach($assigned_rep as $key => $value)
			{
				$current_rep_data = get_userdata( $assigned_rep[$key]['int_value']);
				
				// If the one who updated the ticket is current rep, dont send email notification
				
				if($current_rep_data->ID != $current_user->ID)
				{
					
					// Check if user email exists, if so queue the email/name:
					
					if($current_rep_data->user_email!='')
					{
					
						
						$staff_template = $this->load_template('email_notification_ticket_update_staff');
						
						
						$ticket_link='<a href="'.get_permalink($post->ID).'#'.$ticket_reply_id.'">'.$this->lang['click_to_view_updated_ticket'].'</a>';
						
						$staff_template = $this->process_lang($staff_template);
						
						$vars=array(
							'organization_name' => $this->opt['org_name'],
							'ticket_link' => $ticket_link,
							'staff_name' => $current_rep_data->display_name,
						
						);
							
						$staff_template = $this->process_vars_to_template($vars, $staff_template);
				
						
						$headers = array('Content-Type: text/html; charset=UTF-8');
						

						$args=array(
						
							'to' => $current_rep_data->display_name." <".$current_rep_data->user_email.">",
							'subject' => $this->lang['ticket_post_type_singular_name'].' #'.$post_id.' - '.$this->lang['ticket_updated_email_notification_subject_staff'],
							'message' => $staff_template,
							'headers' => $headers,
							'from_name' => $this->opt['org_name'],
							'attachments' => false
						
						);
						
						$sent = $this->send_email($args);						
						
						
						
					}
				
				}
				
			}
			
			
		}
		
		
	}
	public function post_ticket_p($v1)
	{
		
		$args = $v1;

		$current_user = get_current_user();
		
		$ticket_id =  $this->insert_wp_post($args);

		if($ticket_id AND !is_wp_error($ticket_id))
		{
			// Successfully inserted. Now set the ticket department:
	
			wp_set_object_terms($ticket_id, $args['tax_input'][$this->internal['prefix'].'support'], $this->internal['prefix'].'support');			
			
		}
		else
		{
			$this->queue_notice($this->lang['ticket_create_error'].$ticket_id->get_error_message(),'error','ticket_create_error');
			return false;
		}
		
		// Insert ticket post as first reply:
		
		$post = get_post($ticket_id);

		
		$args = array(
				'reply_post'    => $post->ID,
				'reply_parent'    => 0,
				'reply_slug'    => $post->post_name,
				'reply_status'    => 1,
				'reply_created'    => date("Y-m-d H:i:s", time()),
				'reply_modified'    => date("Y-m-d H:i:s", time()),
				'reply_title'    => $post->post_title,
				'reply_content'    => $post->post_content,
				'reply_user'    => $args['post_author'],
				'reply_group'    => 1,
		);		
	
		$reply_id = $this->insert_reply('reply',$args);		

		// Assign ticket to a rep in department :
		
		$assigned_rep = $this->auto_assign_ticket_to_rep($ticket_id);
		
		// Set ticket to open status:

		$this->update_meta_by_item_id($ticket_id,'ticket_status','open','text','post');
		
		$result_array = array(
			'ticket_id'=> $ticket_id, 
			'reply_id' => $reply_id
		);

		
		return $result_array;
		
	}
	public function get_ticket_p($id=false)
	{
	
		$ticket = $this->get_single($this->internal['prefix'].'ticket',$id);
		
		return $ticket;
		
	}
	public function add_rewrite_rules_p()
	{
		
		//add_rewrite_rule( '^'.$this->opt['pages']['support_desk_page_slug'].'/([^/]*)/?', 'index.php?'.$this->internal['prefix'].'action=make_help_desk_page&cb_plugin='.$this->internal['id'].'cb_p3_ticket_slug='.$matches[1], 'top' );
		
		// add_rewrite_rule( '^'.$this->opt['ticket_post_type_slug'].'/(.+?)/?$', 'index.php?'.$this->internal['prefix'].'action=display_ticket&cb_plugin='.$this->internal['id'].'&post_type='.$this->internal['prefix'].'ticket&'.$this->internal['prefix'].'ticket_slug=$matches[1]', 'top' );
		// add_rewrite_tag('%'.$this->internal['prefix'].'action%','([^&]+)');
		// add_rewrite_rule( '^ticket/?', 'index.php?'.$this->internal['prefix'].'action=display_ticket&cb_plugin='.$this->internal['id'], 'top' );
	}	
	public function the_author_filters_p($content)
	{
		global $post;
		
		if($post->post_type==$this->internal['prefix'].'ticket' AND is_single() AND in_the_loop() AND is_main_query())
		{		
			// We dont want author name displayed on top of ticket, so:
			
			return '';
		
		}
		
	}
	public function get_the_date_filters_p($content)
	{
		global $post;
		
		if($post->post_type==$this->internal['prefix'].'ticket' AND is_single() AND in_the_loop() AND is_main_query())
		{		
			// We dont want the date displayed on top of ticket, so:
			
			return '';
		
		}
		
	}
	public function title_filters_p($title)
	{
		global $post;

		
		// Process any content queued by actions and functions :
		
		if(is_page($this->opt['pages']['support_desk_page']))
		{
		
		
				
		}
		
		if(is_page($this->opt['pages']['agent_desk_page']))
		{
		
		}
		
		return $title;
	}
	public function content_filters_p($wordpress_content)
	{
		global $post;
		
		if(!(is_singular() AND in_the_loop() AND is_main_query()))
		{
			return $wordpress_content;
			
		}
		
	
		// If nothing is queued until this point, set the plugin content placeholder to null so it will be removed during template processing:
		
		if(!isset($this->internal['template_vars']['content']['plugin_content_placeholder']))
		{
			
			$this->set_template_var('plugin_content_placeholder','');	
			
		}
		
		$this->set_template_var('wordpress_content_placeholder',$wordpress_content);	

		// Queue errors and notices if there are any
		
		
		// Ticket related stuff if we are on a ticket:
	
		
		if($post->post_type==$this->internal['prefix'].'ticket' AND is_singular() AND in_the_loop() AND is_main_query())
		{
			// Reset the content so we wont get anything unexpected or added by plugins
			
			$wordpress_content='';
			
			// Slap the support menu on top of the ticket :
			
			/*
				
				template vars added to $this->internal['template_vars']['template_key']
				with set_template_var / append_template_var - default template_key is content
				

				template parts added to $this->internal['template_parts']['template_key']
				with load_template_part / append_template_part - default template_key is content
				
				each template key is processed by $this->process_template(template_key); default key is content
				
				process_template processes template vars from $this->internal['template_vars']['template_key'] into template part at $this->internal['template_parts']['template_key'] it also processes lang and internal vars (id, prefix). 
				
				process_template unsets template_vars['template_key'] and template_parts['template_key'] to save memory after processing
				
				each template is processed within their own routine - if a particular element which will be placed on content template needs separate processing, it is processed with everything, vars, lang and ids via $this->process_template() before being assigned into $this->internal['template_vars']['content'] or whichever final template they are going to go into.
				
			
			*/
			
					
			// Load and set the content template - by default it is assigned to content
			$this->load_set_template_part('ticket_content_template');
			
			
			$this->queue_default_top_support_menu_buttons();	
			
			// Display the ticket
				
			$this->display_ticket($post->ID);
		
			
		}
		
		// Process any content queued by actions and functions :
		
		if(is_page($this->opt['pages']['support_desk_page']) AND is_singular() AND in_the_loop() AND is_main_query())
		{
			// This is the support desk page. Append what's necessary for users to create tickets
		
			
			// If any action is requested, reset default page content :
			
			if(isset($_REQUEST[$this->internal['prefix'].'action']))
			{
				$wordpress_content='';				
			}
			
			
			$this->queue_default_top_support_menu_buttons();	

			// Load and set the content template - by default it is assigned to content
			$this->load_set_template_part('help_desk_page_content_template');			
		
				
		}
		
		if(is_page($this->opt['pages']['agent_desk_page']) AND is_singular() AND in_the_loop() AND is_main_query())
		{
			// This is the support agent desk page. Append what's necessary for users to create tickets
			

			// If any action is requested, reset default page content :
			
			if(isset($_REQUEST[$this->internal['prefix'].'action']))
			{
				$wordpress_content='';				
			}
			
			$this->queue_default_top_support_menu_buttons();	
			
			
			// Load and set the content template - by default it is assigned to content
			$this->load_set_template_part('agent_desk_page_content_template');
			
		}
	
		
		$this->set_template_var('notices_placeholder',$this->prepare_notices());	
		
		$this->set_template_var('wordpress_content_placeholder',$wordpress_content);	

		$content = $this->process_template('content');
	
		return $content;
	}
	public function create_ticket_p($v1)
	{
		$request = $v1;
		
		if(!is_user_logged_in())
		{
			auth_redirect(); 
		}
		
		$user_id = get_current_user_id();
		
		$ticket_content = $_REQUEST[$this->internal['prefix'].'ticket_content'];
		$ticket_title = $_REQUEST[$this->internal['prefix'].'ticket_title'];
		$department = $_REQUEST[$this->internal['prefix'].'department'];
		
		// user permission check here
		
		$ticket_author = $user_id;

		if($this->is_user_support_admin($user_id) OR $this->is_user_support_agent($user_id) OR $this->is_user_wp_admin($user_id))
		{
			// If user is admin/agent and requested to open ticket on behaof of another user, change ticket author:
			
			$ticket_author = $request[$this->internal['prefix'].'open_ticket_on_behalf_of_user'];
			
		}
	
		// Get taxonomy name :
		
		
		$chosen_department = get_term_by('id',$_REQUEST[$this->internal['prefix'].'department'],$this->internal['prefix'].'support');
		
		
		$args = array(
				'post_status'    => 'publish',
				'post_type'    => $this->internal['prefix'].'ticket',
				'post_date'    => date("Y-m-d H:i:s", time()),
				'post_modified'    => date("Y-m-d H:i:s", time()),
				'post_title'    => $ticket_title,
				'post_content'    => $ticket_content,
				'post_author'    => $ticket_author,
				'tax_input' => array(
                $this->internal['prefix'].'support' => $chosen_department->name,
             
            )
		);					

		$ticket_result = $this->post_ticket($args);	
		
		if($ticket_result['reply_id']=='')	
		{
			
			$this->queue_notice($this->lang['ticket_creation_failed'],'error','ticket_creation_failed');

			$this->queue_content($this->lang['ticket_creation_failed_long'],'ticket_creation_failed_long');
			
			$this->queue_content_filters();
		}
		else
		{
	
			
			// Send email notification:
			$this->send_ticket_email_notification($ticket_result['ticket_id']);	

			// Check if any attachments are posted:
			
			if(!empty($_FILES) AND $_FILES[$this->internal['prefix'].'ticket_attachment']['tmp_name']!='')
			{
				if(isset($_FILES[$this->internal['prefix'].'ticket_attachment']))
				{
				
					// Insert the attachment :
					
					$attachment_save = $this->save_attachment($ticket_result['reply_id'],$_FILES[$this->internal['prefix'].'ticket_attachment']['tmp_name']);
					
					
					if($attachment_save) 
					{
						
						$this->queue_notice($this->lang['ticket_attachment_saved'],'success','ticket_attachment_saved');
						
					}
					else
					{
							
						$this->queue_notice($this->lang['ticket_attachment_failed'],'error','ticket_attachment_failed');
						
						
					}
									
				
				
				}				
				
			}
			
			// Lets make ticket link:
		
			$link = get_permalink($ticket_result['ticket_id']);
			
			$message = '<a href="'.$link.'">'.$this->lang['ticket_creation_successful_long'].'</a>';
			
			$this->queue_notice($this->lang['ticket_creation_successful'],'success','ticket_creation_successful');
			
			$this->queue_content($message,'ticket_creation_successful_long');
			
			$this->queue_content_filters();
		}
		
	}
	public function make_ticket_department_form_p()
	{
					
		$help_desk_url=get_permalink($this->opt['pages']['support_desk_page']);
		
		$department_form = $this->load_template('ticket_department_form');
		
		$department_form = $this->process_lang($department_form);
			
		// Process the internal ids and replacements
			
		$department_form = $this->process_vars_to_template($this->internal, $department_form,array('prefix','id'));
		
		$department_select_values=$this->make_department_select();
		
		$vars=array(
		
			'help_desk_page_url' => $help_desk_url,
			'departmentselect' => $department_select_values,
		
		);
		
		
		$department_form = $this->process_vars_to_template($vars, $department_form);
		
		
		return $department_form;
			
		
	}
	public function queue_default_top_support_menu_buttons_p()
	{
		global $post;
		
		$ticket_id = $post->ID;
	
		$current_user = wp_get_current_user();
		
		$help_desk_url=get_permalink($this->opt['pages']['support_desk_page']);
			
		if(is_page($this->opt['pages']['agent_desk_page']) OR ($post->post_type==$this->internal['prefix'].'ticket' AND is_singular()))
		{
			
			$agent_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
				
			if($this->is_user_wp_admin($current_user->ID) OR $this->is_user_support_admin($current_user->ID))
			{
				// Queue view all tickets buttons:

				$list_all_ticket_vars = array(
				
					$this->internal['prefix'].'action' => 'list_all_tickets_admin',
					'cb_plugin' => $this->internal['id'],
				
				);
				
				$list_all_tickets = add_query_arg(
					$list_all_ticket_vars,		
					$agent_desk_url
				);			
				
				
				$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['view_all_tickets_label'],$list_all_tickets));		
				
				
			}
			
			
			if($this->is_user_support_admin($current_user->ID) OR $this->is_user_support_agent($current_user->ID) OR $this->is_user_wp_admin($current_user->ID))
			{
				
				$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['agent_support_home_label'],$agent_desk_url));			
				
				
				$list_ticket_vars = array(
				
					$this->internal['prefix'].'action' => 'list_agent_assigned_tickets',
					'cb_plugin' => $this->internal['id'],
				
				);
				
				$list_agent_assigned_tickets = add_query_arg(
					$list_ticket_vars,		
					$agent_desk_url
				);			
				
				
				$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['view_assigned_tickets_label'],$list_agent_assigned_tickets));		

				// If not already the single ticket page, add open ticket button for agents:
				
				if(!($post->post_type==$this->internal['prefix'].'ticket' AND is_singular()))
				{
					$create_ticket_vars = array(
					
						$this->internal['prefix'].'action' => 'ticket_department_form',
						'cb_plugin' => $this->internal['id'],
						$this->internal['prefix'].'ticket_id' =>  $post->ID,
					
					);
					
					$create_ticket_url = add_query_arg(
						$create_ticket_vars,		
						$help_desk_url
					);	


					$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['create_ticket_label'],$create_ticket_url));			
				}
			}	
	
	
		}
		
		if($post->post_type==$this->internal['prefix'].'ticket' AND is_singular())
		{
			
			
			$department = wp_get_post_terms( $post->ID, $this->internal['prefix'].'support' );
	
		
			
			if($this->is_user_department_admin($department[0]->term_id,$current_user->ID))
			{
				
				$reassign_ticket_url = get_permalink($this->opt['pages']['agent_desk_page']);
				
				$reassign_ticket_vars = array(

					$this->internal['prefix'].'action' => 'reassign_ticket',
					'cb_plugin' => $this->internal['id'],
					$this->internal['prefix'].'ticket_id' => $ticket_id,
					$this->internal['prefix'].'department_id' => $department[0]->term_id,
					$this->internal['prefix'].'ticket_id' =>  $post->ID,

				);
					
				$reassign_ticket_url = add_query_arg(
						$reassign_ticket_vars,		
						$reassign_ticket_url
				);					
				
				$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['reassign_ticket_button_label'],$reassign_ticket_url));
				
				
				
				
			}
			
			if($this->is_user_department_agent($department[0]->term_id,$current_user->ID) OR $this->is_user_department_admin($department[0]->term_id,$current_user->ID) OR $this->is_user_wp_admin($current_user->ID))
			{
				
				$status_button = $this->make_ticket_status_change_button($ticket_id);
				
				$this->append_template_var('top_support_menu_buttons', $status_button);
				
				
				$change_department_url = get_permalink($this->opt['pages']['agent_desk_page']);
				
				$change_department_vars = array(

					$this->internal['prefix'].'action' => 'change_ticket_department',
					'cb_plugin' => $this->internal['id'],
					$this->internal['prefix'].'ticket_id' => $ticket_id,
					$this->internal['prefix'].'department_id' => $department[0]->term_id,

				);
						
				$change_department_url = add_query_arg(
						$change_department_vars,		
						$change_department_url
				);					
			
				$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['change_ticket_department_button_label'],$change_department_url));
				

				$delete_ticket_url = get_permalink($this->opt['pages']['agent_desk_page']);
				
				$delete_ticket_vars = array(

					$this->internal['prefix'].'action' => 'delete_this_ticket',
					'cb_plugin' => $this->internal['id'],
					$this->internal['prefix'].'ticket_id' => $ticket_id,

				);
						
				$delete_ticket_url = add_query_arg(
						$delete_ticket_vars,		
						$delete_ticket_url
				);					
			
				$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['delete_ticket_button_label'],$delete_ticket_url));				
				
					
			}
			
		}
	
		if(is_page($this->opt['pages']['support_desk_page']) OR ($post->post_type==$this->internal['prefix'].'ticket' AND is_singular()))
		{
			// Support desk page. Queue standard buttons:
		
			
			$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['support_home_label'],$help_desk_url));
						
			$list_ticket_vars = array(
			
				$this->internal['prefix'].'action' => 'list_tickets',
				'cb_plugin' => $this->internal['id'],
			
			);
			
			$list_tickets_url = add_query_arg(
				$list_ticket_vars,		
				$help_desk_url
			);			
			
			
			$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['view_tickets_label'],$list_tickets_url));
			
			$create_ticket_vars = array(
			
				$this->internal['prefix'].'action' => 'ticket_department_form',
				'cb_plugin' => $this->internal['id'],
			
			);
			
			$create_ticket_url = add_query_arg(
				$create_ticket_vars,		
				$help_desk_url
			);	

			$this->append_template_var('top_support_menu_buttons', $this->make_button($this->lang['create_ticket_label'],$create_ticket_url));			
			
		
		}		
		
		
		
	
	}
	public function make_ticket_form_p()
	{
		
		$current_user = wp_get_current_user();
		
		$help_desk_url=get_permalink($this->opt['pages']['support_desk_page']);
		
		$department_form = $this->load_template('ticket_form');
		
		$department_form = $this->process_lang($department_form);
			
		// Process the internal ids and replacements
			
		$department_form = $this->process_vars_to_template($this->internal, $department_form, array('prefix','id'));
		
		$department_select_values=$this->make_department_select();
		
		if($this->is_user_support_admin($current_user->ID) OR $this->is_user_support_agent($current_user->ID) OR $this->is_user_wp_admin($current_user->ID))
		{
			// User can opn ticket for other users :
			
			$ticket_open_for_different_user = '<h5>'.$this->lang['open_ticket_for_user'].'</h5>';
			
			$ticket_open_for_different_user .= wp_dropdown_users(array('echo' => false,'selected' => $current_user->ID,'name' => $this->internal['prefix'].'open_ticket_on_behalf_of_user')).'<br><br>';
			
		}
		
		if(!isset($ticket_open_for_different_user))
		{
			$ticket_open_for_different_user='';
		}
		
		$vars = array(
		
			'help_desk_page_url' => $help_desk_url,
			'department_id' => $_REQUEST[$this->internal['prefix'].'department'],
			'ticket_open_for_different_user' => $ticket_open_for_different_user,
		
		);
		
		$department_form = $this->process_vars_to_template($vars, $department_form);
	
		return $department_form;
		
	}
	public function make_department_select_p($v1)
	{
		$selected=$v1;

		$departments=$this->get_departments_array();


		if(is_array($departments))
		{
			foreach($departments as $key => $value)
			{
				$dept_array[$departments[$key]->term_id]=$departments[$key]->name;

			}
		
	
		$dept_select = $this->make_select($dept_array,$this->internal['prefix'].'department',$selected);		
		
			return $dept_select;
		
		}
		else
		{
			return '<select><option>'.$this->lang['no_support_departments_found'].'</option></select>';
			
		}
	}
	public function get_departments_array_p($v1)
	{
		$selected=$v1;
		
		$departments = get_terms( $this->internal['prefix'].'support', array( 'hide_empty' => 0));
	
		if(is_wp_error($departments))
		{
			$this->internal['frontend_errors']['department_selection_error']= $departments->get_error_message();
			return false;
		} 	
		return $departments;
		
	}
	public function add_staff_to_department_p($v1)
	{
// User capability check here
		
		if(!current_user_can('manage_options'))
		{
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission','admin');
			return false;
		}		

		if($_REQUEST[$this->internal['prefix'].'department']!='' AND $_REQUEST[$this->internal['prefix'].'user']!='' AND $_REQUEST[$this->internal['prefix'].'role']!='')
		{
			// Check if user is already in the department
			
			if(isset($this->opt['departments_to_staff'][$_REQUEST[$this->internal['prefix'].'department']][$_REQUEST[$this->internal['prefix'].'user']]))
			{
				$checked_user=$this->opt['departments_to_staff'][$_REQUEST[$this->internal['prefix'].'department']][$_REQUEST[$this->internal['prefix'].'user']];
			}
			else
			{
				$checked_user=false;
			}
			
			if($checked_user==$_REQUEST[$this->internal['prefix'].'role'])
			{
				
				$this->queue_notice($this->lang['error_staff_operation_failed_alread_in_department'],'error','error_staff_operation_failed_alread_in_department','admin');
				return false;
				
			}
			
			$this->opt['departments_to_staff'][$_REQUEST[$this->internal['prefix'].'department']][$_REQUEST[$this->internal['prefix'].'user']]=$_REQUEST[$this->internal['prefix'].'role'];
	
			$result=update_option($this->internal['prefix'].'options',$this->opt);
			
			if($result)
			{
				$this->queue_notice($this->lang['success_staff_operation_successful'],'success','success_staff_operation_successful','admin');
				
				return true;
			}
			else
			{		
				$this->queue_notice($this->lang['error_staff_operation_failed'],'error','error_staff_operation_failed','admin');
				
				return false;
			}
		}
		else
		{
		
			$this->queue_notice($this->lang['department_user_or_role_cant_be_empty'],'error','department_user_or_role_cant_be_empty','admin');
			
			return false;
		
		}		
		
	}
	public function remove_staff_from_department_p($v1)
	{
		// User capability check here
		
		if(!current_user_can('manage_options'))
		{
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission','admin');
			return false;
		}	

		unset($this->opt['departments_to_staff'][$_REQUEST[$this->internal['prefix'].'department']][$_REQUEST[$this->internal['prefix'].'user']]);
		
		$result = update_option($this->internal['prefix'].'options',$this->opt);
					
		if($result)
		{	
			$this->queue_notice($this->lang['success_staff_operation_successful'],'success','success_staff_operation_successful','admin');					

		}
		else
		{
			$this->queue_notice($this->lang['error_staff_operation_failed'],'error','error_staff_operation_failed','admin');
					
			
		}
		
		
	}
	public function check_user_role_p($v1)
	{
		$requested=$v1;
		
		//if($this->opt)
		
		
	}
	public function make_roles_select_p($v1)
	{
		$selected=$v1;


		foreach($this->opt['roles'] as $key => $value)
		{
			$roles_array[$key]=$this->lang['role_'.$key];

		}

		$role_select=$this->make_select($roles_array,$this->internal['prefix'].'role',$selected);		
				
		
		return $role_select;
	}
	public function template_redirections_p($link)
	{
		global $post;

		if(isset($post->post_type) AND $post->post_type==$this->internal['prefix'].'ticket')
		{
			if(!$this->check_ticket_viewing_permissions($post))
			{
				
				wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
				exit;
			}	
			
		}
		return $link;
	}
	public function display_ticket_p($v1)
	{
		global $post;
		
		$ticket_id = $v1;
		
		$current_user = wp_get_current_user();
		
		$ticket_url = get_permalink($ticket_id);
		
		// Meta test :
		
		/*
		echo $this->add_meta(
			50,
			'meta_name4',
			8,
			'int',
			false,
			false,
			'test_set'
		);
		echo $this->add_meta(
			50,
			'meta_name6',
			12,
			'float',
			false,
			false,
			'test_set'
		);
		*/
		
		// echo $this->delete_item_meta_by_set(50,'test_set');
		// echo '<pre>';
		// print_r($this->get_all_item_meta_by_set(50,'test_set'));
		// echo '</pre>';
		/*
		$args=array(
		'order_by' =>	'parent',
		'sort' =>	'ASC',
		'start' => 0,
		'limit' =>	5,
		
		
		);
		echo '<pre>';
		print_r($this->get_items_by_meta('meta_name6',array(3.4,3.999991,12),'float','ticket',$args));
		echo '</pre>';
		
		
		*/
		
		
		$ticket_header = $this->load_template('ticket_header');
		
		$ticket_footer = $this->load_template('ticket_footer');
		
		$ticket_quick_reply_form = $this->load_template('ticket_quick_reply_form');
		
		$ticket_entry_header = $this->load_template('ticket_reply_entry_header');
		
		$ticket_entry_footer = $this->load_template('ticket_reply_entry_footer');
		
		$ticket_entry_template = $this->load_template('ticket_reply_entry');
		
		$department = wp_get_post_terms( $ticket_id, $this->internal['prefix'].'support' );
	
		
		$replies = $this->get_replies($ticket_id);

		
		$ticket = $ticket_header;

		
		// If the user is a support agent or admin in the department of this ticket add a specific header for them:


		if($this->is_user_department_admin($department[0]->term_id,$current_user->ID) OR $this->is_user_department_agent($department[0]->term_id,$current_user->ID) OR $this->is_user_wp_admin($current_user->ID))
		{
			$agent_ticket_header = $this->load_template('agent_ticket_header');			
			
			$agent_ticket_header = $this->process_lang($agent_ticket_header);
		
			// Process the internal ids and replacements
		
			$agent_ticket_header = $this->process_vars_to_template($this->internal, $agent_ticket_header,array('prefix'));			
			
			$ticket_owner_info = get_userdata($post->post_author);
			
			
			$modified_at = $this->ticket_modified_ago($post->post_modified);
				
			$days = $modified_at['days'];
			$hours = $modified_at['hours'];
			$minutes = $modified_at['minutes'];
			
			$assigned_rep = $this->get_item_meta($ticket_id,'assigned_rep','int','post',true);
		
			
			if($assigned_rep!='')
			{
				$rep_details = get_userdata( $assigned_rep ); 
			
				
			}
			
			$agent_template_vars = array(

				'ticket_owner' => $ticket_owner_info->data->display_name,
				'ticket_created' => $post->post_date,
				'ticket_updated_minutes' => $minutes,
				'ticket_assigned_to' => $rep_details->display_name,
				'ticket_updated_ago_before' => $this->lang['ticket_updated_ago_before'],
				'ticket_updated_ago_after' => $this->lang['ticket_updated_ago_after'],
				'ticket_updated_ago_before' => $this->lang['ticket_updated_ago_before'],
				'ticket_updated_ago_after' => $this->lang['ticket_updated_ago_after'],
				'ticket_updated_minutes_label' => $this->lang['ticket_updated_minutes_label'],
			);
			
			// Add day and hour values if they are not zero:
			if($days!=0)
			{
				$agent_template_vars['ticket_updated_days']=$days;
				$agent_template_vars['ticket_updated_days_label']=$this->lang['ticket_updated_days_label'];
				
			}
			else
			{
			
				$agent_template_vars['ticket_updated_days']='';
				$agent_template_vars['ticket_updated_days_label']='';
				
			}
			if($hours!=0)
			{
				$agent_template_vars['ticket_updated_hours']=$hours;
				$agent_template_vars['ticket_updated_hours_label']=$this->lang['ticket_updated_hours_label'];
				
			}
			else
			{
			
				$agent_template_vars['ticket_updated_hours']='';
				$agent_template_vars['ticket_updated_hours_label']='';
				
			}
				
			$ticket .= $this->process_vars_to_template($agent_template_vars,$agent_ticket_header);
		
			
		}
		
		// Now do the rest of replies from custom table
		
		if(is_array($replies) AND count($replies)>0)
		{
			foreach($replies as $reply)
			{
				$ticket.= $ticket_entry_header;
				
				// Get any attachment info if they exist:
			
				$attachments_insert = $this->reply_attachments($reply['reply_id']);
									
				$template_vars=array(
						'user_avatar' => $this->get_user_avatar($reply['reply_user']),
						'reply_content' => stripslashes(wpautop($reply['reply_content'])),
						'user_name_link' => '<a href="'.get_author_posts_url($reply['reply_user']).'">'.get_the_author_meta( 'display_name',$reply['reply_user'] ).'</a>',
						'reply_id'=> $reply['reply_id'],
						'attachments_insert'=> $attachments_insert,
						
				);				
				
				$ticket.= $this->process_vars_to_template($template_vars, $ticket_entry_template);
				
				$ticket.= $ticket_entry_footer;
				
			}
		}
		
		$ticket_status_changer = $this->load_template('ticket_status_changer');
		
		
		$help_desk_url = get_permalink($this->opt['pages']['support_desk_page']);
		
	
		
		if($this->get_ticket_status($ticket_id)=='open')
		{

			$ticket_status = $this->lang['ticket_status_open'];		
		}
		if($this->get_ticket_status($ticket_id)=='closed')
		{

			$ticket_status = $this->lang['ticket_status_closed'];		
		}		

		$status_button = $this->make_ticket_status_change_button($ticket_id);
			
		$template_vars=array(
				
				'current_ticket_status'=> $ticket_status,
				'ticket_status_button'=> $status_button,
				
		);		
			
		$ticket_status_changer = $this->process_vars_to_template($this->internal, $ticket_status_changer,array('prefix'));	
		
		$ticket_status_changer = $this->process_lang($ticket_status_changer);
		
		$ticket_status_changer = $this->process_vars_to_template($template_vars,$ticket_status_changer);
		
		$ticket.= $ticket_status_changer;
		
		
		if($this->get_ticket_status($ticket_id)=='open')
		{		
			$ticket.= $ticket_quick_reply_form;
		}
		
		$ticket.= $ticket_footer;
		
		$ticket = $this->process_lang($ticket);
		
		
		// Process the internal ids and replacements
		
		$ticket = $this->process_vars_to_template($this->internal, $ticket,array('prefix'));
		
		$ticket = str_replace('{***ticket_id***}',$post->ID,$ticket);
		
		
		if($this->is_user_department_admin($department[0]->term_id,$current_user->ID) OR $this->is_user_department_agent($department[0]->term_id,$current_user->ID))
		{
			// Queue agent operations bar if user is agent or admin
			if(!isset($agent_admin_operations_bar))
			{
				$agent_admin_operations_bar='';				
			}
			$this->queue_content($agent_admin_operations_bar,'agent_admin_operations_bar');
				
		}
		// Queue content to frontend:
		
		$this->set_template_var('plugin_content_placeholder',$ticket);
		
				
		return $ticket;
		
	}
	public function check_ticket_viewing_permissions_p($v1)
	{
		
		// Checks ticket permissions - to view
		// ticket needs to be either user's ticket, user must be an admin or support rep in the department ticket is in
		$post_id = $v1;
		
		$post = get_post($post_id);
				
		$user=wp_get_current_user();
		
		
		// return true if ticket belongs to user:

		if($user->ID==$post->post_author)
		{
			return true;			
		}
		
		// return true if user is wp admin:

		if($this->is_user_wp_admin($user->ID))
		{
			return true;			
		}
		
		// Check the reps assigned to ticket, if any of them match, return true :
		
		$assigned_reps = $this->get_item_meta($post->ID,'assigned_rep','int','post');
		
		if(!is_array($assigned_reps) OR count($assigned_reps)==0)
		{
		
			$this->queue_notice($this->lang['no_agents_found_assigned_to_ticket'],'error','no_agents_found_assigned_to_ticket');				
		}
		else
		{
			// Iterate through each saved rep
			foreach($assigned_reps as $key => $value)
			{
				$current_rep_data = get_userdata( $assigned_reps[$key]['int_value']);
				
				if($current_rep_data->ID==$user->ID)
				{
					// return true if rep is in assigned list
					return true;				
				}
				
			}		
			
		}
		
		// Check if user is support admin in relevant department:
	
		$terms=get_the_terms($post->ID,$this->internal['prefix'].'support');
		
		
		foreach($terms as $key => $value)
		{
			if($this->is_user_department_admin($terms[$key]->term_id,$user->ID))
			{
				return true;
				
			}
		}
		
		// If all checks failed, return false
		
		return false;

	}
	public function setup_languages_p()
	{
		// Here we do plugin specific language procedures. 
		
		// Set up the custom post type and its taxonomy slug into options:
		
		$current_lang=get_option($this->internal['prefix'].'lang_'.$this->opt['lang']);
		
		// Get current options
		
		$current_options=get_option($this->internal['prefix'].'options');
		
		$current_options['ticket_post_type_slug']=$current_lang['ticket_post_type_slug'];
		$current_options['ticket_category_slug']=$current_lang['ticket_post_type_category_slug'];
		
		// Update options :
		
		update_option($this->internal['prefix'].'options',$current_options);
		
		// Set current options the same as well :
		
		$this->opt=$current_options;
		
	}
	public function activate_p()
	{
	
		$this->check_create_pages();
		
		flush_rewrite_rules(true);
		
		// Create attachments directory:
		
		// Because init has not been done yet, we have to construct the uploads dir var:
		
		$upload_dir = wp_upload_dir();
		
		$this->internal['attachments_dir'] = $upload_dir['basedir']. '/'.$this->internal['prefix'].'ticket_attachments/';
		
		if(!file_exists($this->internal['attachments_dir']))
		{
		
			$create_attachments_dir = wp_mkdir_p($this->internal['attachments_dir']);

			if(!$create_attachments_dir)
			{
				// Errör 
		
				$this->queue_notice($this->lang['error_couldnt_create_attachments_dir'],'error','error_couldnt_create_attachments_dir','admin');			
				
			}	
			else
			{
				// Drop no auth index php and index html to make sure no directory listing happens:
				
				file_put_contents($this->internal['attachments_dir'].'/index.php','No Auth');
				file_put_contents($this->internal['attachments_dir'].'/index.html','No Auth');
				
				
				// Drop a htaccess there to deny access to any attachment so they wont be accessible even if plugin is deactivated:
				
				// Rewrite rules:
				
				$rewrite_rules = '
# BEGIN cb_p3 Protect Attachments
<IfModule mod_rewrite.c>
    RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_FILENAME} -s
	RewriteRule ^'.$this->internal['attachment_relative_url'].'(.*)$ index.php?cb_p3_action=serve_attachment&attachment=$1 [QSA,L]
</IfModule>
# END  cb_p3 Protect Attachments';		
				
				file_put_contents($this->internal['attachments_dir'].'/.htaccess',$rewrite_rules);
				
			}
			
		}
		
		
			
		// If no support staff exists, we must add the current user who is activating the plugin and super admins as support admins:
		
		// Get existing departments :
		
		$departments=$this->get_departments_array();

		$current_user_id = get_current_user_id();
		
		if((!is_array($this->opt['departments_to_staff']) OR count($this->opt['departments_to_staff'])==0))
		{
			foreach($departments as $key => $value)
			{
				$this->opt['departments_to_staff'][$departments[$key]->term_id][$current_user_id]='support_admin';
			}
		
			update_option($this->internal['prefix'].'options',$this->opt);
		}	
		// Check if woocommerce is installed to give our message
		$this->check_woocommerce_exists();
		
		if($this->internal['woocommerce_installed'] AND $this->check_addon_exists('woocommerce_integration')=='notinstalled')
		{
			$this->queue_notice($this->lang['woocommerce_addon_available'],'info','update_available','perma',true);		
		}
		
	}
	public function check_redirect_to_setup_wizard_p($v1)
	{
		$activated_plugin =  $v1;

		if($activated_plugin!=$this->internal['plugin_slug'])
		{
			return;
			
		}		
		// If setup was not done, redirect to wizard
		if(!$this->opt['setup_done'])
		{	
	
			wp_redirect($this->internal['admin_url'].'admin.php?page=settings_'.$this->internal['id']);
			exit;	
		}		
		
	}
	public function check_create_pages_p()
	{
		
		if (!is_admin())
		{
			return;			
		}
		
		$user = wp_get_current_user();
		
		$lang_code=get_bloginfo('language');
		
		// If the language was changed from admin, then set to the selected language:
		
		if($this->internal['prefix'].'current_language'!='')
		{
			// The language code was already set before during language change. we just pick it up
			$lang_code=$this->opt['lang'];	
						
		}
		
		// Get relevant language :
		
		$language=get_option($this->internal['prefix'].'lang_'.$lang_code);
		
		// If we dont have the language available, switch to english
		if(!$language)
		{
			$language=get_option($this->internal['prefix'].'lang_en');
		}
		
		$support_desk_page_title = $language['help_desk_page_title'];
		$support_desk_page_content = $language['help_desk_page_content'];

		$page_check = get_page_by_title($support_desk_page_title);
		
		if(!isset($page_check->ID))
		{
		
			$support_desk_page = array(
					'post_type' => 'page',
					'post_title' => $support_desk_page_title,
					'post_content' => $support_desk_page_content,
					'post_status' => 'publish',
					'post_author' => 1,
			);
		
		
			$support_desk_page_id = wp_insert_post($support_desk_page);
		}
		else
		{
			$support_desk_page_id = $page_check->ID;
			// If it was in trash, restore it:
			
			if('trash'==get_post_status($support_desk_page_id))
			{
				$untrash = wp_untrash_post($support_desk_page_id);
				
				if($untrash)
				{
					//$this->queue_notice($this->lang['success_post_found_in_trash_and_restored'],'success','success_post_found_in_trash_and_restored','admin');	
										
				}
				else
				{
					//$this->queue_notice($this->lang['error_post_found_in_trash_but_couldnt_restore'],'error','error_post_found_in_trash_but_couldnt_restore','admin');				
					
				}
								
			}
		}
		
		
		$no_permission_page_title = $language['not_enough_permissions_page_title'];
		$no_permission_page_content = $language['not_enough_permissions_page_content'];


		$page_check = get_page_by_title($no_permission_page_title);
		
		if(!isset($page_check->ID))
		{
		
			$no_permission_page = array(
					'post_type' => 'page',
					'post_title' => $no_permission_page_title,
					'post_content' => $no_permission_page_content,
					'post_status' => 'publish',
					'post_author' => 1,
			);
		
		
			$no_permission_page_id = wp_insert_post($no_permission_page);
		}
		else
		{
			$no_permission_page_id = $page_check->ID;
		
			if('trash'==get_post_status($no_permission_page_id))
			{
				$untrash = wp_untrash_post($no_permission_page_id);
				
				if($untrash)
				{
					//$this->queue_notice($this->lang['success_post_found_in_trash_and_restored'],'success','success_post_found_in_trash_and_restored','admin');	
										
				}
				else
				{
					//$this->queue_notice($this->lang['error_post_found_in_trash_but_couldnt_restore'],'error','error_post_found_in_trash_but_couldnt_restore','admin');				
					
				}
								
			}	
		}
		
		$agent_desk_page_title = $language['agent_desk_page_title'];
		$agent_desk_page_content = $language['agent_desk_page_content'];


		$page_check = get_page_by_title($agent_desk_page_title);
		
		if(!isset($page_check->ID))
		{
		
			$agent_desk_page = array(
					'post_type' => 'page',
					'post_title' => $agent_desk_page_title,
					'post_content' => $agent_desk_page_content,
					'post_status' => 'publish',
					'post_author' => 1,
			);
		
		
			$agent_desk_page_id = wp_insert_post($agent_desk_page);
		}
		else
		{
			$agent_desk_page_id = $page_check->ID;
		
			if('trash'==get_post_status($agent_desk_page_id))
			{
				$untrash = wp_untrash_post($agent_desk_page_id);
				
				if($untrash)
				{
					//$this->queue_notice($this->lang['success_post_found_in_trash_and_restored'],'success','success_post_found_in_trash_and_restored','admin');	
										
				}
				else
				{
					//$this->queue_notice($this->lang['error_post_found_in_trash_but_couldnt_restore'],'error','error_post_found_in_trash_but_couldnt_restore','admin');				
					
				}
								
			}	
		}
		
			
		$default_department_check = get_term_by('name',$language['general_support_department_label'],$this->internal['prefix'].'support');
	
		
		if(!isset($default_department_check->term_id))
		{
	
			$default_department = array(
				  'cat_name' => $language['general_support_department_label'],
				  'category_parent' => 0,
				  'taxonomy' => $this->internal['prefix'].'support' 
			);

			// During activation post type is not yet initiated. We must manually do it.
			
			$this->register_post_types();
			
			$default_department_id = wp_insert_category($default_department);

		}
		else
		{
			$default_department_id = $default_department_check->term_id;		
			
		}
		
	
		
		// Put current user (admin) as admin to default department:
		
		$this->opt['departments_staff'][$default_department_id][$user->ID] = 'support_admin';
		
		$this->opt['pages']['no_permission_to_view_ticket_page'] = $no_permission_page_id;
		$this->opt['pages']['no_permission_to_view_ticket_page_slug'] = get_page_uri($no_permission_page_id);
		
		$this->opt['pages']['support_desk_page'] = $support_desk_page_id;
		$this->opt['pages']['support_desk_page_slug'] = get_page_uri($support_desk_page_id);
		
		$this->opt['pages']['agent_desk_page'] = $agent_desk_page_id;
		$this->opt['pages']['agent_desk_page_slug'] = get_page_uri($agent_desk_page_id);
		
		
		// Set the name for the site support service:
		$site_name = get_bloginfo();
		
		$support_org_name = $site_name.' '.$this->lang['help_desk_label'];

		$this->opt['org_name'] = $support_org_name;
		
		// Save the options
		
		update_option($this->internal['prefix'].'options' ,$this->opt);
	
	}
	public function enqueue_frontend_styles_p()
	{
		
		wp_enqueue_style( $this->internal['id'].'-css-main', $this->internal['template_url'].'/'.$this->opt['template'].'/style.css' );
	}
	public function enqueue_admin_styles_p()
	{
	
		$current_screen=get_current_screen();

		if($current_screen->base=='toplevel_page_settings_'.$this->internal['id'])
		{
			wp_enqueue_style( $this->internal['id'].'-css-admin', $this->internal['plugin_url'].'plugin/includes/css/admin.css' );
			
		}
	}
	public function enqueue_frontend_scripts_p()
	{
	
	
	
		
	}	
	public function enqueue_admin_scripts_p()
	{
	

		wp_enqueue_script( $this->internal['id'].'-js-admin', $this->internal['plugin_url'].'plugin/includes/scripts/admin.js' );	
		
		
	}	
	public function get_replies_p($post_id)
	{
		
		return $this->get_many_by_post_id('reply',$post_id);
		
		
	}
	public function register_post_types_p()
	{
		
        $labels = array(
            'name' => $this->lang['ticket_post_type_plural_name'],
            'singular_name' => $this->lang['ticket_post_type_singular_name'],
            'add_new' => $this->lang['ticket_post_type_add_new'],
            'add_new_item' => $this->lang['ticket_post_type_add_new_item'],
            'edit_item' => $this->lang['ticket_post_type_edit_item'],
            'new_item' => $this->lang['ticket_post_type_new_item'],
            'view_item' => $this->lang['ticket_post_type_view_item'],
            'search_items' => $this->lang['ticket_post_type_search_items'],
            'not_found' => $this->lang['ticket_post_type_not_found'],
            'not_found_in_trash' => $this->lang['ticket_post_type_not_found_in_trash'],
            'parent_item_colon' => ''
        );
		


        $result = register_post_type($this->internal['prefix'].'ticket', array(
		
				'labels' => $labels,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'capability_type' => 'post',
                'hierarchical' => false,
                'publicly_queryable' => true,
                'query_var' => $this->internal['prefix'].'ticket_slug',
                'exclude_from_search' => false,
                'rewrite' => array('slug' => $this->opt['ticket_post_type_slug'], 'with_front' => false, 'feeds' => false, 'pages' => true),
                'taxonomies' => array($this->internal['prefix'].'department'),
                'show_in_nav_menus' => false,
                'menu_position' => 20,
                'supports' => array('title', 'editor', 'author', 'thumbnail',   'custom-fields')
            )
        );

		if(is_wp_error($result))
		{
			$this->internal['admin_errors'][]= $result->get_error_message();
		
		} 

        $result = register_taxonomy($this->internal['prefix'].'support', array($this->internal['prefix'].'ticket'), array('hierarchical' => true,
            'label' => $this->lang['ticket_post_type_category_plural'],
            'singular_label' => $this->lang['ticket_post_type_category_singular'],
            'rewrite' => array( 'slug' => $this->opt['ticket_category_slug'] ),
            'query_var' => true
        ));	
		
		if(is_wp_error($result))
		{
			$this->internal['admin_errors'][]= $result->get_error_message();
					
		}
	
	}
	public function route_request_p()
	{
		global $post;
		
		$current_term = get_queried_object();
		$current_user = wp_get_current_user();
		

		$uri=explode('/',$_SERVER['REQUEST_URI']);
		
		// Check if the first slug is our custom post type, taxonomy slug or any particular page:
	
		if(get_post_type()==$this->internal['prefix'].'ticket')
		{
			

			if(!is_user_logged_in())
			{
				auth_redirect(); 
			}			
			
			// Check ticket viewing permissions:
			
			if(!$this->check_ticket_viewing_permissions($current_term->ID))
			{
				
				$this->internal['frontend_errors']['no_permission_to_view_ticket']=$this->lang['no_permission_to_view_ticket_explanation'];				
				
				return $message;
			}
			
			// A ticket. We must queue content filter or any necessary function
			
			$this->queue_content_filters();
			
			
		}

		if(isset($current_term->taxonomy) AND $current_term->taxonomy ==  $this->internal['prefix'].'support')
		{
			
			
			// A ticket category (department) page. Redirect to agent page with relevant listing action:
			

			$agent_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
				
			$list_department_ticket_vars = array(
			
				$this->internal['prefix'].'action' => 'list_all_tickets_admin',
				'cb_plugin' => $this->internal['id'],
				$this->internal['prefix'].'department_id' => $current_term->term_id,
			
			);
			
			$list_department_tickets_url = add_query_arg(
				$list_department_ticket_vars,		
				$agent_desk_url
			);			
			
			wp_redirect($list_department_tickets_url);
			exit;
			
		}
		
		if(isset($current_term->ID) AND $current_term->ID == $this->opt['pages']['no_permission_to_view_ticket_page'])
		{
			// No permission for ticket warning page. Queue content filter or any necessary function
	
			$this->queue_content_filters();
			
		}
		
		if(isset($current_term->ID) AND $current_term->ID == $this->opt['pages']['support_desk_page'])
		{
			
			if(!is_user_logged_in())
			{
				auth_redirect(); 
			}
			
			// Support desk main page. Queue content filter or any necessary function
			
			$this->queue_content_filters();
			$this->queue_title_filters();
			
		}
		if(isset($current_term->ID) AND $current_term->ID == $this->opt['pages']['agent_desk_page'])
		{
			
			
			if(!is_user_logged_in())
			{
				auth_redirect(); 
			}
			// Check if user is a support admin or an agent:
			
			if(!($this->is_user_support_admin($current_user->ID) OR $this->is_user_support_agent($current_user->ID) OR $this->is_user_wp_admin($current_user->ID)))
			{
				// User is not a rep, admin or wp admin. Send to no perm page:
				
				wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
				exit;
				
				
				
			}
			
			
			// Support desk main page. Queue content filter or any necessary function
			
			$this->queue_content_filters();
			$this->queue_title_filters();
			
		}
			
		
		
	}
	public function ticket_form_p()
	{
		// This wraps the ticket form . 
		
		if(!is_user_logged_in())
		{
			auth_redirect(); 
		}		

	
		// No error no redirection, then queue ticket form :
		
		
		
		$this->append_template_var('plugin_content_placeholder',$this->make_ticket_form());
				
				
		return;
		
	}
	public function ticket_department_form_p()
	{
		// This wraps department form action. And reroutes department form to ticket form if necessary 
		
		// Redirect directly to ticket form if there is only one department
		

		if(!is_user_logged_in())
		{
			auth_redirect(); 
		}
		
		$departments = get_terms( $this->internal['prefix'].'support', array( 'hide_empty' => 0));
	
		if(is_wp_error($departments))
		{

			$this->queue_notice($this->lang['department_retrieval_error'],'error','department_retrieval_error');
			$this->queue_notice($departments->get_error_message(),'error','department_retrieval_error_message');
						
			
			return;
		} 
		
		// Redirect to ticket open screen if there is one department
		$this->open_ticket_single_department_redirect_check(true,$departments);

		
		// No error no redirection, then queue department form :
		
		$this->append_template_var('plugin_content_placeholder',$this->make_ticket_department_form());
				
		return;
		
	}
	public function list_tickets_p()
	{
		// Gets a list of user's tickets on user end
		
		global $current_user;
		
		$paged = ( get_query_var('page') ) ? get_query_var('page') : 1;
		
		// Do Ticket listing header
	
		$ticket_listing_header_template = $this->load_template('ticket_listing_header');
	
		$ticket_listing_header_template = $this->process_lang($ticket_listing_header_template);
		
		$ticket_listing_header_template = $this->process_vars_to_template($this->internal, $ticket_listing_header_template, array('prefix','id'));
		
		$help_desk_url=get_permalink($this->opt['pages']['support_desk_page']);
		
		
		$url_vars = array(
					
						$this->internal['prefix'].'action' => 'list_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_open_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			
	
		$url_vars = array(
					
						$this->internal['prefix'].'action' => 'list_resolved_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_resolved_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			

		

		$vars = array(
		
			'list_open_tickets_link' => $list_open_tickets_link,
			'list_resolved_tickets_link' => $list_resolved_tickets_link,
			
		);
		
		$tickets_content = $this->process_vars_to_template($vars, $ticket_listing_header_template);
		
		
		// Do Ticket listing header EOF
		

		$args = array(
			'author' => $current_user->ID,
			'post_type' => $this->internal['prefix'].'ticket',
			'paged' => $paged,
			'posts_per_page' => -1,
			'order' => 'DESC',
			'orderby' => 'modified',
		);		
		
		$tickets = new WP_Query( $args );
		
		if (is_wp_error($tickets)) {
			$this->internal['frontend_errors']['ticket_listing_error_long'] = $tickets->get_error_message();
			$this->internal['frontend_errors']['ticket_listing_error'] = $tickets->get_error_message();
		} 
		else
		{
		
			if( $tickets->have_posts() ) {
				
				$ticket_listing_entry_template = $this->load_template('user_ticket_listing_entry');
				
				while( $tickets->have_posts()) {
					
					$tickets->the_post();
					// title, content, etc
				
					$ticket_id = get_the_ID();
					
					// Skip closed tickets - this must be improved later to have the query take only open tickets #IMPROVE
					
					if($this->get_ticket_status($ticket_id)=='closed')
					{
						continue;
					}	
											
					
					$ticket_entry = $this->process_lang($ticket_listing_entry_template);
					
					$ticket_entry = $this->process_vars_to_template($this->internal, $ticket_entry, array('prefix','id'));

					$newly_updated = '';
					
					// Check if ticket was updated before user's last visit

					if(isset($ticket['post_modified']) AND strtotime($ticket['post_modified'])>$user_last_seen)
					{
						// Ticket was modified since user's last visit
						// Naming variable newly instead of recently because i just feel like it
						$newly_updated = ' '.$this->internal['prefix'].'user_updated_since_last_visit';					
					}
					
					$last_reply = $this->get_last_reply($ticket_id);

					$ticket_last_reply_user = $last_reply[0]['reply_user'];
					
					$post_author_id = get_post_field( 'post_author', $ticket_id );
		
			
					if($ticket_last_reply_user==$post_author_id)
					{
						$last_updated_by=$this->lang['label_you'];
					}
					else
					{
						$last_updated_by=$this->lang['label_support_team'];	
					}
					
			

					if($this->get_ticket_status($ticket_id)=='closed')
					{
						$button_label_view_or_resolved = $this->lang['ticket_resolved'];
					}	
					else
					{
						$button_label_view_or_resolved = $this->lang['button_label_view'];
						
					}				
					
						
					$vars = array(
					
						'ticket_link' => get_permalink(),
						'ticket_title' => get_the_title(),
						'user_ticket_last_updated_by' => $this->lang['user_ticket_last_updated_by'],
						'last_updated_by' => $last_updated_by,
						'newly_updated' => $newly_updated,
						'button_label_view_or_resolved' => $button_label_view_or_resolved,
						
					);
					
				
					$ticket_entry = $this->process_vars_to_template($vars, $ticket_entry);
						
					$tickets_content .= $ticket_entry;
					
				}
				
			
				wp_reset_postdata();
				
				$this->queue_content($tickets_content,'user_ticket_listing');
				
			
			}		
			else
			{
				$this->queue_notice($this->lang['ticket_listing_no_result'],'info','ticket_listing_no_result');
				
				$this->queue_content($tickets_content,'ticket_listing_no_result');
			}
		}
		
		
	
		// Queue content filters to show content :
		
		$this->append_template_var('plugin_content_placeholder',$tickets_content);
	
		return $tickets_content;
		
		
	}
	public function list_resolved_tickets_p()
	{
		// Gets a list of user's tickets on user end
		
		global $current_user;
		
		$paged = ( get_query_var('page') ) ? get_query_var('page') : 1;
		
		
		// Do Ticket listing header
	
		
		$ticket_listing_header_template = $this->load_template('ticket_listing_header');
	
		$ticket_listing_header_template = $this->process_lang($ticket_listing_header_template);
		
		$ticket_listing_header_template = $this->process_vars_to_template($this->internal, $ticket_listing_header_template, array('prefix','id'));
		
		$help_desk_url=get_permalink($this->opt['pages']['support_desk_page']);
		
		
		$url_vars = array(
					
						$this->internal['prefix'].'action' => 'list_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_open_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			
	
		$url_vars = array(
					
						$this->internal['prefix'].'action' => 'list_resolved_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_resolved_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			

		

		$vars = array(
		
			'list_open_tickets_link' => $list_open_tickets_link,
			'list_resolved_tickets_link' => $list_resolved_tickets_link,
			
		);
		
		$tickets_content = $this->process_vars_to_template($vars, $ticket_listing_header_template);
		
		
		// Do Ticket listing header EOF
		

		$args = array(
			'author' => $current_user->ID,
			'post_type' => $this->internal['prefix'].'ticket',
			'paged' => $paged,
			'posts_per_page' => -1,
			'order' => 'DESC',
			'orderby' => 'modified',
		);		
		
		$tickets = new WP_Query( $args );
		
		if (is_wp_error($tickets)) {
			$this->internal['frontend_errors']['ticket_listing_error_long'] = $tickets->get_error_message();
			$this->internal['frontend_errors']['ticket_listing_error'] = $tickets->get_error_message();
		} 
		else
		{
		
			if( $tickets->have_posts() ) {
				
				$ticket_listing_entry_template = $this->load_template('user_ticket_listing_entry');
				
				while( $tickets->have_posts()) {
					
					$tickets->the_post();
					// title, content, etc
				
					$ticket_id = get_the_ID();
					
					// Skip open tickets - this must be improved later to have the query take only closed tickets #IMPROVE
					
					if($this->get_ticket_status($ticket_id)=='open')
					{
						continue;
					}	
											
					
					$ticket_entry = $this->process_lang($ticket_listing_entry_template);
					
					$ticket_entry = $this->process_vars_to_template($this->internal, $ticket_entry, array('prefix','id'));

					$newly_updated = '';
					
					// Check if ticket was updated before user's last visit

					if(isset($ticket['post_modified']) AND strtotime($ticket['post_modified'])>$user_last_seen)
					{
						// Ticket was modified since user's last visit
						// Naming variable newly instead of recently because i just feel like it
						$newly_updated = ' '.$this->internal['prefix'].'user_updated_since_last_visit';					
					}
					
					$last_reply = $this->get_last_reply($ticket_id);

					
					
					if(isset($last_reply[0]['ticket_user']))
					{
						$ticket_user = $last_reply[0]['ticket_user'];
					}
					else
					{
						$ticket_user = false;
					}
						
				
		
					if($ticket_user==get_the_author_meta( 'ID' ))
					{
						$last_updated_by=$this->lang['label_you'];
					}
					else
					{
						$last_updated_by=$this->lang['label_support_team'];	
					}
					
			

					if($this->get_ticket_status($ticket_id)=='closed')
					{
						$button_label_view_or_resolved = $this->lang['ticket_resolved'];
					}	
					else
					{
						$button_label_view_or_resolved = $this->lang['button_label_view'];
						
					}				
					
						
					$vars = array(
					
						'ticket_link' => get_permalink(),
						'ticket_title' => get_the_title(),
						'user_ticket_last_updated_by' => $this->lang['user_ticket_last_updated_by'],
						'last_updated_by' => $last_updated_by,
						'newly_updated' => $newly_updated,
						'button_label_view_or_resolved' => $button_label_view_or_resolved,
						
					);
					
				
					$ticket_entry = $this->process_vars_to_template($vars, $ticket_entry);
						
					$tickets_content .= $ticket_entry;
					
				}
				
			
				wp_reset_postdata();
				
				$this->queue_content($tickets_content,'user_ticket_listing');
				
			
			}		
			else
			{
				$this->queue_notice($this->lang['ticket_listing_no_result'],'info','ticket_listing_no_result');
				
				$this->queue_content($tickets_content,'ticket_listing_no_result');
			}
		}
		
		
	
		// Queue content filters to show content :
		
		$this->append_template_var('plugin_content_placeholder',$tickets_content);
	
		return $tickets_content;
		
		
	}
	public function list_agent_assigned_tickets_p()
	{
		// Gets a list of user's tickets on user end

		global $current_user;
		

		// Do Ticket listing header
	
		$ticket_listing_header_template = $this->load_template('ticket_listing_header');
	
		$ticket_listing_header_template = $this->process_lang($ticket_listing_header_template);
		
		$ticket_listing_header_template = $this->process_vars_to_template($this->internal, $ticket_listing_header_template, array('prefix','id'));
		
		$help_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
		
		$url_vars = array(
					
			$this->internal['prefix'].'action' => 'list_agent_assigned_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_open_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			
	
		$url_vars = array(
					
			$this->internal['prefix'].'action' => 'list_agent_assigned_resolved_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_resolved_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			

		

		$vars = array(
		
			'list_open_tickets_link' => $list_open_tickets_link,
			'list_resolved_tickets_link' => $list_resolved_tickets_link,
			
		);
		
		$tickets_content = $this->process_vars_to_template($vars, $ticket_listing_header_template);		
		
		$args=array(
			'order_by' =>	'parent',
			'sort' =>	'DESC',
			'get_data_rows' =>	true,
			
		);		
		
		$tickets = $this->get_items_by_meta('assigned_rep',array($current_user->ID),'int','post',$args);
		
		if(count($tickets)>0 AND is_array($tickets))
		{
			$ticket_listing_entry_template = $this->load_template('agent_ticket_listing_entry');
			
			// Sort tickets according to their last update date
			usort($tickets, array(&$this, 'date_compare'));
			
			// Get user last seen time
			
			$user_last_seen = get_user_meta($current_user->ID,$this->internal['prefix'].'user_last_seen',true);
		
			foreach($tickets as $key => $value)
			{
				$ticket=$tickets[$key];
				
				// Skip closed tickets - this must be improved later to have the query take only closed tickets #IMPROVE
				
				if($this->get_ticket_status($ticket['ID'])=='closed')
				{
					continue;
				}	
												
					
				$ticket_entry = $this->process_lang($ticket_listing_entry_template);
				
				$ticket_entry = $this->process_vars_to_template($this->internal, $ticket_entry, array('prefix','id'));
		
				$ticket_departments = get_the_terms($ticket['ID'], $this->internal['prefix'].'support');
				
				$listed_departments='';
				if(is_array($ticket_departments))
				{
					foreach($ticket_departments as $key => $value)
					{
						 $listed_departments .= $ticket_departments[$key]->name.'&nbsp;';				
						
					}
				}
				$newly_updated = '';
				
				// Check if ticket was updated before user's last visit

				if(strtotime($ticket['post_modified'])>$user_last_seen)
				{
					// Ticket was modified since user's last visit
					// Naming variable newly instead of recently because i just feel like it
					$newly_updated = ' '.$this->internal['prefix'].'agent_updated_since_last_visit';					
				}
				

				$last_reply = $this->get_last_reply($ticket['ID']);

				$ticket_last_reply_user = $last_reply[0]['reply_user'];
				
				if($ticket_last_reply_user==$ticket['post_author'])
				{
					$last_updated_by=$this->lang['label_ticket_user'];
				}
				else
				{
					$last_updated_by=$this->lang['label_support_team'];	
				}	
				
			
				if($this->get_ticket_status($ticket['ID'])=='closed')
				{
					$button_label_view_or_resolved = $this->lang['ticket_resolved'];
				}	
				else
				{
					$button_label_view_or_resolved = $this->lang['button_label_view'];
					
				}

						
				$vars = array(
				
					'ticket_link' => get_permalink($ticket['ID']),
					'ticket_title' => get_the_title($ticket['ID']),
					'ticket_post_type_category_singular' =>$this->lang['ticket_post_type_category_singular'],
					'ticket_post_type_category_singular' =>$this->lang['ticket_post_type_category_singular'],
					'department_name' => $this->lang['ticket_post_type_category_singular'],
					'listed_departments' => $listed_departments,
					'newly_updated' => $newly_updated,
					'agent_ticket_last_updated_by' => $this->lang['agent_ticket_last_updated_by'],
					'last_updated_by' => $last_updated_by,
					'button_label_view_or_resolved' => $button_label_view_or_resolved,
					
				);
				
				
				
			
				$ticket_entry = $this->process_vars_to_template($vars, $ticket_entry);
			
				$tickets_content .= $ticket_entry;
				
			}
			
				
			$this->append_template_var('plugin_content_placeholder',$tickets_content);
				
		}
		else
		{

			$this->internal['frontend_errors']['ticket_listing_error_long'] = $this->lang['ticket_post_type_not_found'];
						
			
		}
		

		$this->queue_content_filters();
	
		return $tickets_content;
			
		
	}
	public function list_all_tickets_admin_p($v1=false)
	{
		// Gets a list of user's tickets on user end

		global $current_user;
		
		$request = $v1;
		if(isset($request[$this->internal['prefix'].'department_id']))
		{
			$department_id = $request[$this->internal['prefix'].'department_id'];
		}
		else
		{
			$department_id = '';
		}
		if($department_id =='' AND !($this->is_user_wp_admin($current_user->ID) OR $this->is_user_support_admin($current_user->ID)))
		{
	
			$this->queue_notice($this->lang['you_dont_have_permission_for_this_action'],'error','you_dont_have_permission_for_this_action');
			
			return false;		
			
		}
		
		// Do Ticket listing header
	
		$ticket_listing_header_template = $this->load_template('ticket_listing_header');
	
		$ticket_listing_header_template = $this->process_lang($ticket_listing_header_template);
		
		$ticket_listing_header_template = $this->process_vars_to_template($this->internal, $ticket_listing_header_template, array('prefix','id'));
		
		$help_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
		
		$url_vars = array(
					
			$this->internal['prefix'].'action' => 'list_all_tickets_admin',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_all_tickets_admin = add_query_arg(
			$url_vars,		
			$help_desk_url
		);
	
		$url_vars = array(
					
			$this->internal['prefix'].'action' => 'list_all_resolved_tickets_admin',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_all_resolved_tickets_admin = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			
		
		if(!isset($list_open_tickets_link))
		{
			
			$list_open_tickets_link='';
		}
		if(!isset($list_open_tickets_link))
		{
			
			$list_all_resolved_tickets_admin='';
		}
	
		$vars = array(
		
			'list_open_tickets_link' => $list_open_tickets_link,
			'list_resolved_tickets_link' => $list_all_resolved_tickets_admin,
			
		);
		
		$tickets_content = $this->process_vars_to_template($vars, $ticket_listing_header_template);		
		
		if(!isset($paged))
		{
			$paged=false;
		}

		$args = array(
			'post_type' => $this->internal['prefix'].'ticket',
			'paged' => $paged,
			'posts_per_page' => -1,
			'order' => 'DESC',
			'orderby' => 'modified',
		);		
		
		// If a department was requested, query only that. 
		if($department_id!='')
		{
			$args['tax_query'] = array(
				array(
					'taxonomy' => $this->internal['prefix'].'support',
					'field' => 'term_id',
					'terms' => $department_id,
				),
			);
		}
		
		
		$tickets = new WP_Query( $args );
		
		if (is_wp_error($tickets)) {
			$this->internal['frontend_errors']['ticket_listing_error_long'] = $tickets->get_error_message();
			$this->internal['frontend_errors']['ticket_listing_error'] = $tickets->get_error_message();
		} 
		else
		{
		
			if( $tickets->have_posts() ) {
				
				$ticket_listing_entry_template = $this->load_template('user_ticket_listing_entry');
				
				while( $tickets->have_posts()) {
					
					$tickets->the_post();
					// title, content, etc
				
					$ticket_id = get_the_ID();
					
					
					
					$department = wp_get_post_terms( $ticket_id, $this->internal['prefix'].'support' );
		
					// Skip if user is not super admin or is not support admin in this department:
					
					if(!($this->is_user_wp_admin($current_user->ID) OR (isset($department[0]) AND is_object($department[0]) AND $this->is_user_department_admin($department[0]->term_id,$current_user->ID))))
					{
						continue;						
						
					}
	
					// Skip closed tickets - this must be improved later to have the query take only open tickets #IMPROVE
					
					if($this->get_ticket_status($ticket_id)=='closed')
					{
						continue;
					}	
					else
					{
						$button_label_view_or_resolved = $this->lang['button_label_view'];
						
					}
											
					
					$ticket_entry = $this->process_lang($ticket_listing_entry_template);
					
					$ticket_entry = $this->process_vars_to_template($this->internal, $ticket_entry, array('prefix','id'));

					$newly_updated = '';
					
					// Check if ticket was updated before user's last visit

					if(isset($ticket) AND strtotime($ticket['post_modified'])>$user_last_seen)
					{
						// Ticket was modified since user's last visit
						// Naming variable newly instead of recently because i just feel like it
						$newly_updated = ' '.$this->internal['prefix'].'user_updated_since_last_visit';					
					}
					
					$last_reply = $this->get_last_reply($ticket_id);

					$ticket_last_reply_user = $last_reply[0]['reply_user'];
					
					$post_author_id = get_post_field( 'post_author', $ticket_id );
		
			
					if($ticket_last_reply_user==$post_author_id)
					{
						$last_updated_by=$this->lang['label_you'];
					}
					else
					{
						$last_updated_by=$this->lang['label_support_team'];	
					}
					
			

					if($this->get_ticket_status($ticket_id)=='closed')
					{
						$button_label_view_or_resolved = $this->lang['ticket_resolved'];
					}	
					else
					{
						$button_label_view_or_resolved = $this->lang['button_label_view'];
						
					}				
					
						
					$vars = array(
					
						'ticket_link' => get_permalink(),
						'ticket_title' => get_the_title(),
						'user_ticket_last_updated_by' => $this->lang['user_ticket_last_updated_by'],
						'last_updated_by' => $last_updated_by,
						'newly_updated' => $newly_updated,
						'button_label_view_or_resolved' => $button_label_view_or_resolved,
						
					);
					
				
					$ticket_entry = $this->process_vars_to_template($vars, $ticket_entry);
						
					$tickets_content .= $ticket_entry;
					
				}
				
			
				wp_reset_postdata();
				
				$this->queue_content($tickets_content,'user_ticket_listing');
				
			
			}		
			else
			{
				$this->queue_notice($this->lang['ticket_listing_no_result'],'info','ticket_listing_no_result');
				
				$this->queue_content($tickets_content,'ticket_listing_no_result');
			}
		}
		
		// Queue content filters to show content :
		
		$this->append_template_var('plugin_content_placeholder',$tickets_content);
	
		$this->queue_content_filters();
	
		return $tickets_content;
		
		
	}
	public function list_all_resolved_tickets_admin_p()
	{
		// Gets a list of user's tickets on user end

		global $current_user;
		

		if((isset($department_id) AND !($this->is_user_wp_admin($current_user->ID) OR $this->is_user_support_admin($current_user->ID))) OR (isset($department_id) AND !($this->is_user_department_admin($department_id,$current_user->ID) OR $this->is_user_wp_admin($current_user->ID))))
		{
	
			$this->queue_notice($this->lang['you_dont_have_permission_for_this_action'],'error','you_dont_have_permission_for_this_action','admin');
			
			return false;		
			
		}
		
		// Do Ticket listing header
	
		$ticket_listing_header_template = $this->load_template('ticket_listing_header');
	
		$ticket_listing_header_template = $this->process_lang($ticket_listing_header_template);
		
		$ticket_listing_header_template = $this->process_vars_to_template($this->internal, $ticket_listing_header_template, array('prefix','id'));
		
		$help_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
		
		$url_vars = array(
					
			$this->internal['prefix'].'action' => 'list_all_tickets_admin',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_all_tickets_admin = add_query_arg(
			$url_vars,		
			$help_desk_url
		);
	
		$url_vars = array(
					
			$this->internal['prefix'].'action' => 'list_all_resolved_tickets_admin',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_all_resolved_tickets_admin = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			


		$vars = array(
		
			'list_open_tickets_link' => $list_all_tickets_admin,
			'list_resolved_tickets_link' => $list_all_resolved_tickets_admin,
			
		);
		
		$tickets_content = $this->process_vars_to_template($vars, $ticket_listing_header_template);		
		
		$paged = isset($paged) ? $paged : false;

		$args = array(
			'post_type' => $this->internal['prefix'].'ticket',
			'paged' => $paged,
			'posts_per_page' => -1,
			'order' => 'DESC',
			'orderby' => 'modified',
		);		
		
		$tickets = new WP_Query( $args );
		
		if (is_wp_error($tickets)) {
			$this->internal['frontend_errors']['ticket_listing_error_long'] = $tickets->get_error_message();
			$this->internal['frontend_errors']['ticket_listing_error'] = $tickets->get_error_message();
		} 
		else
		{
		
			if( $tickets->have_posts() ) {
				
				$ticket_listing_entry_template = $this->load_template('user_ticket_listing_entry');
				
				while( $tickets->have_posts()) {
					
					$tickets->the_post();
					// title, content, etc
				
					$ticket_id = get_the_ID();
					
	
					
					$department = wp_get_post_terms( $ticket_id, $this->internal['prefix'].'support' );
					
					// Skip if user is not super admin or is not support admin in this department:
					
					if(!($this->is_user_wp_admin($current_user->ID) OR $this->is_user_department_admin($department[0]->term_id,$current_user->ID)))
					{
						continue;						
					}					
					
					// Skip closed tickets - this must be improved later to have the query take only open tickets #IMPROVE
					
					if($this->get_ticket_status($ticket_id)=='open')
					{
						continue;
					}	
											
					
					$ticket_entry = $this->process_lang($ticket_listing_entry_template);
					
					$ticket_entry = $this->process_vars_to_template($this->internal, $ticket_entry, array('prefix','id'));

					$newly_updated = '';
					
					// Check if ticket was updated before user's last visit


					$user_last_seen = get_user_meta($current_user->ID,$this->internal['prefix'].'user_last_seen',true);
		
					if(strtotime($tickets->post->post_modified)>$user_last_seen)
					{
						// Ticket was modified since user's last visit
						// Naming variable newly instead of recently because i just feel like it
						$newly_updated = ' '.$this->internal['prefix'].'user_updated_since_last_visit';					
					}
					
					$last_reply = $this->get_last_reply($ticket_id);

					$ticket_last_reply_user = $last_reply[0]['reply_user'];
					
					$post_author_id = get_post_field( 'post_author', $ticket_id );
		
			
					if($ticket_last_reply_user==$post_author_id)
					{
						$last_updated_by=$this->lang['label_ticket_user'];
					}
					else
					{
						$last_updated_by=$this->lang['label_support_team'];	
					}
					
			

					if($this->get_ticket_status($ticket_id)=='closed')
					{
						$button_label_view_or_resolved = $this->lang['ticket_resolved'];
					}	
					else
					{
						$button_label_view_or_resolved = $this->lang['button_label_view'];
						
					}				
					
						
					$vars = array(
					
						'ticket_link' => get_permalink(),
						'ticket_title' => get_the_title(),
						'user_ticket_last_updated_by' => $this->lang['user_ticket_last_updated_by'],
						'last_updated_by' => $last_updated_by,
						'newly_updated' => $newly_updated,
						'button_label_view_or_resolved' => $button_label_view_or_resolved,
						
					);
					
				
					$ticket_entry = $this->process_vars_to_template($vars, $ticket_entry);
						
					$tickets_content .= $ticket_entry;
					
				}
				
			
				wp_reset_postdata();
				
				$this->queue_content($tickets_content,'user_ticket_listing');
				
			
			}		
			else
			{
				$this->queue_notice($this->lang['ticket_listing_no_result'],'info','ticket_listing_no_result');
				
				$this->queue_content($tickets_content,'ticket_listing_no_result');
			}
		}
		
		
	
		// Queue content filters to show content :
		
		$this->append_template_var('plugin_content_placeholder',$tickets_content);
	
		$this->queue_content_filters();
	
	
		return $tickets_content;
		

		
	
			
		
	}
	public function list_agent_assigned_resolved_tickets_p()
	{
		// Gets a list of user's tickets on user end

		global $current_user;
		

		// Do Ticket listing header
	
		$ticket_listing_header_template = $this->load_template('ticket_listing_header');
	
		$ticket_listing_header_template = $this->process_lang($ticket_listing_header_template);
		
		$ticket_listing_header_template = $this->process_vars_to_template($this->internal, $ticket_listing_header_template, array('prefix','id'));
		
		$help_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
		
		$url_vars = array(
					
						$this->internal['prefix'].'action' => 'list_agent_assigned_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_open_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			
	
		$url_vars = array(
					
						$this->internal['prefix'].'action' => 'list_agent_assigned_resolved_tickets',
						'cb_plugin' => $this->internal['id'],
					
		);
		
		$list_resolved_tickets_link = add_query_arg(
			$url_vars,		
			$help_desk_url
		);			

		

		$vars = array(
		
			'list_open_tickets_link' => $list_open_tickets_link,
			'list_resolved_tickets_link' => $list_resolved_tickets_link,
			
		);
		
		$tickets_content = $this->process_vars_to_template($vars, $ticket_listing_header_template);		
		
		$args=array(
			'order_by' =>	'parent',
			'sort' =>	'DESC',
			'get_data_rows' =>	true,
			
		);		
		
		$tickets = $this->get_items_by_meta('assigned_rep',array($current_user->ID),'int','post',$args);
		
		if(count($tickets)>0 AND is_array($tickets))
		{
			$ticket_listing_entry_template = $this->load_template('agent_ticket_listing_entry');
			
			// Sort tickets according to their last update date
			usort($tickets, array(&$this, 'date_compare'));
			
			// Get user last seen time
			
			$user_last_seen = get_user_meta($current_user->ID,$this->internal['prefix'].'user_last_seen',true);
		
			foreach($tickets as $key => $value)
			{
				$ticket=$tickets[$key];
				
				// Skip closed tickets - this must be improved later to have the query take only open tickets #IMPROVE
				
				if($this->get_ticket_status($ticket['ID'])=='open')
				{
					continue;
				}	
												
					
				$ticket_entry = $this->process_lang($ticket_listing_entry_template);
				
				$ticket_entry = $this->process_vars_to_template($this->internal, $ticket_entry, array('prefix','id'));
		
				$ticket_departments = get_the_terms($ticket['ID'], $this->internal['prefix'].'support');
				
				$listed_departments='';
				
				foreach($ticket_departments as $key => $value)
				{
					 $listed_departments .= $ticket_departments[$key]->name.'&nbsp;';				
					
				}
				
				$newly_updated = '';
				
				// Check if ticket was updated before user's last visit

				if(strtotime($ticket['post_modified'])>$user_last_seen)
				{
					// Ticket was modified since user's last visit
					// Naming variable newly instead of recently because i just feel like it
					$newly_updated = ' '.$this->internal['prefix'].'agent_updated_since_last_visit';					
				}
				

				$last_reply = $this->get_last_reply($ticket['ID']);
				
			

				$ticket_user = $last_reply[0]['reply_user'];
				

				if($ticket_user==$ticket['post_author'])
				{
					$last_updated_by=$this->lang['label_ticket_user'];
				}
				else
				{
					$last_updated_by=$this->lang['label_support_team'];	
				}	
				
			
				if($this->get_ticket_status($ticket['ID'])=='closed')
				{
					$button_label_view_or_resolved = $this->lang['ticket_resolved'];
				}	
				else
				{
					$button_label_view_or_resolved = $this->lang['button_label_view'];
					
				}

						
				$vars = array(
				
					'ticket_link' => get_permalink($ticket['ID']),
					'ticket_title' => get_the_title($ticket['ID']),
					'ticket_post_type_category_singular' =>$this->lang['ticket_post_type_category_singular'],
					'ticket_post_type_category_singular' =>$this->lang['ticket_post_type_category_singular'],
					'department_name' => $this->lang['ticket_post_type_category_singular'],
					'listed_departments' => $listed_departments,
					'newly_updated' => $newly_updated,
					'agent_ticket_last_updated_by' => $this->lang['agent_ticket_last_updated_by'],
					'last_updated_by' => $last_updated_by,
					'button_label_view_or_resolved' => $button_label_view_or_resolved,
					
				);
				
				
				
			
				$ticket_entry = $this->process_vars_to_template($vars, $ticket_entry);
			
				$tickets_content .= $ticket_entry;
				
			}
			
				
			$this->append_template_var('plugin_content_placeholder',$tickets_content);
				
		}
		else
		{

			$this->internal['frontend_errors']['ticket_listing_error_long'] = $this->lang['ticket_post_type_not_found'];
						
			
		}
		

		$this->queue_content_filters();
	
		return $tickets_content;
			
		
	}
	public function queue_title_filters_p()
	{
		// This function is a wrapper for queueing content filter
		
		if(!isset($this->internal['title_filter_queued']))
		{
			$this->internal['title_filter_queued']=true;
			add_filter('the_title', array(&$this, 'title_filters'));		
		}
	}
	public function queue_content_filters_p()
	{
		// This function is a wrapper for queueing content filter
		
		if(!isset($this->internal['content_filter_queued']))
		{
			$this->internal['content_filter_queued']=true;
			add_filter('the_content', array(&$this, 'content_filters'));		
		}
	}
	public function queue_author_filter_p()
	{
		// This function is a wrapper for queueing author filter
		
		if(!$this->internal['the_author_filter_queued'])
		{
			$this->internal['the_author_filter_queued']=true;
			add_filter('the_author', array(&$this, 'the_author_filters'));		
		}
	}
	public function queue_get_the_date_filter_p()
	{
		// This function is a wrapper for queueing author filter
		
		if(!$this->internal['get_the_date_filter_queued'])
		{
			$this->internal['get_the_date_filter_queued']=true;
			add_filter('get_the_date', array(&$this, 'get_the_date_filters'));		
		}
	}
	public function edit_department_p($v1)
	{
		$request=$v1;
		
		$department=get_term( $_REQUEST[$this->internal['prefix'].'department'], $this->internal['prefix'].'support',ARRAY_A);
		
		if(is_array($department))
		{
			$_REQUEST[$this->internal['prefix'].'department_name']=$department['name'];
			$_REQUEST[$this->internal['prefix'].'department_description']=$department['description'];
			$_REQUEST[$this->internal['prefix'].'department']=$department['term_id'];
		}
	
		if(is_wp_error($department))
		{
			$this->queue_notice($this->lang['error_getting_department_details_failed'].'<br>'.$department->get_error_message(),'error','error_getting_department_details_failed','admin');

		}
		else
		{
			// print_r($result);
			// add_action( 'admin_notices', array(&$this,'admin_notice') );
		
		}		
		
	}
	public function choose_language_p($v1)
	{
		
		// Check if language was successfully changed and hook to create pages if necessary:
		if($this->internal['core_return'])
		{
			add_action( 'admin_init', array(&$this, 'check_create_pages'));			
		}
	}
	public function delete_department_p($v1)
	{
		$request=$v1;
		
		// User capability check here
		
		if(!current_user_can('manage_options'))
		{
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission','admin');
			return false;
		}			
	
		$result=wp_delete_term(
						$request[$this->internal['prefix'].'department'], // the term 
						$this->internal['prefix'].'support'// the taxonomy
		
					);					

		if(is_wp_error($result))
		{						
			$this->queue_notice($this->lang['error_department_operation_failed_at_db_stage'].'<br>'.$result->get_error_message(),'error','error_department_operation_failed_at_db_stage','admin');

		}
		else
		{
			$this->queue_notice($this->lang['success_department_operation_successful'],'success','success_department_operation_successful','admin');
					
			
		}
		
		
	}
	public function auto_assign_ticket_to_rep_p($v1)
	{		
		$ticket_id=$v1;
		
		// Get the department of the ticket:
		
		$departments_of_the_ticket = wp_get_post_terms( $ticket_id, $this->internal['prefix'].'support');
		
	
		// Use round robin :
		
		$assignment = $this->round_robin_assignment_method($ticket_id,$departments_of_the_ticket);
	
		$assigned_rep = $assignment['assigned_rep'];
		$from_department = $assignment['from_department'];
		$position = $assignment['position'];
	
		// Update ticket meta:
		
		$this->update_meta_by_item_id($ticket_id,'assigned_rep',$assigned_rep,'int','post');
		
		$this->opt['round_robin_assignments'][$from_department]['last_assigned']=$position;
			
		update_option($this->internal['prefix'].'options',$this->opt);		

		
	}
	public function round_robin_assignment_method_p($v1,$v2)
	{
	
		$ticket_id = $v1;
		$departments_of_the_ticket = $v2;
		
		// Iterate departments ticket is assigned to and try to assign by iterating departments to staff

		
		foreach($departments_of_the_ticket as $key => $value)
		{
			$current_department = $this->opt['departments_to_staff'][$departments_of_the_ticket[$key]->term_id];
			
			$department_id = $departments_of_the_ticket[$key]->term_id;

			if
			(
				is_array($current_department)
				AND
				count($current_department)>0
			)
			{
				
				
				if($this->opt['assign_tickets_to_admins']=='yes')
				{
					// Assigment to admins allowed, use entire department - ie do nothing
				
					$allowed_reps = array_keys($current_department);
					
				}
				else
				{
	
					// Only assign to reps - get support rep members:
					$allowed_reps=array_keys($current_department,'support_staff');
					
				}						
		
				if(count($allowed_reps)>0 AND is_array($allowed_reps))
				{
					// We have a rep set to use. all good
	
				}
				else
				{

					// Couldnt get any allowed reps. Break out of foreach to allow assignment to site admins:
					break;
					
				}
				// There are reps in this dep. Assign to them by finding the next one:
				
				if(isset($this->opt['round_robin_assignments'][$department_id]['last_assigned']))
				{
					$last_assigned = $this->opt['round_robin_assignments'][$department_id]['last_assigned'];
				}
				else
				{
					$last_assigned = 0;
				}

			
				if($last_assigned >= 0)
				{
					// We know the last assigned rep. Assign to next one:
					
					$next = $last_assigned + 1;
					
					// Check if the assigned number goes over the size of existing rep count.
					if($next > count($allowed_reps)-1)
					{
						// Yes, reset to zero and assign to the first one:
						
						$array_position = 0;
				
					}
					else
					{
						// No, assign to next rep:
					
						$array_position = $next;
						
					}
					
				
			
					
					$assigned_rep = $allowed_reps[$array_position];
				
					return array('assigned_rep'=>$assigned_rep,'from_department'=>$department_id,'position'=>$array_position);
					
				}
				else
				{
					// There is no last assignment. Assign to first rep:

				
					return array('assigned_rep'=>$allowed_reps[0],'from_department'=>$department_id,'position'=>0);
				
					
				}
				
			}
			
		}
		
		// If we are still here, it means we werent able to find any rep from any department to assign this ticket to. Then assign it to site admin:
		
		$users_query = new WP_User_Query( array( 
                'role' => 'administrator'
                ) );
		$results = $users_query->get_results();
		
		
		foreach($results as $user)
		{
		   // Assign to first super admin
			return array('assigned_rep'=>$user->ID,'from_department'=>$department_id,'position'=>0);
			
		}
	
	}
	public function get_last_reply_p($v1)
	{
		global $wpdb;
		
		$post_id = $v1;
		
		// Get the last reply from ticket replies table:
		
		$sql = "SELECT * FROM ".$wpdb->prefix.$this->internal['prefix']."reply WHERE reply_post = %d ORDER BY reply_id DESC LIMIT 1";
		
		$values_array = array(
		
			$post_id,
		
		
		);
		
		$prepared_sql = $wpdb->prepare(
		
						$sql,
						
						$values_array
			  
		);		
		
	
		$results = $wpdb->get_results($prepared_sql,ARRAY_A);
		
		
		if(count($results)>0)
		{		
			return $results;		
		}
		else
		{			
			return false;			
		}
	
	}
	public function get_reply_p($v1)
	{
		global $wpdb;
		
		$ticket_reply_id = $v1;
		
		// Get the last reply from ticket replies table:
		
		$sql = "SELECT * FROM ".$wpdb->prefix.$this->internal['prefix']."reply WHERE reply_id = %d";
		
		$values_array = array(
		
			$ticket_reply_id,
		
		
		);
		
		$prepared_sql = $wpdb->prepare(
		
						$sql,
						
						$values_array
			  
		);		
		
		
		$results = $wpdb->get_results($prepared_sql,ARRAY_A);
		
		if(count($results)>0)
		{		
			return $results;		
		}
		else
		{
			return false;			
		}
	
	}
	public function ticket_modified_ago_p($v1)
	{
		$post_modified_at = $v1;
	

		$ticket_modified_at_unix_time = time()-strtotime($post_modified_at);
		
		$minutes = floor(($ticket_modified_at_unix_time % 3600) / 60);
		$hours = floor(($ticket_modified_at_unix_time % 86400) / 3600);
		$days = floor(($ticket_modified_at_unix_time % 2592000) / 86400);	
		
		$modified = array(
			'days' => $days,
			'hours' => $hours,
			'minutes' => $minutes,
		
		
		);
		
		return $modified;
	
	}
	public function add_modify_department_p($v1)
	{
		$request = $v1;
		
		// User capability check here
		
		if(!current_user_can('manage_options'))
		{
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission','admin');
			return false;
		}	
		
		if($request['department_name']=='')
		{
			$this->queue_notice($this->lang['error_department_name_empty'],'error','error_department_name_empty','admin');
												
		}
		else
		{		
			if($request['department']!='')
			{
				$result=wp_update_term(
					$request['department'], // the term 
					$this->internal['prefix'].'support',// the taxonomy
					array(
					'name'=>$request['department_name'],
					'description'=>$request['department_description']
					)
				);	
			}
			else
			{
				$result=wp_insert_term(
					$request['department_name'], // the term 
						$this->internal['prefix'].'support',// the taxonomy
						array(
								'description'=>$request['department_description']
							)
					);
			}
			if($result AND is_wp_error($result))
			{
				$this->queue_notice($this->lang['error_department_operation_failed_at_db_stage'].'<br>'.$result->get_error_message(),'error','error_department_operation_failed_at_db_stage','admin');

			
			}
			else
			{
				$this->queue_notice($this->lang['success_department_operation_successful'],'success','success_department_operation_successful','admin');
					
			}

		if($_REQUEST['department']!='')
		{
			$_REQUEST['department']='';
		
		}
		if($_REQUEST['department_name']!='')
		{
			$_REQUEST['department_name']='';
		
		}

		if($_REQUEST['department_description']!='')
		{
			$_REQUEST['department_description']='';
		
		}			
			
		}
	}
	
	public function modify_htaccess_p($rules)
	{
		
		$plugin_rules = '
# BEGIN cb_p3 Protect Attachments
<IfModule mod_rewrite.c>
	RewriteCond %{REQUEST_FILENAME} -s
	RewriteRule ^'.$this->internal['attachment_relative_url'].'(.*)$ index.php?cb_p3_action=serve_attachment&attachment=$1 [QSA,L]
</IfModule>
# END  cb_p3 Protect Attachments';		

		return $rules = $rules.$plugin_rules;
	}	
	public function serve_attachment()
	{
		// Get the ticket post id from attachment name:
		
		$attachment = $_REQUEST['attachment'];
		
		$name_string = explode('_',$attachment);
		
		$post_id = $name_string[0];
		
		// Get post :
		
		$post = get_post($post_id);
		
		if($post)
		{
			// Valid ticket. Check permissions for the viewer:

			if(!$this->check_ticket_viewing_permissions($post))
			{		
	
				wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
				exit;
			}	
			// User has rights to view ticket. serve the attachment:
			
			$file = $this->internal['attachments_dir'].$attachment;
			
		
			$mime = wp_check_filetype($file);
		
			
			if( false === $mime[ 'type' ] && function_exists( 'mime_content_type' ) )
				$mime[ 'type' ] = mime_content_type( $file );
			if( $mime[ 'type' ] )
				$mimetype = $mime[ 'type' ];
			else
				$mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );
			header( 'Content-Type: ' . $mimetype ); // always send this
			if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) )
				header( 'Content-Length: ' . filesize( $file ) );
			
			readfile( $file );
			exit; 
		}
		
		
	}
	public function insert_attachment_entry_p($v1,$v2,$v3)
	{
		$reply_id = $v1;
		$attachment_name = $v2;
		$original_name = $v3;
		
		$reply = $this->get_single('reply',$reply_id);

		$post = get_post($reply['reply_post']);
		
		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{		

			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}
		
		$current_user=wp_get_current_user();
	
		$args = array(
				'attachment_post'	=> $post->ID,
				'attachment_parent'	=> $reply_id,
				'attachment_slug'    => $post->post_name,
				'attachment_status'    => 1,
				'attachment_created'    => date("Y-m-d H:i:s", time()),
				'attachment_modified'    => date("Y-m-d H:i:s", time()),
				'attachment_title'    => sanitize_title($original_name,'save'),
				'attachment_user'    => $current_user->ID,
				'attachment_group'    => 1,
				'attachment_content'    => $attachment_name,
			);			
		
		
		return $this->insert_single('attachment',$args);		
		
		
	}
	public function save_attachment_p($v1,$v2,$v3=false)
	{
		$reply_id = $v1;
	
		$attachment_name = $v2;
		$post_type = $v3;
		
		if($post_type=='' OR !$post_type)
		{
			$post_type = 'ticket';			
		}
		
		$current_user=get_current_user();

		$path = $_FILES[$this->internal['prefix'].'ticket_attachment']['name'];
	
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		
		$reply = $this->get_single('reply',$reply_id);
		
		$post = get_post($reply['reply_post']);
		

		// user permission check here
		
		if(!$this->check_ticket_viewing_permissions($post))
		{		

			wp_redirect( get_permalink($this->opt['pages']['no_permission_to_view_ticket_page']) );
			exit;
		}		

		// Make a name for the attachment:
		
		$attachment_new_name = $post->ID.'_'.$reply_id.'_'.time().'_'.rand(10,1000).'.'.$ext;
				
		$move_file = move_uploaded_file($_FILES[$this->internal['prefix'].'ticket_attachment']['tmp_name'], $this->internal['attachments_dir'].$attachment_new_name);
		
		if($move_file)
		{
			// File moved, create db entry:
			
					
			
			$insert_entry = $this->insert_attachment_entry($reply_id,$attachment_new_name,$_FILES[$this->internal['prefix'].'ticket_attachment']['name']);
			
			if($insert_entry)
			{
				return true;
					
			}
			else
			{
				return false;
				
			}
			
			
		}
		else
		{
			return false;			
			
		}
		
	}
	public function get_reply_attachments_p($v1,$v2=false)
	{
		global $wpdb;
		
		$reply_id = $v1;
		$type = $v2;
		
	
		// Ticket 
		$type = 'attachment';
		
		$results = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix.$this->internal['id']."_".$type." WHERE ".$type."_parent = '".$reply_id."'", ARRAY_A );
			
	
		if(count($results)>0)
		{		
			return $results;		
		}
		else
		{
			return false;			
		}
		
	}
	public function format_attachment_insert_p($v1)
	{
		$attachments = $v1;
			
		
		
	}
	public function filter_next_prev_post_link_p( $v1, $v2 ) 
	{
		global $post;
		
		$format = $v1;
		$link = $v2;

		if($post->post_type == $this->internal['prefix'].'ticket')
		{
			return false;
		}
		return $format;
	}
	public function is_user_department_admin_p( $v1,$v2) 
	{
		global $post;
		
		$department_id = $v1;
		$user_id = $v2;
		
		if(isset($this->opt['departments_to_staff'][$department_id][$user_id]) AND $this->opt['departments_to_staff'][$department_id][$user_id]=='support_admin')
		{
			return true;			
		}
		
		return false;
		
	}
	public function is_user_support_admin_p( $user_id) 
	{
		// Checks if user is a support admin in any department
		
		global $post;
		
	
		foreach($this->opt['departments_to_staff'] as $key => $value)
		{
			if(isset($this->opt['departments_to_staff'][$key][$user_id]))
			{
				if($this->opt['departments_to_staff'][$key][$user_id]=='support_admin')
				{
					$departments[]=$key;
				}
			}
			
			
		}
		
		if(isset($departments))
		{
			if(is_array($departments) and count($departments)>0)
			{
				return $departments;			
			}
			else
			{
				return false;			
			}
		}
		else
		{
			return false;			
		}
		
		
	}
	public function is_user_department_agent_p( $v1, $v2 ) 
	{
		global $post;
		
		$department_id = $v1;
		$user_id = $v2;
		
		if(isset($this->opt['departments_to_staff'][$department_id][$user_id]) AND $this->opt['departments_to_staff'][$department_id][$user_id]=='support_staff')
		{
			return true;			
		}
		
		return false;
			
	}
	public function is_user_support_agent_p( $v1 ) 
	{
		// Checks if user is support agent in any department
		
		$user_id = $v1;
	
		foreach($this->opt['departments_to_staff'] as $key => $value)
		{
			if(isset($this->opt['departments_to_staff'][$key][$user_id]))
			{
				if($this->opt['departments_to_staff'][$key][$user_id]=='support_staff')
				{
					return true;			
				}	
			}			
			
		}		
		return false;
			
	}
	public function get_support_agents_p( $v1 =false) 
	{
		// Checks if user is support agent in any department
		
		$department = $v1;
		
		
		if($department)
		{
			
			foreach($this->opt['departments_to_staff'][$department] as $user => $user_role)
			{
				if($this->opt['departments_to_staff'][$department][$user]=='support_staff')
				{
					$agents[$user]='support_staff';
				}
			}				
			return $agents;
		}
		
		
		foreach($this->opt['departments_to_staff'] as $key => $value)
		{
			foreach($this->opt['departments_to_staff'][$key] as $user => $user_role)
			{
				if($this->opt['departments_to_staff'][$key][$user]=='support_staff')
				{
					$agents[$user]='support_staff';
				}
			}			
			
		}		
		return $agents;
			
	}
	public function get_support_admins_p( $v1 =false) 
	{
		// Checks if user is support agent in any department
		
		$department = $v1;
		
		
		if($department)
		{
			
			foreach($this->opt['departments_to_staff'][$department] as $user => $user_role)
			{
				if($this->opt['departments_to_staff'][$department][$user]=='support_admin')
				{
					$agents[$user]='support_admin';
				}
			}				
			return $agents;
		}
		
		
		foreach($this->opt['departments_to_staff'] as $key => $value)
		{
			foreach($this->opt['departments_to_staff'][$key] as $user => $user_role)
			{
				if($this->opt['departments_to_staff'][$key][$user]=='support_admin')
				{
					$agents[$user]='support_admin';
				}
			}			
			
		}		
		return $agents;
			
	}
	public function get_ticket_status_p( $v1 ) 
	{
				
		$ticket_id = $v1;

		$ticket_status = $this->get_item_meta($ticket_id,'ticket_status','text','post',true);

		return $ticket_status;			
		
	}
	public function reply_attachments_p( $v1) 
	{
		$reply_id = $v1;
		
		$attachments = $this->get_reply_attachments($reply_id);
	
		if($attachments)
		{
			$attachments_insert = '<div class="'.$this->internal['prefix'].'reply_attachments">';

			foreach($attachments as $attachment)
			{
				// Lets display it if it is image:

				
				$path = $attachment['attachment_content'];
			
				$ext = pathinfo($path, PATHINFO_EXTENSION);
			
				
				if(in_array($ext,array('png','jpg','gif','tif','tiff','jpeg')))
				{
					$attachments_insert.='<a href="'.$this->internal['attachment_url'].$attachment['attachment_content'].'" target="_blank"><img src="'.$this->internal['attachment_url'].$attachment['attachment_content'].'" /></a><br>';
				}
				else
				{
					$attachments_insert.='<a href="'.$this->internal['attachment_url'].$attachment['attachment_content'].'" target="_blank">'.$attachment['attachment_title'].'</a><br>';
					
				}
								
			}
			
			$attachments_insert .= '</div>';
		}
		else
		{
			
			$attachments_insert = '';
			
		}
		
		return $attachments_insert;
	}
	public function change_ticket_department_p($v1)
	{
		
		$request=$v1;
		
		$current_user = wp_get_current_user();
		
		$post = get_post($request[$this->internal['prefix'].'ticket_id']);
		
		if(!($this->is_user_department_admin($request[$this->internal['prefix'].'department_id'],$current_user->ID) OR $this->is_user_department_agent($request[$this->internal['prefix'].'department_id'],$current_user->ID) OR $this->is_user_wp_admin($current_user->ID)))
		{
			
			// User doesnt have enough perms. put out error and return
			
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			
			$this->append_template_var('plugin_content_placeholder',$return_message);
			
			return false;
			
		}
		
		
		$department_form = $this->load_template('change_ticket_department_form');
		
		$department_form = $this->process_lang($department_form);
			
		// Process the internal ids and replacements
			
		$department_form = $this->process_vars_to_template($this->internal, $department_form, array('prefix','id'));
		
		$department_select_values=$this->make_department_select();
		
		
		$help_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
		$vars=array(
		
			'help_desk_page_url' => $help_desk_url,
			'departmentselect' => $department_select_values,
			'ticket_id' => $request[$this->internal['prefix'].'ticket_id'],
		
		);
		
			
		$department_form = $this->process_vars_to_template($vars, $department_form);
	
		
		
		$this->append_template_var('plugin_content_placeholder',$department_form);
				
		
		
	}
	public function change_ticket_department_confirm_p($v1)
	{
		
		$request=$v1;
		
		$current_user = wp_get_current_user();
		
		$post = get_post($request[$this->internal['prefix'].'ticket_id']);
		
		// Get current departments of the ticket:
		
		$departments_of_the_ticket = wp_get_post_terms( $request[$this->internal['prefix'].'ticket_id'], $this->internal['prefix'].'support');
		
		// Get ticket department id :

		$department_id = $departments_of_the_ticket[0]->term_id;
		
		// User must be either agent or admin in current department or a wp admin :
		
		if(!($this->is_user_department_admin($department_id,$current_user->ID) OR $this->is_user_wp_admin($current_user->ID) OR $this->is_user_department_agent($department_id,$current_user->ID)))
		{
			
			// User doesnt have enough perms. put out error and return
			
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			
			$this->append_template_var('plugin_content_placeholder',$return_message);
			
			return false;
			
		}
		
		// Change ticket's department:
		
		
		$department = array($request[$this->internal['prefix'].'department']);
		
		$department = array_map( 'intval', $department );

		$department = array_unique( $department );
		
		
		$result = wp_set_object_terms($request[$this->internal['prefix'].'ticket_id'],$department,$this->internal['prefix'].'support');
		

		if($result AND is_wp_error($result))
		{
			$this->queue_notice($this->lang['error_department_operation_failed_at_db_stage'].'<br>'.$result->get_error_message(),'error','error_department_operation_failed_at_db_stage');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			$this->append_template_var('plugin_content_placeholder',$return_message);
			
			return false;	
		
		}
		else
		{
			$this->queue_notice($this->lang['success_department_operation_successful'],'success','success_department_operation_successful');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			$this->append_template_var('plugin_content_placeholder',$return_message);	

			// Department changed. Reassign the ticket to a rep through round robin:
			
			$this->auto_assign_ticket_to_rep($request[$this->internal['prefix'].'ticket_id']);
			
			
			
			return true;
				
		}		
		
	
		
		
		
	}	
	public function reassign_ticket_p($v1)
	{
		
		$request=$v1;
		
		$current_user = wp_get_current_user();
		
		$post = get_post($request[$this->internal['prefix'].'ticket_id']);
		
		if(!($this->is_user_department_admin($request[$this->internal['prefix'].'department_id'],$current_user->ID) OR $this->is_user_wp_admin($current_user->ID)))
		{
			
			// User doesnt have enough perms. put out error and return
			
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			
			$this->append_template_var('plugin_content_placeholder',$return_message);
			
			return false;
			
		}
		
		
		$reassign_form = $this->load_template('ticket_reassign_form');
		
		$help_desk_url=get_permalink($this->opt['pages']['agent_desk_page']);
		
		$reassign_form = $this->process_lang($reassign_form);
			
		// Process the internal ids and replacements
			
		$reassign_form = $this->process_vars_to_template($this->internal, $reassign_form, array('prefix','id'));
		
		///////////////////		
		
		
		if($this->opt['assign_tickets_to_admins']=='yes')
		{
			
			$admins = $this->get_support_admins();
			
			foreach($admins as $key => $value)
			{
				
				$user = get_userdata($key);
				$admins[$key]=$user->display_name;
				
			}
			

			$agentselect = $this->make_select($admins,$this->internal['prefix'].'reassign_ticket_to');
		
			$vars=array(
			
				'help_desk_page_url' => $help_desk_url,
				'ticket_id' => $_REQUEST[$this->internal['prefix'].'ticket_id'],
				'select_agent' => $this->lang['select_admin_to_assign_ticket'],
				'agentselect' => $agentselect,
				
			);
			
			$reassign_to_admin_form = $this->process_vars_to_template($vars, $reassign_form);				
			
			$this->append_template_var('plugin_content_placeholder',$reassign_to_admin_form);
		
		
		}
		
		
		$agents = $this->get_support_agents();
		
		if(is_array($agents) AND count($agents)>0)
		{
			
			foreach($agents as $key => $value)
			{
				
				$user = get_userdata($key);
				$agents[$key]=$user->display_name;
				
			}
			
			$agentselect = $this->make_select($agents,$this->internal['prefix'].'reassign_ticket_to');
	
			
		}
		else
		{
			$agentselect = $this->lang['no_agents_found_in_department'];
			
		}
	
		
		
		$vars=array(
		
			'help_desk_page_url' => $help_desk_url,
			'ticket_id' => $_REQUEST[$this->internal['prefix'].'ticket_id'],
			'select_agent' => $this->lang['select_agent_to_assign_ticket'],
			'agentselect' => $agentselect,
			
		);
		
		$reassign_to_agent_form = $this->process_vars_to_template($vars, $reassign_form);		
		
		$this->append_template_var('plugin_content_placeholder',$reassign_to_agent_form);
				
		
		
	}
	public function reassign_ticket_confirm_p($v1)
	{
		
		$request=$v1;
		
		
		$current_user = wp_get_current_user();
		
		$post = get_post($request[$this->internal['prefix'].'ticket_id']);
		
		if(!((isset($request[$this->internal['prefix'].'department_id']) AND $this->is_user_department_admin($request[$this->internal['prefix'].'department_id'],$current_user->ID)) OR $this->is_user_wp_admin($current_user->ID)))
		{
			
			// User doesnt have enough perms. put out error and return
			
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			
			$this->append_template_var('plugin_content_placeholder',$return_message);
			
			return false;
			
		}
		
		// Now we are going to assign the ticket to selected agent:
		
		if($this->update_meta_by_item_id($request[$this->internal['prefix'].'ticket_id'],'assigned_rep',$request[$this->internal['prefix'].'reassign_ticket_to'],'int','post'))
		{
			

			$this->queue_notice($this->lang['success_assign_ticket'],'success','success_assign_ticket');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			$this->append_template_var('plugin_content_placeholder',$return_message);			
			
			return true;
		}
		else
		{
			
			$this->queue_notice($this->lang['error_failed_to_reassign_ticket'],'error','error_failed_to_reassign_ticket');
			
			$url=get_permalink($request[$this->internal['prefix'].'ticket_id']);
			
			$return_message=$this->lang['return_to_previous_page_message'];
			
			$return_message.='<br><br><a href="'.$url.'">'.$post->post_title.'</a>';
			
			
			$this->append_template_var('plugin_content_placeholder',$return_message);
			
			return false;			
			
		}
		
		
		
		
	}
	public function make_ticket_status_change_button_p($v1)
	{
		$ticket_id = $v1;
		
	
		
		$status_button = $this->load_template('ticket_status_changer_button');
		
		
		$ticket_url = get_permalink($ticket_id);

		if($this->get_ticket_status($ticket_id)=='open')
		{
			$change_ticket_status_label = $this->lang['ticket_status_change_to_closed'];
			
			$ticket_status = $this->lang['ticket_status_open'];		
			
			$action_url = add_query_arg( 
			array(
				$this->internal['prefix'].'ticket_id' => $ticket_id,
				$this->internal['prefix'].'action' => 'close_ticket',
			), 
			$ticket_url );
		}
	
		elseif($this->get_ticket_status($ticket_id)=='closed')
		{
			$change_ticket_status_label = $this->lang['ticket_status_change_to_open'];
			
			$ticket_status = $this->lang['ticket_status_closed'];
			
			$action_url = add_query_arg( 
			array(
				$this->internal['prefix'].'ticket_id' => $ticket_id,
				$this->internal['prefix'].'action' => 'reopen_ticket',
			), 
			$ticket_url );
			
		}
		else
		{
			$action_url='';
			$change_ticket_status_label ='';
			
		}
					
		$template_vars = array(
				'url' => $action_url,
				'change_ticket_status_label' => $change_ticket_status_label,
				
		);	
		
		$status_button = $this->process_vars_to_template($this->internal, $status_button,array('prefix'));	
		
		$status_button = $this->process_lang($status_button);
		
		$status_button = $this->process_vars_to_template($template_vars,$status_button);

		return $status_button;
		
		
	}
	public function check_for_update($checked_data) 
	{
			global $wp_version, $plugin_version, $plugin_base;
		
			if ( empty( $checked_data->checked ) ) {
				return $checked_data;
			}

			if(version_compare( $this->internal['version'], $checked_data->response[$this->internal['plugin_id'].'/index.php']->new_version, '<' ))
			{
				// place update link into update lang string :
				
				$update_link = $this->process_vars_to_template(array('plugin_update_url'=>$this->internal['plugin_update_url']),$this->lang['update_available']);

				$this->queue_notice($update_link,'info','update_available','perma',true);		
			}
			return $checked_data;
		
	}	
	public function upgrade_p($v1,$v2)
	{
		
		$upgrader_object = $v1;
		$options = $v2;
		
		if($upgrader_object->result['destination_name']!=$this->internal['plugin_id'])
		{
			return;			
		}
		
		if(!current_user_can('manage_options'))
		{
			$this->queue_notice($this->lang['error_operation_failed_no_permission'],'error','error_operation_failed_no_permission','admin');
			return false;
		}
		
		// Check if woocommerce is installed to give our message
		$this->check_woocommerce_exists();
		
		
		if($this->internal['woocommerce_installed'] AND $this->check_addon_exists('woocommerce_integration')=='notinstalled')
		{
			$this->queue_notice($this->lang['woocommerce_addon_available'],'info','update_available','perma',true);		
		}		
	
		$this->dismiss_admin_notice(array('notice_id'=>'update_available','notice_type'=>'info'));
		
	}
	public function do_setup_wizard_p()
	{
		// Here we do and process setup wizard if it is not done:
		
		if($_REQUEST['setup_stage']=='')
		{
			
			require($this->internal['plugin_path'].'plugin/includes/setup_1.php');
			
			$this->opt['setup_done'] = true;
			
			update_option($this->internal['prefix'].'options',$this->opt);		

		}
		else
		{
	
		
		}
		
		$this->internal['setup_is_being_done']=true;
		
	}
	public function display_addons_p()
	{
		// This function displays addons from internal vars
		echo '<div class="cb_addons_list">';
		foreach($this->internal['addons'] as $key => $value)
		{
			echo $this->display_addon($key);
			
		}
		echo '</div>';
		
		
	}
	public function display_addon_p($v1)
	{
		$addon_key=$v1;
		
		$addon=$this->internal['addons'][$addon_key];
		
		// This function displays a particular addon
	
		echo '<div class="cb_addon_listing">';	
		echo '<div class="cb_addon_icon"><a href="'.$this->internal['addons'][$addon_key]['link'].'" target="_blank"><img src="'.$this->internal['plugin_url'].'images/'.$addon['icon'].'" /></a></div>';echo '<div class="cb_addon_title"><a href="'.$this->internal['addons'][$addon_key]['link'].'" target="_blank">'.$this->lang['addon_'.$addon_key.'_title'].'</a></div>';		
		echo '<div class="cb_addon_status">'.$this->check_addon_status($addon_key).'</div>';
		echo '</div>';			
		
	}
	public function wrapper_check_addon_license_p($v1)
	{
		// Wrapper solely for the purpose of letting addons check their licenses
		return;
	}
	public function check_addon_status_p($v1)
	{
		// Checks addon status, license, and provides links if inecessary
		
		$addon_key = $v1;
		
		// Check if addon is active:
		
		if ( is_plugin_active( $this->internal['addons'][$addon_key]['slug'] ) ) 
		{
			//plugin is active
			
			echo $this->wrapper_check_addon_license($addon_key);
			
		}
		else
		{
			// Check if plugin exists:
			
			if(file_exists(WP_PLUGIN_DIR.'/'.$this->internal['addons'][$addon_key1]['slug']))
			{
				
				return $this->lang['inactive']; 
				
			}
			else			
			{
				// Not installed. 
				return '<a href="'.$this->internal['addons'][$addon_key]['link'].'" class="cb_get_addon_link" target="_blank">'.$this->lang['get_this_addon'].'</a>';
				
			}
			
		}
		
		
	}
	public function check_addon_exists_p($v1)
	{
		// Checks addon status, license, and provides links if inecessary
		
		$addon_key = $v1;
		
		// Check if addon is active:
		
		if ( is_plugin_active( $this->internal['addons'][$addon_key]['slug'] ) ) 
		{
			//plugin is active
			
			return 'active';
			
		}
		else
		{
			// Check if plugin exists:
			
			if(isset($addon_key1) AND file_exists(WP_PLUGIN_DIR.'/'.$this->internal['addons'][$addon_key1]['slug']))
			{
				
				return 'notinstalled';
				
			}
			else			
			{
				// Not installed. 
				return 'notinstalled';
				
			}
			
		}
		
		
	}
	public function open_ticket_single_department_redirect_check_p($v1,$v2)
	{
		// Checks if there is only one dep
		$redirect=$v1;
		$departments=$v2;
		
		// Create redirect url :
		

		$help_desk_url=get_permalink($this->opt['pages']['support_desk_page']);
		
		$create_ticket_vars = array(
		
			$this->internal['prefix'].'action' => 'ticket_form',
			'cb_plugin' => $this->internal['id'],
			$this->internal['prefix'].'department' => $departments[0]->term_id,
		
		);
		
		$create_ticket_url = add_query_arg(
			$create_ticket_vars,		
			$help_desk_url
		);			
		
		
		if(count($departments)==1 AND $redirect)
		{
			
			wp_redirect($create_ticket_url);
			
			exit;
			
		}	
			
		// Else return redirect url
		
		return $create_ticket_url;
		
	}
	public function check_woocommerce_exists_p($v1)
	{
		
		$active_plugins=get_option('active_plugins');
		
		if(in_array('woocommerce/woocommerce.php',$active_plugins))
		{
			$this->internal['woocommerce_installed']=true;
		}		
		
		
	}
	
}


$cb_p3 = cb_p3_plugin::get_instance();

function cb_p3_get()
{

	// This function allows any plugin to easily retieve this plugin object
	return cb_p3_plugin::get_instance();

}

?>