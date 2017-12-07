<?php



	// if uninstall.php is not called by WordPress, die
		if (!defined('WP_UNINSTALL_PLUGIN')) {
			die;
		}
	

		global $wpdb;
	


		// Create dud object for loading options and internal vars:
		
		class cb_dud_object {
			
			public $internal = array(
	
			// Holds internal and generated vars. Never saved.

			);
			public $opt = array(
	
			// Holds internal and generated vars. Never saved.

			);
			public $hardcoded = array(
	
			// Holds hardcoded vars. Never saved.

			);
			
					
			public function __construct() 
			{
			
				require_once('core/includes/default_internal_vars.php');
				require_once('plugin/includes/default_internal_vars.php');
				require_once('plugin/includes/hardcoded_vars.php');
					
			
			}
		}	
		$cb_p3 = new cb_dud_object;
		
		// Include internal vars from file:
		
		
		// Get options 
		
		$cb_p3->opt=get_option($cb_p3->internal['prefix'].'options');		

		if($cb_p3->opt['delete_options_on_uninstall']=='yes')
		{
			$wpdb->query( "DELETE FROM ".$wpdb->options." WHERE option_name LIKE '".$cb_p3->internal['id']."_%';");
		
		}
	
		if($cb_p3->opt['delete_data_on_uninstall']=='yes')
		{
			
			foreach($cb_p3->internal['tables'] as $key => $value)
			{
				$wpdb->query( "DROP TABLE IF EXISTS ".$wpdb->prefix.$cb_p3->internal['id']."_".$key.";");
				
			}
			foreach($cb_p3->internal['meta_tables'] as $key => $value)
			{
				
				$wpdb->query( "DROP TABLE IF EXISTS ".$wpdb->prefix.$cb_p3->internal['id']."_".$key.";");
				
			}
			
			// Remove wordpress posts
			
			// Get posts first:
	
			$results = $wpdb->get_results( "SELECT ID FROM ".$wpdb->posts." WHERE post_type = '".$cb_p3->internal['id']."_ticket';",ARRAY_A);
			
			foreach($results as $key => $value)
			{
				$post_id = $results[$key]['ID'];
				
				// Delete post meta
				
				$wpdb->query( "DELETE FROM ".$wpdb->postmeta." WHERE post_id = '".$post_id."';");	
				
				// Delete post 
				
				$wpdb->query( "DELETE FROM ".$wpdb->posts." WHERE ID = '".$post_id."';");	
				
				
			}
			
			// Delete custom taxonomy
						
			// Delete terms
			$wpdb->query( "
				DELETE FROM
				".$wpdb->terms."
				WHERE term_id IN
				( SELECT * FROM (
					SELECT ".$wpdb->terms.".term_id
					FROM ".$wpdb->terms."
					JOIN ".$wpdb->term_taxonomy."
					ON ".$wpdb->term_taxonomy.".term_id = ".$wpdb->terms.".term_id
					WHERE taxonomy = '".$cb_p3->internal['id']."_support'
				) as T
				);
			" );

			// Delete taxonomies
			$wpdb->query( "DELETE FROM ".$wpdb->term_taxonomy." WHERE taxonomy = '".$cb_p3->internal['id']."_support'" );

			
		}
		

?>