( function( $ ) {

	// ready event
	$( function() {
		$( '<option>' ).val( 'applywatermark' ).text( iwMediaArgs.applyWatermark ).appendTo( 'select[name="action"], select[name="action2"]' );

		if ( iwMediaArgs.backupImage === '1' )
			$( '<option>' ).val( 'removewatermark' ).text( iwMediaArgs.removeWatermark ).appendTo( 'select[name="action"], select[name="action2"]' );
	} );

} )( jQuery );