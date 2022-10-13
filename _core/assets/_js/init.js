var vlogin = 0;

$(function(){
	m.init();

	// Backend filter dropdown handler
	$( '#filter_dropdown_handler' ).click( toggle_filter );
	$('#filter_dropdown').click(function( e ){
		// e.preventDefault();
		e.stopPropagation();
	});

	$( '.search_field' ).focus( function(){
		$( this ).animate( { width: "+=" + ( $(this).data( 'incr' ) ?? 70 ) }, 200, function(){} );
	}).blur( function(){
		$( this ).animate( { width: "-=" + ( $(this).data( 'incr' ) ?? 70 ) }, 200, function(){} );
	});

	$( document ).click(function(){
		auto_accesskey_menu();
	});
	$( '#curr_cat' ).click(function(){
		auto_accesskey_menu();
	});

	if ( $( '[data-tab]' ).length > 0 ) {
		// var tab_curr = document.cookie.replace(/(?:(?:^|.*;\s*)tab\s*\=\s*([^;]*).*$)|^.*$/, "$1");
		var tab_curr = window.location.hash.substr( 1 );
		if ( ! tab_curr ) {
			tab_curr = $('[data-tab]').first().data('tab');
		}
		display_tab( tab_curr );

		$( '.tabs ul li, a[data-tab]' ).click(function(){
			var curr = $( this ).data( 'tab' );
			// document.cookie = 'tab=' + curr;
			window.location.hash = '#' + curr;
			display_tab( curr );
		});
	}

	// Batch operation
	if ( $('input[data-batch]').length > 0 ) {
		batch_operation();
	}

});

/**
 * Batch operation hooks
 */
function batch_operation() {
	$('input[data-batch]').click(function(e){
		console.log( '--1--input batch clicked' );
		var batch = $(this).data('batch');
		batch_click_trigger(batch);
	});
	$('body').click(function(e){
		console.log( '--3--body clicked' );
	});

	$('body').on('click', 'input[data-batch], input[data-batch-all]', function(e){console.log('e.target',e.target);
		if ( $(this).data('batch-all') ) {
			var ck = $(this).prop('checked');
			console.log('--1--checked all clicked', ck);
			var batch = $(this).data('batch-all');
			$('[type="checkbox"][data-batch="'+batch+'"]:enabled').prop('checked', ck);
		}
		else {

		}
		console.log( 'batch row chosen [target] ' + batch );

		batch_click_trigger(batch);

		console.log( 'body click executed' );
		// if ( $(this).data('batch') ) e.stopPropagation();
	});

	// avoid clicking link caused checkbox ticking status reverted
	$('input[data-batch]').parents( 'tr' ).find( 'a' ).click( function(e) {
		e.stopPropagation();
	} );

	$( 'tr[data-batch]' ).click( function(e) {
		var selection = window.getSelection(); // No select any text yet
		if ( selection == 0 ) {

			console.log('--2--tr batch row clicked',$(this).find( 'input[data-batch]' ).is(':checked'));

			$(this).find( 'input[data-batch]' ).click();

			console.log('tr batch row after clicked',$(this).find( 'input[data-batch]' ).is(':checked'));
			// e.stopPropagation();
		}
	} );

	$('[data-batch_btn]').click(function(){
		var ids = '';
		$('input[data-batch="'+$(this).data('batch_btn')+'"]:checked').each( function(i) {
			ids += ( ids ? ',' : '' ) + $(this).val();
		} );

		var src = $(this).data( 'action' ) + '&ids=' + ids;

		if ( $(this).data( 'target' ) == 'modal' ) {
			$('#modalIframe').attr( 'src', src );
			$('#modal').modal('show');
		}
		else if ( $(this).data( 'target' ) == 'window' ) {
			window.open( src );
		}
		else {
			m.j( src );
		}

	});

}

/**
 * Backend batch checkbox click effect
 */
function batch_click_trigger(batch) {
	// Batch btn count
	$('[data-batch_count="'+batch+'"]').html( $('input[data-batch="'+batch+'"]:checked:enabled').length ? '(' + $('input[data-batch="'+batch+'"]:checked:enabled').length + ')' : '' );
	// Batch btn ability
	$('[data-batch_btn="'+batch+'"]').prop( 'disabled', $('input[data-batch="'+batch+'"]:checked').length == 0 )
		.toggleClass( 'btn-secondary', $('input[data-batch="'+batch+'"]:checked').length == 0 )
		.toggleClass( 'btn-primary', $('input[data-batch="'+batch+'"]:checked').length != 0 );

	// Rows highlight
	$('input[data-batch="'+batch+'"]:not(:checked)').parents( 'tr' ).removeClass( 'tr_selected' );
	$('input[data-batch="'+batch+'"]:checked').parents( 'tr' ).addClass( 'tr_selected' );
}

/**
 * Backend filter toggle
 */
function toggle_filter() {
	if ( $( '.filter_dropdown_list' ).is( ':visible' ) ) {
		m.cover_hide();
	}
	else {
		$( '#filter_dropdown' ).toggleClass( 'active' );
		$( '.filter_dropdown_list' ).toggle();
		$( '.i_dropdown' ).toggleClass( 'fa-sort-asc fa-sort-desc' );
		m.cover_show('.filter_dropdown_list', function(){
			$( '#filter_dropdown' ).removeClass( 'active' );
			$( '.i_dropdown' ).toggleClass( 'fa-sort-asc fa-sort-desc' );
		} );
	}
}

/**
 * Show tabs
 */
function display_tab( curr ) {
	$( '.tab-content' ).hide();
	$( '.tabs ul li' ).removeClass( 'active' );
	$( 'li[data-tab="'+ curr +'"]' ).addClass( 'active' );
	$( '[target=tab' + curr + ']' ).show();
	$( '.slider' ).css( 'transform', 'translateX(' + ( ( curr - 1 ) * 100 ) + '%)' );
}

function auto_accesskey_menu() {return;
	setTimeout(function(){
		// Switch 1-9 shortcuts
		$(document).off('keydown');
		var visible = $( '#cat_list' ).is( ':visible' );
		var i = 0;
		const cat_accesskeys = [ 'Z', 'X', 'C', 'V', 'B' ];
		$( '#cat_list a' ).each(function(){
			var curr_accesskey = cat_accesskeys[ i ];
			if ( visible ) {
				$( '[accesskey=' + curr_accesskey + ']' ).removeAttr( 'accesskey' ).attr( 'accesskey2', curr_accesskey );
				$(this).attr( 'accesskey', curr_accesskey );
			}
			else {
				$(this).removeAttr( 'accesskey' );
				// $( '[accesskey2=' + curr_accesskey + ']' ).removeAttr( 'accesskey2' ).attr( 'accesskey', curr_accesskey );
			}
			i++;
		});
		i = 1;
		$( '#menu_list a' ).each(function(){
			if ( i > 9 ) return;
			if ( visible ) {
				$( '[accesskey=' + i + ']' ).removeAttr( 'accesskey' ).attr( 'accesskey2', i );
				$(this).attr( 'accesskey', i );
			}
			else {
				$(this).removeAttr( 'accesskey' );
				// $( '[accesskey2=' + i + ']' ).removeAttr( 'accesskey2' ).attr( 'accesskey', i );
			}
			i++;
		});
		m.accesskey();
	}, 10 );
}


Object.map = function(obj, fn, ctx) {
	const ret = [];
	for ( let k of Object.keys( obj ) ) {
		ret.push( fn( k, obj[k] ) );
	}
	return ret;
};