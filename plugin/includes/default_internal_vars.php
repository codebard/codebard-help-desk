<?php

$this->internal = array_replace_recursive(
	
	
	$this->internal,
	
	array(
		
		'id' => 'cb_p3',
		'plugin_id' => 'codebard-help-desk',
		'prefix' => 'cb_p3_',
		'version' => '1.0.9',
		'plugin_name' => 'CodeBard Help Desk',
		
		'callable_from_request' => array(
			'get_tickets' => 1,
			'get_ticket' => 1,
			'ticket_department_form' => 1,
			'ticket_form' => 1,
			'open_ticket' => 1,
			'create_ticket' => 1,
			'list_tickets' => 1,
			'list_agent_assigned_tickets' => 1,
			'save_settings' => 1,
			'add_modify_department' => 1,
			'edit_department' => 1,
			'delete_department' => 1,
			'remove_staff_from_department' => 1,
			'add_staff_to_department' => 1,
			'reset_languages' => 1,
			'save_language' => 1,
			'choose_language' => 1,
			'serve_attachment' => 1,
			'close_ticket' => 1,
			'reopen_ticket' => 1,
			'reassign_ticket' => 1,
			'reassign_ticket_confirm' => 1,
			'change_ticket_department' => 1,
			'change_ticket_department_confirm' => 1,
			'change_ticket_department' => 1,
			'save_license' => 1,
			'delete_this_ticket' => 1,
			'list_resolved_tickets' => 1,
			'list_agent_assigned_resolved_tickets' => 1,
			'dismiss_admin_notice' => 1,
			'list_all_tickets_admin' => 1,
			'insert_quick_reply' => 1,
			'list_all_resolved_tickets_admin' => 1,
			'ignore the ones after this line they were allowed for development!'=>1,
			
		),
			
		
		'do_log' => false,
		
		'calllimits' => array(
		
			'add_admin_menu'=>1,
		),		
		
		'callcount' => array(
		
		),		
		
		'tables'=> array(

			'department' => '',
			'reply' => '',
			'attachment' => '',

		),	
		'data'=> array(

			'department' => array(
				'tables' => array(
					'department' => 'department',
				),
			
			),
			'reply' => array(
				'tables' => array(
					'reply' => 'reply',
				),
			),
			'attachment' => array(
				'tables' => array(
					'attachment' => 'attachment',
				),
			),

		),	
		

		'meta_tables'=> array(

			'int' => array(
						'type'=>'bigint',
						'length' => '22',
						'default' => '0',
						'structure'=>'',
						),
						
			'text' => array(
						'type'=>'varchar',
						'length' =>'255',
						'default' => 'NULL',
						'structure'=>'',
						),		
						
			'date' => array(
						'type'=>'datetime',
						'length' =>'',
						'structure'=>'',
						),
						
			'decimal' => array(
						'type'=>'decimal',
						'length' =>'26,6',
						'structure'=>'',
						),
								
			'longtext' => array(
						'type'=>'longtext',
						'length' =>'',
						'structure'=>'',
						),
						
		),	
		
		
		'ticket_statuses' => array(
		
			'open' => 'ticket_status_open',
			'closed' => 'ticket_status_closed',
			
		
		),
		
		
		'roles'=> array(
		
				'support_admin' => array(
							
											'label' =>'Support Admin', 
											
											'color' =>'#ff0000', 
											
											'capabilities'  => array(
											
																'close_ticket', 
																'open_ticket', 
																'reopen_ticket',  
																'reply_ticket', 
																'view_ticket',
																
																'assign_ticket',
																'view_staff',
																'edit_staff' 
															)
										),
				
				'support_staff' => array(
				
											'label' => 'Support Staff', 
											
											'color' => '#6F6F6F', 
											
											'capabilities'  => array(
											
																'close_ticket',  
																'open_ticket', 
																'reopen_ticket',  
																'reply_ticket', 
																'view_ticket'
															)
										),
					
				
		),
		
		
		'admin_tabs' => array(
		
			'dashboard'=>array(
				
			),
			'general'=>array(
				
				
			),
			'departments'=>array(
				
				
			),
			'staff'=>array(
				
				
			),
			'languages'=>array(
				
				
			),
			'addons'=>array(
				
				
				
			),
			'extras'=>array(
				
			
				
			),
			'support'=>array(
				
				
			),
		
		
		
		),
		
		'addons' => array(
		
			'woocommerce_integration' => array(
			
				'title' => '',
				'icon' => 'woocommerce_integration.jpg',		
				'link' => 'https://codebard.com/codebard-help-desk-woocommerce-integration',		
				'slug' => 'codebard-help-desk-woocommerce-integration/index.php',		
				'class_name' => 'cb_p3_a1',		
			
			),
		
		
		
		
		),
	
	)
	
);


?>