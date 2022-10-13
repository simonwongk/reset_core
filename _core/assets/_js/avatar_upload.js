var load_img_filename;

$(function(){
	$('head').append($('<link rel="stylesheet" type="text/css" />').attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-jcrop/0.9.15/css/jquery.Jcrop.min.css'));


	$.cachedScript('https://cdnjs.cloudflare.com/ajax/libs/jquery-jcrop/0.9.15/js/jquery.Jcrop.min.js').done(function() {
		$('#avatar_upload').change(load_img);

		function load_img() {
			var file = $('#avatar_upload')[0].files[0];
			if ( ! file ) {
				$('#target').html( '' );
				return;
			}
			$('#target').html($('<div id="target_loading">Loading...</div>'));
			load_img_filename = file.name;
			console.log('load_img_filename', load_img_filename);
			var reader = new FileReader();
			reader.onload = function(event) {
			    var result = event.target.result;
			    $.post( $('#avatar_upload').data('thumbnail-target'), { data: result, name: load_img_filename }, set_preview_img );
			};
			reader.readAsDataURL(file);
		}

		function set_preview_img( data ) {
			$('#target_loading').remove();
			console.log( 'logo upload result', data );
			if ( !data.path ) {
				console.log('no path');
				return;
			}

			$('#target').html( '<img src="' + data.path + '?' + (new Date()).getTime() + '" id="target_img" />' );
			set_jcrop();
		}

		function set_jcrop() {
			$('#target_img').Jcrop({
				onChange: showPreview,
				onSelect: showPreview,
				setSelect:   [ 0, 0, 300, 300 ],
				aspectRatio: 1,
			});

			$('#avatar_upload_btn').prop( 'disabled', false );

			function showPreview(e){
				$('#x').val(e.x);
				$('#y').val(e.y);
				$('#w').val(e.w);
				$('#h').val(e.h);
			}
		}

	});
});