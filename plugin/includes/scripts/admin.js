jQuery.noConflict(); // Reverts '$' variable back to other JS libraries

jQuery(document).ready(function($) {

	jQuery('body').on("click",'.cb_p3_admin_toggle_button',function(e) {
		target_div = document.getElementById($(this).attr( 'target' ));
		$(target_div).fadeToggle(1800);
	});
	
	jQuery(document).on( 'click', '.cb_p3_notice .notice-dismiss', function(e) {

		var id_to_send = e.target.id;
	
		console.log($(this).parent().attr("id"));
		console.log(jQuery(this).attr("id"));
		jQuery.ajax({
			url: ajaxurl,
			data: {
				action: 'dismiss_admin_notice',
				cb_p3_action: 'dismiss_admin_notice',
				notice_id: $(this).parent().attr("id"),
				notice_type: $(this).parent().attr("notice_type"),
			}
		});

	});
	
	jQuery(document).on('submit', '.cb_license_save_form', function(e) {
			
		e.preventDefault();
 
		var form = jQuery(this);
		var oldcontent = form.parent().html();
		var targetdiv = form.parent();

		
		jQuery.post(form.attr('action'), form.serialize(), function(data) {
	
			
			targetdiv.html(data['message']);

			if(data['result'] == 'success')
			{
			}
			else
			{
				
				targetdiv.fadeOut(6000, function() {
					jQuery(this).html(oldcontent);
					jQuery(this).fadeIn(1000);
				});


				
			}
			
		}, 'json');


	});
	
	
	/*
	
	
	jQuery('.cb_license_save_form').submit(function(e){
			
			e.preventDefault();
			
			var form = jQuery(this);
			
			var send_license = ('input[name="license"]').val();
			console.log(send_license);
			jQuery.ajax({
				type:"POST",
				url: "/wp-admin/admin-ajax.php",
				data: { 
					action: "cb_p3_a1_save_license",
					license: send_license
				},
				success: function (data) {
					console.log(data);
					form.parent().html(data);
					console.log(form.parent().attr('id'));
				}
			});
	});
	
	
	*/

		
  
});