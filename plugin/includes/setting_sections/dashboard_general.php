<?php

if(isset($this->opt[$tab]['force_site_button']))
{
	
	if($this->opt[$tab]['force_site_button']=='yes')
	{
	
		$force_site_checked_yes=" CHECKED";
	
	}
	else
	{
		$force_site_checked_no=" CHECKED";	
	}
	
}	
else
{
	$force_site_checked_no=" CHECKED";	
}


?>


	<h3><?php echo $this->lang['opt_org_name_title']; ?></h3>
	<?php echo $this->lang['opt_org_name_explanation']; ?><br><br>
	<input type="text" name="opt[org_name]" value="<?php echo $this->opt['org_name']; ?>">
	