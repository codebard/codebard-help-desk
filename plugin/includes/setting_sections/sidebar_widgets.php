<?php



	if($this->opt[$tab]['insert_text_align']=='left')
	{
	
		$widget_insert_text_align_checked_left=" CHECKED";
	
	}
	if($this->opt[$tab]['insert_text_align']=='center')
	{

		$widget_insert_text_align_checked_center=" CHECKED";
	
	}
	if($this->opt[$tab]['insert_text_align']=='right')
	{
	
		$widget_insert_text_align_checked_right=" CHECKED";
	
	}
	
?>
	<h1>Sidebar Widget</h1>
	<hr>		
	<h3>Alignment of Message text And Button in Widget</h3>
	You can align the message and button to the left, center, or right.		
	<br><br>
	Left <input type="radio" name="<?php echo $this->internal['prefix'].$tab; ?>[insert_text_align]" value="left"<?php echo $widget_insert_text_align_checked_left; ?>>
	Center <input type="radio" name="<?php echo $this->internal['prefix'].$tab; ?>[insert_text_align]" value="center"<?php echo $widget_insert_text_align_checked_center; ?>>
	Right <input type="radio" name="<?php echo $this->internal['prefix'].$tab; ?>[insert_text_align]" value="right"<?php echo $widget_insert_text_align_checked_right; ?>>

<h3>Widget Message Font Size</h3>
	You can adjust the size of the message over button in Widget with the below value. px, %, pt accepted.<br><br>
	<input type="text" name="<?php echo $this->internal['prefix'].$tab; ?>[message_font_size]" value="<?php echo $this->opt[$tab]['message_font_size']; ?>">	
	
	
	<h3>Message over Button Top and Bottom Margin</h3>
	This allows you to change the margin above and under the text message over the button to change distance of text from button below.<br><br>
	<input type="text" name="<?php echo $this->internal['prefix'].$tab; ?>[message_over_post_button_margin]" value="<?php echo $this->opt[$tab]['message_over_post_button_margin']; ?>">
	
	<h3>Button Top and Bottom Margin</h3>
	You can change the margin of the Button independently from the above margin, in case you need it.<br><br>
	<input type="text" name="<?php echo $this->internal['prefix'].$tab; ?>[button_margin]" value="<?php echo $this->opt[$tab]['button_margin']; ?>">