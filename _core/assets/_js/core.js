/**
 */

var uNum = 1; // Unique I
var form_submitting = false; // Resolve duplicate form submission
var accesskey_registered = false; // Only register document keydown once
var accesskeys = [];

var m = {
	init:function(){
		$.expr[':'].textEquals = function(a, i, m) {
			return $(a).text().match("^" + m[3] + "$");
		};

		$.cachedScript = function( url ) {
			return $.ajax( {
				dataType: "script",
				cache: true,
				url: url
			});
		};
		m.requiredStar();
		m.btn_shadow();
		m.clock(); // counter
		m.secode(); // secode
		m.autoHeight();
		m.click_to_copy();
		m.easy_submit();
		m.cover_hide_reg();
		m.table_sortable();
		m.table_resizable();
		$('a.cfm').on('click', function(){
			if ( ! confirm( __('Please confirm you want to proceed') ) ) return false;
		});
		$('a.back').on('click', function(e){
			history.go(-1);
			return false;
		});

		m.accesskey();
		m.modalOn();

		$('[type=button]:not([noLoading])').click(function(){
			var $btn = $(this);
			m.prevent_multi_click( $btn );
		});
		$('form').submit(function(){
			if ( form_submitting ) {
				return false;
			}
			var $btn = $( '[type=submit]:not([noLoading])', this );
			m.prevent_multi_click( $btn );
		});

		//input add class="form-control"
		m.input_add_cls();
		//a add disabled after clicked
		$('a:not([target],[data-enable],.cfm,[data-toggle])').on('click',function(){
			var that = $(this);
			that.addClass('disabled');
			setTimeout( function () {
				that.removeClass( 'disabled' );
			}, 3000 );
		});

		$("[mp3]").on('mouseenter click', function() {
			$(this).addClass('disabled');
			if(!$('audio').length) $('<audio />').appendTo('body');
			$('audio').html($('<source>').attr('src', $(this).attr('mp3')));
			$('audio')[0].load();
			$('audio')[0].play();
			$(this).removeClass('disabled');
		});

		$( 'select[data-jump]' ).change( function() {
			m.j( '/' + p + '?s' + $(this).data( 'jump' ) + '=1&o' + $(this).data( 'jump' ) + '=' + encodeURIComponent( $(this).val() ) );
		} );
		$( '#filter_start_end' ).click( function() {
			var s = 94;
			m.j( '/' + p + '?s' + s + '=1&start=' + $('input[name="start"]').val() + '&end=' + $('input[name="end"]').val() );
		} );
		$( '[data-filter_start_end]' ).click( function() {
			var s = $(this).data('filter_start_end');
			m.j( '/' + p + '?s' + s + '=1&start=' + $('input[data-filter_start="'+s+'"]').val() + '&end=' + $('input[data-filter_end="'+s+'"]').val() );
		} );
		$('[data-filter]').click(function(){
			var s = $(this).data('filter');
			m.j( '/' + p + '?s' + s + '=1&o' + s + '=' + $('input[data-filter_input="'+s+'"]').val() );
		});
		$('input[data-filter_input]').keyup(function(e){
			if (e.key==="Enter") {
				console.log('Pressed key Enter, trigger click btn=' + 'input[data-filter="'+$(this).data('filter_input')+'"]' );
				$('input[data-filter="'+$(this).data('filter_input')+'"]').click();
			}
		});

		//initialize ajax
		$.ajaxSetup({
			url: '/',
			type: 'POST',
			dataType: 'json',
			data: {__: '__'},
			cache: false
		});

		if(self != top) {
			$('body').css('margin-bottom', '0');
			$('body').css('padding-bottom', '0');
			$('.panel').css('margin-bottom', '5px');
		}

		// 上一页下一页自动切换
		$('body').on('keydown',function(){
			if($(":focus").length > 0) return;

			if(m.keycode(37) && $('a[data-page="last"]').length) {
				m.j($('a[data-page="last"]:first').attr('href'));
			}
			if(m.keycode(39) && $('a[data-page="next"]').length) {
				m.j($('a[data-page="next"]:first').attr('href'));
			}
		});

		// 千位数格式化
		$('[data-dollar]').each(function(index, el) {
			m.dollarComma(this);
		});

		m.burst_checkbox();
		m.select2init();
		m.bootstrapToggleInit();
		// m.loadToggleSwithCss();

		m.videoControl();

		m.date_picker();

		if ( $('[data-trigger]').length > 0 ) {
			$('[data-trigger]').popover({
				html: true
			}) ;
		}

		// Checkbox required attr handler
		m.checkbox_required();

		$('[data-toggle-tab]').click(function(){
			$('[data-toggle-tab]').removeClass('btn-primary').addClass('btn-secondary');
			$(this).addClass('btn-primary').removeClass('btn-secondary');

			if ( $(this).data('toggle-tab') ) {
				console.log('toggle-tab one type only');
				$('[data-toggle-target]').hide();
				$('[data-toggle-target='+$(this).data('toggle-tab')+']').show();
			}
			else {
				console.log('show all types');
				$('[data-toggle-target]').show();
			}
		});

		m.coldiy_update();
	},

	coldiy_clear: function(selector) {
		selector = selector || '.coldiy_clear';
		if ( ! $(selector).length ) return;

		$(selector).click(function(e){
			e.preventDefault();
			e.stopPropagation();
			$.post($(this).attr('href'), function(data){
				if ( ! data.hasOwnProperty( 'id' ) || ! data.hasOwnProperty( 'td' ) ) {
					console.log( 'column clear res', data );
					alert( __('Failed to clear column value') );
					return;
				}

				$('#'+data.id).replaceWith(data.td);
				m.modal_on_a( '#'+data.id );
			});
		});
	},

	cover_show: function(selector, callback){
		if ( ! $('#root_cover').length ) return;
		$('#root_cover').data('selector', selector).data('callback', callback).show();
	},

	cover_hide: function(){
		$($('#root_cover').data('selector')).hide();
		$('#root_cover').hide();
		if ( $('#root_cover').data('callback') ) $('#root_cover').data('callback')();

		$('#root_cover').data('selector','').data('callback','');
	},

	cover_hide_reg: function(){
		if ( ! $('#root_cover').length ) return;
		$('#root_cover').click(function(e){
			e.stopPropagation();
			console.log('hide root_cover');
			m.cover_hide();
		});
	},

	coldiy_dropdown: function(selector){
		selector = selector || '.custom_col_dropdown a.e_cover';
		if ( ! $(selector).length ) return;

		$(selector).off('click').click(function(e){
			e.preventDefault();
			e.stopPropagation();
			$(this).blur();
			var that = this;
			var href = $(this).attr('href');
			href += href.indexOf("?") === -1 ? '?' : '&';
			href += '__=1';
			$.post( href, function(data) {
				if ( ! data.hasOwnProperty( 'col_list' ) ) {
					console.error( 'No col_list data in response' );
					return;
				}
				// build up dropdown list
				var list = '';
				for (var i = 0; i < data.col_list.length; i++) {
					var bgcolor = data.col_list[i].color ? data.col_list[i].color : '#8f8f8f';
					var title = data.col_list[i].title ? data.col_list[i].title : 'X';
					if ( i < 9 ) title = (i+1) + '. ' + title;
					list += '<div class="coldiy_picker_list custom_col_editable" style="background-color: '+bgcolor+';"><a href="'+data.col_list[i].link+'" data-accesskey-i target="modal">'+title+'</a></div>';
				}
				$('.coldiy_picker_container').html(list);
				var offset = $(that).offset();
				m.cover_show( '.coldiy_picker_root', function(){$('.coldiy_picker_container').html('');} );
				$('.coldiy_picker_root').show().css({ 'left': offset.left, 'top': offset.top+$(that).height() });

				// Disabled original accesskey
				for (var i = 1; i <= 9; i++) {
					if ( $( '[accesskey=' + i + ']' ).length <= 0 ) continue;
					$( '[accesskey=' + i + ']' ).removeAttr( 'accesskey' ).attr( 'accesskey2', i );
				}

				console.log(selector+' clicked');
				m.accesskey();
				// Add handler to dropdown links
				$('.coldiy_picker_list a').click(function(e2){
					console.log('dropdown a clicked');
					e2.preventDefault();
					console.log('$.get to ' + $(this).attr('href') );
					$.post( $(this).attr('href'), function(data2){
						console.log('dropdown a ajax ok');

						if ( data2.hasOwnProperty( 'redirect' ) ) {
							m.j();
							return;
						}

						if ( ! data2.hasOwnProperty( 'id' ) || ! data2.hasOwnProperty( 'td' ) ) {
							console.error( 'column update res', data2 );
							alert( __('Failed to update column value') );
							return;
						}

						console.log('start hidding picker');
						m.cover_hide();
						m.accesskey();
						$('#'+data2.id).replaceWith(data2.td);
						m.modal_on_a( '#'+data2.id );
					} );
				});
			} );
		});
	},

	coldiy_update: function(){
		if ( typeof latest_ts === 'undefined' ) {
			return;
		}
		if ( ! $( 'table.col_diy' ).length ) {
			return;
		}

		if ( ! $( '.coldiy_ajax_indicator' ).length ) {
			$('table.col_diy thead tr').find('th:visible:first').prepend( '<span class="coldiy_ajax_indicator">●</span>' );
		}

		$('.coldiy_ajax_indicator').removeClass('text-success');

		$.post( '/' + p + '/cols_diy?step=changes&ts=' + latest_ts, function(data) {
			if ( ! data.hasOwnProperty( '_res' ) || data._res != 'ok' ) {
				alert( data._msg );
				$('.coldiy_ajax_indicator').addClass('text-danger');
				return;
			}
			latest_ts = data.ts;
			if ( data.list.length > 0 ) {
				for ( k in data.list ) {
					var cell = JSON.parse( data.list[k] );
					if ( cell[0] == '_need_refresh' ) {
						if ( $('#modal').is(':visible') ) {
							window.modal_parent = true;
						}
						else {
							m.j();
						}
						return;
					}
					if ( $( '#'+cell[0] ).length > 0 ) {
						$( '#'+cell[0] ).replaceWith( cell[1] );
						m.modal_on_a( '#'+cell[0] );
					}
				}
			}
			$('.coldiy_ajax_indicator').addClass('text-success');
			setTimeout( ()=>{m.coldiy_update();}, 1000 );
		} );
	},

	easy_submit: function(attr) {
		attr = attr || 'data-easysubmit';
		if ( ! $('form['+attr+']').length ) {
			return;
		}
		// $('form['+attr+']').addClass('position-relative');
		var btn = $( 'form['+attr+'] button[type=submit]' );
		btn.clone().addClass( 'easy_submit' ).removeAttr( 'id' ).removeAttr( 'accesskey' ).off().insertAfter( btn ) ;
	},

	html_editor: function(selector) {
		selector = selector || 'textarea';
		$.cachedScript( 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.2/tinymce.min.js' ).done(function(){
			tinymce.init({
				selector: selector,
				menubar: false,
				plugins: 'autolink,link', toolbar: [ 'undo redo | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignfull | link unlink | numlist bullist outdent indent' ],
				relative_urls : false,
				default_link_target: '_blank'
			});
		});
	},

	table_sortable: function( attr ) {
		attr = attr || 'data-no-sort';
		if ( ! $('table.col_diy:not(['+attr+'])').length ) {
			return;
		}
		$.cachedScript('https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js').done(function() {
			$('table.col_diy:not(['+attr+'])').tablesorter({
				usNumberFormat : false,
				sortReset      : true,
				sortRestart    : true
			});
		});
	},

	table_resizable: function( attr ) {
		attr = attr || 'data-no-resize';
		if ( ! $('table.col_diy:not(['+attr+'])').length ) {
			return;
		}
		var pressed = false;
		var start = undefined;
		var startX, startWidth;
		var th_index = 0;
		var coldiy_name = 'coldiy_'+window.location.pathname;

		var coldiy_data = JSON.parse( localStorage.getItem( coldiy_name ) );
		if ( ! coldiy_data ) {
			coldiy_data = {};
		}
		else {
			for ( k in coldiy_data ) {
				$('table.col_diy:not([data-no-resize]) th:nth-child('+k+')').width(coldiy_data[k]);
			}
		}

		// Append resize dragger
		$('table').on('mousedown', '.table_resize', function(e){
			start = $(this).closest('th');
			th_index = start.index()+1;
			pressed = true;
			startX = e.pageX;
			startWidth = $(this).width();
			console.log('startX',startX, 'startWidth',startWidth, 'th_index', th_index);
		});

		$('table.col_diy:not([data-no-resize]) th').append($('<div class="table_resize"></div>'));

		$(document).mousemove(function(e) {
			if(pressed) {
				var width = Math.floor(startWidth+(e.pageX-startX) );
				// console.log( 'curr x', e.pageX, 'old x', startX, 'new width', width );
				$(start).width(width);
				// console.log('width set to ', $(start).width(), 'el', $(start).text());

				// Save to localstorage
				coldiy_data[th_index] = width;
				localStorage.setItem( coldiy_name, JSON.stringify( coldiy_data ) );
			}
		});

		$(document).mouseup(function() {
			if(pressed) {
				// $(start).removeClass("resizing");
				pressed = false;
			}
		});
	},

	prevent_multi_click: function( $btn ) {
		form_submitting = true;
		var $ori_v = $btn.val();
		var $ori_html = $btn.html();
		$btn.val( 'Loading...' ).html( 'Loading...' ).attr( 'disabled', 'disabled' );
		setTimeout( function () {
			form_submitting = false;
			$btn.removeAttr( 'disabled' ).val( $ori_v ).html( $ori_html );
		}, 3000 );
	},

	click_to_copy: function() {
		$('[data-click_to_copy]').click(function(){
			if ( $(this).text() == __('Copied') ) return;

			var $temp = $("<input>");
			$("body").append($temp);
			$temp.val($(this).text()).select();
			document.execCommand("copy");
			$temp.remove();

			var ori = $(this).html();
			$(this).html('<span class="text-success small">'+__('Copied')+'</span>');
			var that = this;
			setTimeout(function(){$(that).html(ori);}, 500);
		});
	},

	checkbox_required_submit_btn: function() {
		// Form submission required checkbox checker
		$( 'form [type=submit]' ).click( function() { // can't directly use form submit hook as required checkbox will suspend the form submission
			$( 'input[type=checkbox][required]:not(:checked),input[type=radio][required]:not(:checked)' ).parent().parent().addClass( 'bg-warning', 400 ).removeClass( 'bg-warning', 600 );
		} );
	},

	checkbox_required: function() {
		$( 'input[type=checkbox][required],input[type=radio][required]' ).on( 'change', function( e ) {
			// console.log( 'Fire checkbox/radio required attr handler' );
			var list = $( '[name="' + $(this).attr('name') + '"]' );
			list.prop('required', ! list.is(':checked') );
		} ).trigger( 'change' );

		m.checkbox_required_submit_btn();
	},

	input_add_cls: function() {
		$('input[type!=radio][type!=checkbox][type!=submit][type!=hidden]:not([noStyle]),select:not([noStyle]),textarea:not([noStyle])').addClass('form-control');
	},

	loadScripts: function(scripts) {
		var deferred = jQuery.Deferred();

		function loadScript(i) {
			if (i < scripts.length) {
				jQuery.ajax({
					url: scripts[i],
					dataType: "script",
					cache: true,
					success: function() {
						loadScript(i + 1);
					}
				});
			} else {
				deferred.resolve();
			}
		}
		loadScript(0);

		return deferred;
	},

	// bootstrap toggle initialize
	loadToggleSwithCss:function(){
		$('head').append($('<link rel="stylesheet" type="text/css" />')
					.attr('href', 'https://cdn.jsdelivr.net/css-toggle-switch/latest/toggle-switch.css'));
	},
	// bootstrap toggle initialize
	bootstrapToggleInit:function(){
		if ( ! $( 'input[data-toggle="toggle"]' ).length ) return ;

		$('head').append($('<link rel="stylesheet" type="text/css" />')
					.attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css'));
		$.cachedScript('/assets/js/_js/bootstrap-toggle.v4.js')
			.done(function() {

		});
	},

	burst_checkbox: function() {
		if( ! $('.radioTag label:not([bursted])').length ) return;

		$.cachedScript("/assets/js/_js/lib/mo.min.js").done(function() {
			const square = new mojs.Shape({
				radius: 45,
				radiusY: 45,
				shape: 'rect',
				stroke: '#2ECC40',
				strokeWidth: {10: 50},
				fill: 'none',
				scale: {0.45 : 0.55},
				opacity: {1: 0},
				duration: 350,
				easing: 'sin.out',
				isShowEnd: false,
			});

			const lines = new mojs.Burst({
				left: 0, top: 0,
				radius:   { 35: 75 },
				angle:    0,
				count: 8,
				children: {
					shape:        'line',
					radius:       10,
					scale:        1,
					stroke:       '#2Ecc40',
					strokeDasharray: '100%',
					strokeDashoffset: { '-100%' : '100%' },
					duration:     700,
					easing:       'quad.out',
				}
			});

			function fireBurst(e) {
				var checked = $('#' + $(this).attr( 'for' ) ).prop('checked');
				if (!checked) {
					const pos = this.getBoundingClientRect();
					const timeline = new mojs.Timeline({ speed: 1.5 });
					timeline.add(square, lines);

					square.tune({
					  'left': pos.left + 21,
					  'top': pos.top + 21
					});
					lines.tune({
					  x: pos.left + 21,
					  y: pos.top + 21
					});

					if ("vibrate" in navigator) {
					  navigator.vibrate = navigator.vibrate || navigator.webkitVibrate || navigator.mozVibrate || navigator.msVibrate;

					  navigator.vibrate([100, 200, 400]);
					}

					timeline.play();
				}
			}

			$('.radioTag label:not([bursted])').attr('bursted', '1').on('click', fireBurst);
		});
	},

	// select2 initialize
	select2init : function(){
		if ( ! $('select').length ) return ;
		$('head').append($('<link rel="stylesheet" type="text/css" />')
			.attr('href', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.10/css/select2.min.css'));

		$.cachedScript("https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.10/js/select2.min.js")
			.done(function() {
				$('select:not([data-allow-new])').delay(1000).select2();
				$('select[data-allow-new]').delay(1000).select2( { tags:true } ) ;
		});

		// $.getScript('https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js');
	},

	date_picker: function() {
		$( 'input[type="date"]' ).attr( 'type', 'text' ).attr( 'data-type', 'date' );
		$( 'input[data-type="date"]' ).datepicker( {
			format: "yyyy-mm-dd",
			autoclose: true,
			zIndexOffset: 99999,
			todayHighlight: true
		} );
	},

	accesskey : function(){console.log('register accesskey');
		// 自动添加快捷键
		! $('[accesskey=B]').length && $("a:textEquals('"+__('Back')+"'):first").attr('accesskey', 'B');
		! $('[accesskey=A]').length && $("a:textEquals('"+__('Add')+"'):first").attr('accesskey', 'A');
		! $('[accesskey=E]').length && $("a:textEquals('"+__('Edit')+"'):first").attr('accesskey', 'E');
		! $('[accesskey=L]').length && $("a:textEquals('"+__('Lock')+"'):first").attr('accesskey', 'L');
		! $('[accesskey=U]').length && $("a:textEquals('"+__('Unlock')+"'):first").attr('accesskey', 'U');
		! $('[accesskey=Z]').length && $("button:textEquals('"+__('Insert')+"'):first").attr('accesskey', 'Z');
		! $('[accesskey=Z]').length && $("button:textEquals('"+__('Update')+"'):first").attr('accesskey', 'Z');
		! $('[accesskey=S]').length && $("[name=o00]:first").attr('accesskey', 'S');
		! $('[accesskey="\\/"]').length && $("[name=o00]:first").attr('accesskey2', String.fromCharCode( 47 ) );

		if ( $('[data-accesskey-i]').length ) {
			var accesskey_i = 1;
			var accesskey_imax = 9;
			for (var i = 1; i <= 9; i++) {
				if ( $('[accesskey="' + i + '"]').length > 0 ) {
					accesskey_i++;
					accesskey_imax--;
				}
				else {
					break;
				}
			}
			$('[data-accesskey-i]:lt('+accesskey_imax+')').each(function(){
				$(this).attr('accesskey', accesskey_i++);
			})
			$('[data-accesskey-i]').removeAttr('data-accesskey-i');
		}

		// Press accesskey
		$( '[accesskey]' ).map( function(){
			var thiskey = $(this).attr('accesskey');
			if ( $('[accesskey="' + thiskey + '"]').length > 1 ) {
				console.log( 'accesskey duplicated ' + thiskey, this );
				return;
			}

			$(this).attr('title', __('Shortcut')+': '+thiskey.toLocaleUpperCase());

			if ( accesskeys.indexOf(thiskey) === -1 ) accesskeys.push( thiskey );
		} );
		$( '[accesskey2]' ).map( function(){
			var thiskey = $(this).attr('accesskey2');
			if ( $('[accesskey="' + thiskey + '"]').length > 0 ) {
				console.log( 'accesskey2 duplicated w/ accesskey ' + thiskey, this );
				return;
			}
			if ( $('[accesskey2="' + thiskey + '"]').length > 1 ) {
				console.log( 'accesskey2 duplicated ' + thiskey, this );
				return;
			}

			$(this).attr('title', __('Shortcut')+': '+thiskey.toLocaleUpperCase());

			if ( accesskeys.indexOf(thiskey) === -1 ) accesskeys.push( thiskey );
		} );

		if ( accesskeys.length > 0 && ! accesskey_registered ) {
			accesskey_registered = true;
			$(document).on('keydown',function(e){
				if($(":focus").length > 0) return;
				if(e.metaKey) return;
				if(e.ctrlKey) return;
				if(e.altKey) return;
				if(e.shiftKey) return;

				console.log('accesskeys',accesskeys);
				for (var i = 0; i < accesskeys.length; i++) {
					if ( ! m.keycode( accesskeys[i].charCodeAt(0) ) ) {
						continue;
					}

					e.preventDefault();

					var that = '[accesskey="' + accesskeys[i] + '"]';
					if ( ! $(that).length ) {
						that = '[accesskey2="' + accesskeys[i] + '"]';
					}

					if ( $(that)[0].nodeName.toLowerCase() == 'input' && $(that).attr( 'type' ) == 'text' ) {
						$(that)[0].focus();
					}
					else {
						$(that)[0].click();
					}
				}
			});
		}
	},

	// Required field's related label add red star automatically
	requiredStar : function(){
		$('input[type=text][required],input[type=number][required],select[required],textarea[required]').each(function(index, el) {
			$('label[for='+$(this).attr('id')+']').append('<span class="text-danger">*</span>');
		});
	},

	// add shadow to all btn
	btn_shadow : function(){
		$('.btn').addClass( 'shadow' );
	},

	modalOpen : function(url){
		$('#modalIframe').attr("src", url);
		$('#modal').modal('show');
	},

	//modal open
	modalOn : function (){
		$('form[target=modal]').submit(function(event) {
			$('#modal').modal('handleUpdate');
			$(this).attr('target', 'modalIframe') ;
			var href = $(this).attr('action');
			href += href.indexOf("?") === -1 ? '?' : '&';
			href += 'modal=1';
			$(this).attr('action', href) ;
		});

		m.modal_on_a();

		$('#modal').on('shown.bs.modal', function() {
			$('#modalIframe').focus();
		});

		$( '#modal' ).on( 'hide.bs.modal', function() {
			if ( typeof modal_parent !== 'undefined' ) m.j();
		} );

		// close button
		$('[data-dismiss="modal"]').click(function(){
			$( '#modal' ).modal('hide');
			if ( typeof modal_parent !== 'undefined' ) m.j();
		});
	},

	modal_on_a: function(parent_selector) {
		var selector = 'a[target=modal]';
		var clear_selector = '.coldiy_clear';
		var dropdown_selector = '.custom_col_dropdown a.e_cover';
		if ( parent_selector ) {
			selector = parent_selector + ' ' + selector;
			clear_selector = parent_selector + ' ' + clear_selector;
			dropdown_selector = parent_selector + dropdown_selector;
		}
		$(selector).on('click', function(){
			var href = $(this).attr('href');
			href += href.indexOf("?") === -1 ? '?' : '&';
			href += 'modal=1';
			m.modalOpen(href);
			return false;
		});

		// clear button
		$(clear_selector).off('click');
		m.coldiy_clear(clear_selector);

		// dropdown button
		$(dropdown_selector).off('click');
		m.coldiy_dropdown(dropdown_selector);
	},

	dollarComma: function(that){
		var num = $.trim( $(that).text() );
		var x = num.split('.');
		var x1 = x[0];
		var x2 = x.length>1 ? '.'+(x[1].length==1?x[1]+'0':x[1]) : '';
		var dSign = '';
		if(x1.substr(0, 1) == '$'){
			dSign = '$';
			x1 = x1.substr(1);
		}

		if ( x1 == '0.00' || x1 == 0 ) {
			$(that).text( '-' );
			return;
		}

		if ( ! $.isNumeric( x1 ) ) {
			return;
		}

		var rgx = /(\d+)(\d{3})/;
		while (rgx.test(x1)) {
			x1 = x1.replace(rgx, '$1'+','+'$2');
		}
		$(that).text(dSign+x1+x2);
	},

	//close modal
	jx : function (){
		console.log('m.jx() run');
		window.parent.$('.modal').modal('hide');
	},
	j : function ( url ) {
		if ( ! url ) {
			if ( typeof _anchor !== 'undefined' ) {
				window.location.hash = _anchor;
			}
			window.location.reload(1);
			return;
		}

		if ( typeof _anchor !== 'undefined' ) {
			url = url.split( '#' );
			url = url[0] + '#' + _anchor;
		}
		console.log( 'jumping to ' + url );
		window.location.href = url;
	},

	/**
	 *	字符串字节数
	 */
	len : function (str, encode){
		var encodelen = 2;
		if(encode && encode.substr(0, 4).toLowerCase() === 'utf8'){
			encodelen = 3;
		}
		var bytelen = 0;
		var chars = str.split('');
		for(i = 0; i < chars.length; i++){
			var urichar = encodeURI(chars[i]);
			bytelen += urichar.length == 1 ? 1 : urichar.length / 9 * encodelen;
		}
		return bytelen;
	},

	/**
	 * 自动调高度
	 */
	autoHeight: function(){
		$('[data-autoheight]').each(function(index, el) {
			if($(this).height() < $(this).data('autoheight')) return;
			var t = $(this).attr('title');
			if(typeof t === typeof undefined) t = '';
			else t = "\n"+t;
			$(this).data('height', $(this).height())
				.attr('title', 'Click to show all'+t)
				.css({
					'overflow-y': 'hidden',
					'display': 'block',
					'max-height': $(this).data('autoheight')
				})
				.after('<div class="collapsHeightTip"></div>');
		});
		$('[data-autoheight]').click(function(event) {
			console.log( 'autoheight clicked' );
			if ( getSelection().toString() ) {
				console.log( 'aborted due to getSelection' );
				return; //select not click
			}
			console.log( 'ori height:' + $(this).height() );
			var open = $(this).height() == $(this).data('autoheight')
			$(this).css('max-height', $(this).data( open ? 'height' : 'autoheight'));
			$(this).next('.collapsHeightTip').toggle(!open);
		});
	},

	/**
	 *	图片刷新大小
	 */
	resize : function (a, b, c){
		var w = a.width;
		var h = a.height;
		var scale_get = w/h;
		var scale_set = b/c;
		if (w > b && h > c){
			if (scale_get >= scale_set){
				a.style.width = b + "px";
			}else{
				a.style.height = c + "px";
			}
		}else if(w < b && h < c){
		}else if(w >= b){
			a.style.width = b + "px";
		}else if(h <= c){
			a.style.height = c + "px";
		}
	},

	/**
	 *	按键值
	 */
	keycode : function (num){
		var num = num || 13;//默认回车
		var code = window.event ? event.keyCode : event.which;
		if(num == code) return true;
		if ( num == 47 && code == 191 ) {
			return true;
		}
		return false;
	},

	/**
	 *	验证码显示
	 */
	secode : function (){
		if( ! $('[secode]').length ) return ;

		$.getScript('https://www.google.com/recaptcha/api.js');

		$('[secode]').each(function(){
			var secid = $(this).attr('secode');
			//写入验证码
			str = '<div class="g-recaptcha" data-sitekey="'+_google_sitekey+'"></div>';
			$(this).html(str);
		});
	},

	/**
	 *	倒计时开始
	 */
	clock : function (){
		$('[clock]').each(function(){
			var i = uNum ++;
			$(this).attr('clockI', i);
			var num = $(this).attr('clock');
			var refresh = $(this).attr('clockR') || 0;
			eval("window.clockSec" + i + " = num;");
			eval("window.clockObj" + i + " = window.setInterval('m.__clockTriger(\"" + i + "\", " + refresh + ");', 1000);");
		});
	},

	/**
	 *	**private** 倒计时触发
	 */
	__clockTriger : function (theid, refresh) {
		var refresh = refresh || 0;
		eval("var tmp = clockSec" + theid);
		if(tmp > 0) {
			tmp -= 1;
			eval("clockSec" + theid + " -= 1");
			var second = Math.floor(tmp % 60);		// 计算秒
			var minite = Math.floor((tmp / 60) % 60);	//计算分
			var hour = Math.floor((tmp / 3600) % 24);	//计算小时
			var day = Math.floor((tmp / 3600) / 24);	//计算天
			var str = '';
			if(day) str += day + "D";
			if(hour) str += hour + "h";
			if(minite) str += minite + "m";
			str += second + "s";
			$("[clockI=" + theid + "]").html(str);
		}else{//剩余时间小于或等于0的时候，就停止间隔函数
			eval("window.clearInterval(clockObj" + theid + ");");
			if(refresh) window.location.href = window.location.href;
			else $("[clockI=" + theid + "]").html('Ended');
		}
	},

	videoControl : function(){
		$("video").hover(function(event) {
			if(event.type === "mouseenter") {
				$(this).attr("controls", "");
			} else if(event.type === "mouseleave") {
				$(this).removeAttr("controls");
			}
		});
	}
};

