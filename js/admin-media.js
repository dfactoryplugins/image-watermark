( function( $ ) {

	// ready event
	$( function() {
		$( '<option>' ).val( 'applywatermark' ).text( iwArgsMedia.applyWatermark ).appendTo( 'select[name="action"], select[name="action2"]' );

		if ( iwArgsMedia.backupImage )
			$( '<option>' ).val( 'removewatermark' ).text( iwArgsMedia.removeWatermark ).appendTo( 'select[name="action"], select[name="action2"]' );
	} );

} )( jQuery );