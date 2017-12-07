<?php


$this->opt = array_replace_recursive(

	$this->opt,

	array(
	
		'test_option' => 5,

			'quickstart'=> array(

				'site_account' => 'Delete this and enter your Site or your personal (admin) Patreon account here',
				'redirect_url'=>'',	
				'site_support_message'=>'Support {sitename} on Patreon!',	
				'force_site_button'=>'no',
			),
				
			'post_button'=> array(
							
				'show_button_under_posts'=>'yes',	
				'append_to_content_order'=>'99',	
				'show_message_over_post_button'=>'yes',
				'message_over_post_button_font_size'=>'24px',	
				'insert_text_align'=>'center',	
				'insert_margin'=>'15px',
				'message_over_post_button'=>'Liked it? Take a second to support {authorname} on Patreon!',
				'message_over_post_button_margin'=>'10px',
				'button_margin'=>'10px',
			),
			'sidebar_widgets'=> array(
					
				'insert_text_align'=>'center',	
				'message_font_size'=>'18px',	
				'message_over_post_button_margin'=>'10px',
				'button_margin'=>'10px',
			),
			'extras'=> array(

			),
			'support'=> array(
				
					
			),
			'departments_to_staff'=> array(
			
				
					
					
			),
	
			'roles'=>array(
				
				'support_admin' => array(
					'color' => '#ff0000', 
					'caps' => array(
						'close_ticket', 
						'edit_staff', 
						'open_ticket', 
						'reopen_ticket', 
						'assign_ticket', 
						'reply_ticket', 
						'edit_department', 
						'view_department', 
						'reply_ticket', 
						'view_staff'
						)
						
				),
			
				'support_staff' => array(
					'color' => '#6F6F6F', 
					'caps' => array(
						'close_ticket',
						'open_ticket',
						'reopen_ticket',
						'reply_ticket',
						'view_ticket'
						)
				),
		
			),
			
			'template'=> 'default',
			'assign_tickets_to_admins'=> 'yes',
			'send_ticket_update_email_notification_to_users'=> 'yes',
			'send_ticket_update_email_notification_to_staff'=> 'yes',
		)
		
		

);


?>