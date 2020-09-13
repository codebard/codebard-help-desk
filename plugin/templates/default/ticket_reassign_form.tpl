<div class="{***prefix***}clearfix"></div>

	<form id="{***prefix***}ticket_reassign_form" enctype="multipart/form-data" method="post" action="{***help_desk_page_url***}" target="_self">
		<h4>{***select_agent***}</h4>
		<div id="{***prefix***}department_select">{***agentselect***}</div>
		{%%%reassign_ticket_explanation%%%}
		<div class="{***prefix***}clearfix"></div>
		<input type="submit" value="{%%%reassign_ticket_button_label%%%}">
								
		<input type="hidden" name="{***prefix***}action" value="reassign_ticket_confirm"> 
		<input type="hidden" name="{***prefix***}ticket_id" value="{***ticket_id***}"> 
		<input type="hidden" name="cb_plugin" value="{***id***}">
								
	</form>

<div class="{***prefix***}clearfix_separator"></div>

