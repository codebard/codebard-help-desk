<div class="{***prefix***}clearfix"></div>

	<form id="{***prefix***}create_ticket_form" enctype="multipart/form-data" method="post" action="{***help_desk_page_url***}" target="_self">
		<h4>{%%%select_department_to_assign_ticket%%%}</h4>
		<div id="{***prefix***}department_select">{***departmentselect***}</div>
		<div class="{***prefix***}clearfix"></div>
		<input type="submit" value="{%%%change_ticket_department_button_label%%%}">
								
		<input type="hidden" name="{***prefix***}action" value="change_ticket_department_confirm"> 
		<input type="hidden" name="{***prefix***}ticket_id" value="{***ticket_id***}"> 
		<input type="hidden" name="cb_plugin" value="{***id***}">
								
	</form>

