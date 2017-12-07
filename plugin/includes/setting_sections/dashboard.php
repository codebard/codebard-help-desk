<div style="display:block; margin-top : 20px;"></div>
<?php 
	$this->check_woocommerce_exists();
	if($this->internal['woocommerce_installed'] AND $this->check_addon_exists('woocommerce_integration')=='notinstalled')
	{
	?>
	
		<div class="cb_p3_setup_wizard_big_heading"><?php echo $this->lang['get_woocommerce_addon'];?></div>
		
	<div class="cb_p3_setup_wizard_small_heading" style="max-width: 600px;">
		<div style="display:table;"><div style="display:table-cell;width : 75px !important;vertical-align : top;padding-right : 10px;"><div class="cb_p3_img_div" style="display:table-cell;width : 75px !important;vertical-align : top;"><a href="https://codebard.com/codebard-help-desk-woocommerce-integration" target="_blank"><img src="<?php echo $this->internal['plugin_url']; ?>images/woo_logo.png"></a></div></div><div style="display:table-cell;vertical-align : top;"><?php echo $this->lang['woocommerce_addon_details_in_setup']; ?></div></div>
	</div>
	<br />
	<hr width="100%" />
	<?php
	}
	?>
	<div class="cb_p3_setup_wizard_one_col" style="text-align : center;font-size : 100%; max-width : 600px;">
	
			<button class="cb_p3_admin_button" onclick="window.open('https://codebard.com/codebard-help-desk-for-wordpress-quickstart-guide');" target="_blank"><?php echo $this->lang['setup_read_quickstart_guide'];?></button>
	
			<button class="cb_p3_admin_button" onclick="window.open('https://codebard.com/codebard-help-desk-for-wordpress-manual');" target="_blank"><?php echo $this->lang['setup_read_manual'];?></button>
	
			<button class="cb_p3_admin_button" onclick="window.open('<?php echo get_permalink($this->opt['pages']['support_desk_page']) ?>');" target="_blank"><?php echo $this->lang['setup_wizard_help_desk_page_label'];?></button>
			
			<button class="cb_p3_admin_button" onclick="window.open('<?php echo get_permalink($this->opt['pages']['agent_desk_page']) ?>');" target="_blank"><?php echo $this->lang['setup_wizard_agent_desk_page_label'];?></button>
		

	</div>
	<div class="cb_p3_setup_wizard_two_col" style="max-width : 600px;">
	
		<div class="cb_p3_setup_wizard_col_33" style="text-align : center; max-width : 600px;">
		
			<?php echo $this->lang['setup_wizard_follow_us_on_twitter'];?><br><br><a href="https://twitter.com/codebardcom" class="twitter-follow-button" data-show-count="false"><?php echo $this->lang['setup_wizard_twitter_follow_label_prefix'];?> @CodeBard</a><script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>
			
		</div>
	
		<div class="cb_p3_setup_wizard_col_33" style="text-align : center;">
		
			<?php echo $this->lang['setup_wizard_join_list']; ?>
			<br><br>
	
			<button class="cb_p3_admin_button" onclick="window.open('<?php echo $this->lang['newsletter_link'] ?>');" target="_blank"><?php echo $this->lang['setup_wizard_join_mailing_list_link_label'];?></button>
		</div>
		

		<div class="cb_p3_setup_wizard_col_33" style="text-align : center;">
		
			<a href="<?php echo $this->lang['tell_your_friends_tweet']; ?>" target="_blank"><?php echo $this->lang['tell_your_friends']; ?></a>
			<br><br>
	
		</div>
		

	</div>

