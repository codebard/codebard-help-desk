<?php


echo $this->do_admin_settings_form_header($tab);



echo '<h2>'.$this->lang['option_title_organization_name'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_org_name'].'</div>';
echo '<input type="text" name="opt[org_name]" value="'.$this->opt['org_name'].'">';

if(isset($this->opt['assign_tickets_to_admins']))
{
	if($this->opt['assign_tickets_to_admins']=='yes')
	{
		$assign_tickets_to_admins_checked_yes=" CHECKED";
		$assign_tickets_to_admins_checked_no="";
	}
	else
	{
		$assign_tickets_to_admins_checked_yes="";
		$assign_tickets_to_admins_checked_no=" CHECKED";
		
	}
}
else
{
	$assign_tickets_to_admins_checked_yes="";
	$assign_tickets_to_admins_checked_no=" CHECKED";
	
}


echo '<h2>'.$this->lang['option_title_assign_tickets_to_admins'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_assign_tickets_to_admins'].'</div>';

echo $this->lang['yes'].' <input type="radio" name="opt[assign_tickets_to_admins]" value="yes"'.$assign_tickets_to_admins_checked_yes.'>'.$this->lang['no'].' <input type="radio" name="opt[assign_tickets_to_admins]" value="no"'.$assign_tickets_to_admins_checked_no.'>';


if(isset($this->opt['send_email_notifications_to_users']))
{
	if($this->opt['send_email_notifications_to_users']=='yes')
	{
		$send_email_notifications_to_users_checked_yes=" CHECKED";
		$assign_tickets_to_admins_checked_no="";
	}
	else
	{
		$send_email_notifications_to_users_checked_yes="";
		$send_email_notifications_to_users_checked_no=" CHECKED";
		
	}
}
else
{
	$send_email_notifications_to_users_checked_yes="";
	$send_email_notifications_to_users_checked_no=" CHECKED";
	
}
echo '<h2>'.$this->lang['option_title_send_email_notifications_to_users'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_send_email_notifications_to_users'].'</div>';

echo $this->lang['yes'].' <input type="radio" name="opt[send_email_notifications_to_users]" value="yes"'.$send_email_notifications_to_users_checked_yes.'>'.$this->lang['no'].' <input type="radio" name="opt[send_email_notifications_to_users]" value="no"'.$send_email_notifications_to_users_checked_no.'>';


if(isset($this->opt['send_email_notifications_to_staff']))
{
	if($this->opt['send_email_notifications_to_staff']=='yes')
	{
		$send_email_notifications_to_staff_checked_yes=" CHECKED";
		$send_email_notifications_to_staff_checked_no="";
	}
	else
	{
		$send_email_notifications_to_staff_checked_yes="";
		$send_email_notifications_to_staff_checked_no=" CHECKED";
		
	}
}
else
{
	$send_email_notifications_to_staff_checked_yes="";
	$send_email_notifications_to_staff_checked_no=" CHECKED";
	
}

echo '<h2>'.$this->lang['option_title_send_email_notifications_to_staff'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_send_email_notifications_to_staff'].'</div>';

echo $this->lang['yes'].' <input type="radio" name="opt[send_email_notifications_to_staff]" value="yes"'.$send_email_notifications_to_staff_checked_yes.'>'.$this->lang['no'].' <input type="radio" name="opt[send_email_notifications_to_staff]" value="no"'.$send_email_notifications_to_staff_checked_no.'>';


echo '<h2>'.$this->lang['option_title_ticket_slug'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_ticket_slug'].'</div>';
echo '<input type="text" name="opt[ticket_post_type_slug]" value="'.$this->opt['ticket_post_type_slug'].'">';


echo '<h2>'.$this->lang['option_title_ticket_category_slug'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_ticket_category_slug'].'</div>';
echo '<input type="text" name="opt[ticket_category_slug]" value="'.$this->opt['ticket_category_slug'].'">';



if(isset($this->opt['delete_options_on_uninstall']))
{
	if($this->opt['delete_options_on_uninstall']=='yes')
	{
		$delete_options_on_uninstall_checked_yes=" CHECKED";
		$delete_options_on_uninstall_checked_no="";
	}
	else
	{
		$delete_options_on_uninstall_checked_yes="";
		$delete_options_on_uninstall_checked_no=" CHECKED";
		
	}
}
else
{
	$delete_options_on_uninstall_checked_yes="";
	$delete_options_on_uninstall_checked_no=" CHECKED";
	
}

echo '<h2>'.$this->lang['option_title_delete_options_on_uninstall'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_delete_options_on_uninstall'].'</div>';

echo $this->lang['yes'].' <input type="radio" name="opt[delete_options_on_uninstall]" value="yes"'.$delete_options_on_uninstall_checked_yes.'>'.$this->lang['no'].' <input type="radio" name="opt[delete_options_on_uninstall]" value="no"'.$delete_options_on_uninstall_checked_no.'>';




if(isset($this->opt['delete_data_on_uninstall']))
{
	if($this->opt['delete_data_on_uninstall']=='yes')
	{
		$delete_data_on_uninstall_checked_yes=" CHECKED";
		$delete_data_on_uninstall_checked_no="";
	}
	else
	{
		$delete_data_on_uninstall_checked_yes="";
		$delete_data_on_uninstall_checked_no=" CHECKED";
		
	}
}
else
{
	$delete_data_on_uninstall_checked_yes="";
	$delete_data_on_uninstall_checked_no=" CHECKED";
	
}

echo '<h2>'.$this->lang['option_title_delete_data_on_uninstall'].'</h2>';
echo '<div class="'.$this->internal['prefix'].'option_info">'.$this->lang['option_info_delete_data_on_uninstall'].'</div>';

echo $this->lang['yes'].' <input type="radio" name="opt[delete_data_on_uninstall]" value="yes"'.$delete_data_on_uninstall_checked_yes.'>'.$this->lang['no'].' <input type="radio" name="opt[delete_data_on_uninstall]" value="no"'.$delete_data_on_uninstall_checked_no.'>';




$this->do_setting_section_additional_settings($tab);

echo $this->do_admin_settings_form_footer($tab);

?>