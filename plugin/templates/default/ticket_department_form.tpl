<div class="{***prefix***}clearfix"></div>

	<form id="{***prefix***}create_ticket_form" enctype="multipart/form-data" method="post" action="{***help_desk_page_url***}" target="_self">
		<h2>{%%%select_department%%%}</h2>
		<div id="{***prefix***}department_select">{***departmentselect***}</div>
		{%%%select_department_explanation%%%}
		<div class="{***prefix***}clearfix"></div>
		<input type="submit" value="{%%%labelcontinue%%%}">
								
		<input type="hidden" name="{***prefix***}action" value="ticket_form"> 
		<input type="hidden" name="cb_plugin" value="{***id***}">
								
	</form>

