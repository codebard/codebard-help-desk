<?php
// Generate Role selector here:

$role_select=$this->make_roles_select();


$dept_select=$this->make_department_select();

echo '<div class="cb_p3_admin_segment2">';

echo '<span class="cb_p3_h2">'.$this->lang['admin_title_assign_staff_to_departments'].'</span>';
echo '<form action="admin.php?page=settings_cb_p3&'.$this->internal['prefix'].'tab=staff" name="" method="post">';

echo $dept_select;

wp_dropdown_users(array('name'=>$this->internal['prefix'].'user'));
echo $role_select;

echo '<input type="hidden" name="'.$this->internal['prefix'].'action" value="add_staff_to_department">';
echo '<input type="hidden" name="cb_plugin" value="'.$this->internal['id'].'">';
echo '<input type="submit" value="'.$this->lang['admin_label_assign_modify'].'" class="'.$this->internal['prefix'].'admin_button">';

echo '</form>';


	echo '<span class="cb_p3_h3">'.$this->lang['admin_title_current_assignments'].'</span>';
	echo '<div class="cb_p3_admin_separator"></div>';


foreach($this->opt['departments_to_staff'] as $key => $value)
{

	$department = get_term( $key, $this->internal['prefix'].'support',ARRAY_A );
	echo '<h4>'.$department['name'].'</h4>';

	
	foreach($this->opt['departments_to_staff'][$key] as $key2 => $value2)
	{
	
		$current_user=get_userdata($key2);
			
		echo '<span class="cb_p3_admin_support_admin_staff_name" style="color:'.$this->opt['roles'][$this->opt['departments_to_staff'][$key][$key2]]['color'].'">'.$current_user->nickname.'</span><form class="'.$this->internal['prefix'].'inline_block_form" action="admin.php?page=settings_cb_p3&'.$this->internal['prefix'].'tab=staff" name="" method="post"><input type="image" src="'.$this->internal['plugin_url'].'images/admin_delete_icon.png" alt="'.$this->lang['admin_label_submit'].'" />
		<input type="hidden" name="cb_plugin" value="'.$this->internal['id'].'" />
		<input type="hidden" name="'.$this->internal['prefix'].'action" value="remove_staff_from_department" />
		<input type="hidden" name="'.$this->internal['prefix'].'department" value="'.$key.'" />
		<input type="hidden" name="'.$this->internal['prefix'].'user" value="'.$key2.'" />
		</form>';
		
	
	}

	echo '<br>';
	
}

echo '</div>';


?>