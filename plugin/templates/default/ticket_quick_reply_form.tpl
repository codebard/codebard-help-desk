<form class="{***prefix***}reply_form" enctype="multipart/form-data" method="post">

<textarea name="{***prefix***}ticket_content" class="{***prefix***}ticket_reply_textarea"></textarea>
<div class="{***prefix***}separator"></div>
<span class="{***prefix***}ticket_attachment_label">{%%%ticket_attachment%%%}</span><input type="file" name="{***prefix***}ticket_attachment" id="{***prefix***}ticket_attachment">
<div class="{***prefix***}separator"></div>
<input type="submit" value="{%%%button_reply%%%}" class="{***prefix***}quick_reply_button">
						
<input type="hidden" name="{***prefix***}action" value="insert_quick_reply"> 
<input type="hidden" name="{***prefix***}plugin" value="cb_p3">
<input type="hidden" name="{***prefix***}ticket_id" value="{***ticket_id***}">
						
</form>