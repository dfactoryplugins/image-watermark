( function( $ ) {

	// ready event
	$( function() {
		$( document ).on( 'click', '#iw_install_imagein', function( e ) {
			var button = $( this );
			var notice = $( '#iw-upgrade-notice' );

			e.preventDefault();

			button.addClass( 'updating-message' );
			button.addClass( 'disabled' );
			button.text( iwDeprecation.installing );

			$.post( ajaxurl, {
				action: 'iw_install_imagein',
				nonce: iwDeprecation.nonce
			} ).done( function( response ) {
				if ( response.success ) {
					var html = iwDeprecation.strings[response.data.status];

					if ( response.data.success ) {
						notice.addClass( 'notice-success' ).removeClass( 'notice-warning' );

						html = html + ' ' + iwDeprecation.goToDashboard;
					} else
						notice.addClass( 'notice-error' ).removeClass( 'notice-warning' );

					$( '#iw-upgrade-notice' ).find( '.iw-upgrade-status' ).removeClass( 'hidden' ).html( html );
				} else {
					notice.addClass( 'notice-error' ).removeClass( 'notice-warning' );

					$( '#iw-upgrade-notice' ).find( '.iw-upgrade-status' ).removeClass( 'hidden' ).html( iwDeprecation.installationFailed );
				}
			} ).always( function( data ) {
				button.parent().remove();
			} ).fail( function() {
				notice.addClass( 'notice-error' ).removeClass( 'notice-warning' );

				$( '#iw-upgrade-notice' ).find( '.iw-upgrade-status' ).removeClass( 'hidden' ).html( iwDeprecation.installationFailed );
			} );
		} );
	} );

} )( jQuery );