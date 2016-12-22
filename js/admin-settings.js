jQuery( document ).ready( function ( $ ) {

    // enable watermark for
    $( document ).on( 'change', '#df_option_everywhere, #df_option_cpt', function () {
	if ( $( '#cpt-specific input[type=radio]:checked' ).val() === 'everywhere' ) {
	    $( '#cpt-select' ).fadeOut( 300 );
	} else if ( $( '#cpt-specific input[type=radio]:checked' ).val() === 'specific' ) {
	    $( '#cpt-select' ).fadeIn( 300 );
	}
    } );

    $( document ).on( 'click', '#reset_image_watermark_options', function () {
	return confirm( iwArgs.resetToDefaults );
    } );

    // size slider
    $( '#iw_size_span' ).slider( {
	value: $( '#iw_size_input' ).val(),
	min: 0,
	max: 100,
	step: 1,
	orientation: 'horizontal',
	slide: function ( e, ui ) {
	    $( '#iw_size_input' ).attr( 'value', ui.value );
	    $( '#iw_size_span' ).attr( 'title', ui.value );
	}
    } );

    // opacity slider
    $( '#iw_opacity_span' ).slider( {
	value: $( '#iw_opacity_input' ).val(),
	min: 0,
	max: 100,
	step: 1,
	orientation: 'horizontal',
	slide: function ( e, ui ) {
	    $( '#iw_opacity_input' ).attr( 'value', ui.value );
	    $( '#iw_opacity_span' ).attr( 'title', ui.value );
	}
    } );

    // quality slider
    $( '#iw_quality_span' ).slider( {
	value: $( '#iw_quality_input' ).val(),
	min: 0,
	max: 100,
	step: 1,
	orientation: 'horizontal',
	slide: function ( e, ui ) {
	    $( '#iw_quality_input' ).attr( 'value', ui.value );
	    $( '#iw_quality_span' ).attr( 'title', ui.value );
	}
    } );

    // quality slider
    $( '#iw_backup_quality_span' ).slider( {
	value: $( '#iw_backup_quality_input' ).val(),
	min: 0,
	max: 100,
	step: 1,
	orientation: 'horizontal',
	slide: function ( e, ui ) {
	    $( '#iw_backup_quality_input' ).attr( 'value', ui.value );
	    $( '#iw_backup_quality_span' ).attr( 'title', ui.value );
	}
    } );

} );
