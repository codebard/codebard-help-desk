
	<div class="{***prefix***}clearfix"></div>
	<form class="{***prefix***}create_ticket_form" enctype="multipart/form-data" method="post">
		<h2>{%%%ticket_form_title%%%}</h2>
		{%%%ticket_title_explanation%%%}
		<br>
		<br>
		<input name="{***prefix***}ticket_title" class="{***prefix***}ticket_form_text_input">
		<br>
		<br>
		{%%%ticket_content_explanation%%%}
		<br>
		<br>
		<textarea name="{***prefix***}ticket_content" class="{***prefix***}ticket_form_textarea"></textarea>
		<div class="{***prefix***}clearfix"></div>
		<div class="{***prefix***}separator"></div>
		<span class="{***prefix***}ticket_attachment_label">{%%%ticket_attachment%%%}</span><input type="file" name="{***prefix***}ticket_attachment" id="{***prefix***}ticket_attachment">
		<div class="{***prefix***}separator"></div>
		{***ticket_open_for_different_user***}
		<input type="submit" value="{%%%labelcontinue%%%}">

		<input type="hidden" name="{***prefix***}action" value="create_ticket"> 
		<input type="hidden" name="{***prefix***}department" value="{***department_id***}"> 
		<input type="hidden" name="cb_plugin" value="{***id***}">
		<!--extra_hidden_vars_-->
								
	</form>
