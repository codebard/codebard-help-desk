<?php


class cb_p3_dud_language_object {
	
	public $internal = array(

	// Holds internal and generated vars. Never saved.

	);
	public $opt = array(

	// Holds internal and generated vars. Never saved.

	);
	public $hardcoded = array(

	// Holds hardcoded vars. Never saved.

	);
	public $lang = array(

	// Holds hardcoded vars. Never saved.

	);
	
			
	public function __construct() 
	{
	


	
	}
	public function load_language($v1=false)
	{

		// Loads language from db.
		
		if(isset($this->opt['lang']))
		{
			$lang = $this->opt['lang'];
		}
		else
		{
			$lang = 'en-US';			
		}		
		
		$lang_file = __DIR__ . '/../../plugin/includes/languages/'.$lang.'.php';
		
		if(!file_exists($lang_file))
		{
			$lang='en-US';
			$this->opt['lang']='en-US';
			$this->update_opt();
			$lang_file = __DIR__ . '/../../plugin/includes/languages/'.$lang.'.php';
			
		}
		
		 
		// Get saved values in db:
		
		$language_values = get_option($this->internal['prefix'].'lang_'.$lang);
		
		include($lang_file);
		$this->lang=$lang;
			
		if(!is_array($language_values))
		{
			// Get the language from language file:

			
			$language_values = $this->lang;
			
		}
		

		
		$language_values = array_replace_recursive(
		
							$lang,
							$language_values
		);
	
		return array_map('stripslashes', $language_values);
			
	}
	public function update_opt()
	{
		// Does nothing but wrap update_options for options:
		
		return update_option($this->internal['prefix'].'options',$this->opt);		

		
	}
	public function load_internal_vars()
	{
		require_once(__DIR__ . '/../../core/includes/default_internal_vars.php');
		require_once(__DIR__ . '/../../plugin/includes/default_internal_vars.php');
		require_once(__DIR__ . '/../../plugin/includes/hardcoded_vars.php');
		
	}
}


class cb_p3_sidebar_user_widget extends WP_Widget {
	public $cb_p3 = '';	
    public function __construct() {

		global $cb_p3;
		
		if( !isset( $cb_p3 ) ) {
			// If plugin is not initialized, we may be in wp-cli or some other tool that accessed this file without initiating this plugin. Use a dud object to replace it:
			
			$this->cb_p3 = new cb_p3_dud_language_object();
			// Get options 
			$this->cb_p3->internal['prefix']='cb_p6';
			$this->cb_p3->opt=get_option($this->cb_p3->internal['prefix'].'options');	
			$this->cb_p3->internal = $this->cb_p3->load_internal_vars();
		}
		else {
			$this->cb_p3=$cb_p3;			
		}

		// Load language from db
		$this->cb_p3->lang = $this->cb_p3->load_language();
		
        parent::__construct(
            'codebard_help_desk_sidebar_user_widget', // Base ID
             $this->cb_p3->lang['sidebar_user_widget_name'], // Name
            array( 'description' => $this->cb_p3->lang['sidebar_user_widget_name'] ) // Args
        );
    }
 
 
    /** @see WP_Widget::widget -- do not rename this */
    function widget($args, $instance) {	
	
		
		global $cb_p3;
        extract( $args );
        $title 		= apply_filters('widget_title', $instance['title']);
		  $message 	= $instance['message'];
        ?>
              <?php echo $before_widget; ?>
                                <?php if ( $title )
                        echo $before_title . $title . $after_title; ?>
							
								<?php 
									
								
								?>
									<div class="cb_p3_sidebar_user_widget_content">
									
									
										<button onclick="window.location.href='<?php echo get_permalink($this->cb_p3->opt['pages']['support_desk_page']) ?>';"><?php echo $this->cb_p3->lang['sidebar_user_widget_help_desk_button_label'] ?></button>
										

										
									
									</div>
								
								<?php 
									
								?>
							
						
              <?php echo $after_widget; ?>
        <?php
    }
 
    /** @see WP_Widget::update -- do not rename this */
    function update($new_instance, $old_instance) {		
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['message'] = strip_tags($new_instance['message']);
        return $instance;
    }
 
    /** @see WP_Widget::form -- do not rename this */
    function form($instance) {	
		global $cb_p3;
		$instance = wp_parse_args( (array) $instance, array( 'title' => $this->cb_p3->lang['sidebar_user_widget_title'] ) );
        $title 		= esc_attr($instance['title']);
        ?>
         <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>	
		
        <?php 
    }
	
}


function cb_p3_register_widgets()
{

	register_widget( 'cb_p3_sidebar_user_widget' );

}

add_action('widgets_init', 'cb_p3_register_widgets');


?>