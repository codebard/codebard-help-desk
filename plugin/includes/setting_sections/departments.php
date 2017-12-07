<?php

$dept_select=$this->make_department_select();

echo '<div class="'.$this->internal['prefix'].'admin_segment2">';

echo '<span class="'.$this->internal['prefix'].'h2">'.$this->lang['admin_title_departments'].'</span>

<span class="'.$this->internal['prefix'].'h3">'.$this->lang['admin_subtitle_departments'].'</span>

<form action="admin.php?page=settings_'.$this->internal['id'].'&'.$this->internal['prefix'].'tab=departments" id="department_form" name="" method="post"  enctype="multipart/form-data">';

if(isset($_REQUEST[$this->internal['prefix'].'department_name']))
{
	
	$department_name = $_REQUEST[$this->internal['prefix'].'department_name'];
	
}
else
{
	$department_name='';
	
}

echo $this->lang['admin_title_name'];
echo '<br>';
echo '<input type="text" style="width : 350px;" name="department_name" value="'.$department_name.'">';
echo '<br>';
echo $this->lang['admin_title_description'];
echo '<br>';


if(isset($_REQUEST[$this->internal['prefix'].'department_name']))
{
	
	$department_description = $_REQUEST[$this->internal['prefix'].'department_description'];
	
}
else
{
	$department_description='';
}

echo '<textarea name="department_description" style="width : 350px;">'.$department_description.'</textarea>';
echo '<br>';
echo '<input type="hidden" name="'.$this->internal['prefix'].'action" value="add_modify_department">';
echo '<input type="hidden" name="cb_plugin" value="'.$this->internal['id'].'">';



if(isset($_REQUEST[$this->internal['prefix'].'department']))
{
	
	$department = $_REQUEST[$this->internal['prefix'].'department'];
	
}
else
{
	$department='';
	
}

echo '<input type="hidden" name="department" value="'.$department.'">';
echo '<button type="submit" class="'.$this->internal['prefix'].'admin_button">'.$this->lang['admin_label_add_edit'].'</button>';


echo '<button type="reset" class="'.$this->internal['prefix'].'admin_button" onclick="window.location.href=window.location.href;return false;">'.$this->lang['admin_label_clear_new'].'</button>';

echo '</form>';

echo '<span class="'.$this->internal['prefix'].'h3">'.$this->lang['admin_subtitle_select_edit_delete'].'</span><form action="admin.php?page=settings_'.$this->internal['id'].'&'.$this->internal['prefix'].'tab=departments" name="" method="post" enctype="multipart/form-data">';

echo $dept_select;

echo '<select name="'.$this->internal['prefix'].'action">
	  <option value="edit_department">'.$this->lang['admin_label_edit'].'</option>
	  <option value="delete_department">'.$this->lang['admin_label_delete'].'</option>
	  </select>
	  ';
echo '<input type="submit" value="'.$this->lang['admin_label_go'].'" class="'.$this->internal['prefix'].'admin_button">';
echo '<input type="hidden" name="cb_plugin" value="'.$this->internal['id'].'">';
echo '</form>';


echo '</div>';


?>