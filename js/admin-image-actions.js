jQuery( document ).ready( function ( $ ) {

    /**
     * wp_localize_script object: iwImageActionArgs
     *
     * Params:
     *
     * _nonce
     * __applied_none => 	'Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.'
     * __applied_one => 	'Watermark was succesfully applied to 1 image.
     * __applied_multi => 	'Watermark was succesfully applied to %s images.'
     * __removed_none => 	'Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.'
     * __removed_one => 	'Watermark was succesfully removed from 1 image.'
     * __removed_multi => 	'Watermark was succesfully removed from %s images.'
     * __skipped => 		'Skipped files'
     * __running => 		'Bulk action is currently running, please wait.'
     * __dismiss => 		'Dismiss this notice.' // Wordpress default string
     *
     */

    watermarkImageActions = {
	running: false,
	action_location: '',
	action: '',
	response: '',
	selected: [ ],
	successCount: 0,
	skippedCount: 0,
	init: function () {

	    // Normal (list) mode
	    $( document ).on( 'click', '.bulkactions input#doaction, .bulkactions input#doaction2', function ( e ) {
		// Get the selected bulk action
		action = $( this ).parent().children( 'select' ).val();

		if ( !iwImageActionArgs.backup_image && action === 'removewatermark' ) {
		    return;
		}

		// Validate action
		if ( 'applywatermark' == action || 'removewatermark' == action ) {
		    // Stop default
		    e.preventDefault();

		    // Is this script running?
		    if ( false === watermarkImageActions.running ) {
			// No! set it on running
			watermarkImageActions.running = true;

			// store current action
			watermarkImageActions.action = action;

			// store current location where the action was fired
			watermarkImageActions.action_location = 'upload-list';

			// store selected attachment id's
			$( '.wp-list-table .check-column input:checkbox:checked' ).each( function () {
			    watermarkImageActions.selected.push( $( this ).val() );
			} );

			// remove current notices
			$( '.iw-notice' ).slideUp( 'fast', function () {
			    $( this ).remove();
			} );

			// begin the update!
			watermarkImageActions.post_loop();

		    } else {
			// script is running, can't run two at the same time
			watermarkImageActions.notice( 'iw-notice error', iwImageActionArgs.__running, false );
		    }

		}
	    } );

	    // Media modal or edit attachment screen mode
	    $( document ).on( 'click', '#image_watermark_buttons a.iw-watermark-action', function ( e ) {
		// Get the selected bulk action
		action = $( this ).attr( 'data-action' );
		id = $( this ).attr( 'data-id' );

		// Validate action
		if ( 'applywatermark' == action || 'removewatermark' == action && !isNaN( id ) ) {
		    // Stop default
		    e.preventDefault();

		    // store current action
		    watermarkImageActions.action = action;

		    // Is this script running?
		    if ( false === watermarkImageActions.running ) {
			// No! set it on running
			watermarkImageActions.running = true;

			// store current action
			watermarkImageActions.action = action;

			// store current location where the action was fired
			if ( $( this ).parents( '.media-modal ' ).length ) {
			    watermarkImageActions.action_location = 'media-modal';
			} else {
			    watermarkImageActions.action_location = 'edit';
			}

			// store attachment id
			watermarkImageActions.selected.push( id );

			// remove current notices
			$( '.iw-notice' ).slideUp( 'fast', function () {
			    $( this ).remove();
			} );

			// begin the update!
			watermarkImageActions.post_loop();
		    } else {
			// script is running, can't run two at the same time
			watermarkImageActions.notice( 'iw-notice error', iwMediaModal.__running, false );
		    }
		}
	    } );

	    // Since these are added later we'll need to enable dismissing again
	    $( document ).on( 'click', '.iw-notice.is-dismissible .notice-dismiss', function () {
		$( this ).parents( '.iw-notice' ).slideUp( 'fast', function () {
		    $( this ).remove();
		} );
	    } );

	},
	post_loop: function () {
	    // do we have selected attachments?
	    if ( watermarkImageActions.selected.length ) {

		// take the first id
		id = watermarkImageActions.selected[ 0 ];

		// check for a valid ID (needs to be numeric)
		if ( !isNaN( id ) ) {

		    // Show loading icon
		    watermarkImageActions.row_image_feedback( 'loading', id );

		    // post data
		    data = {
			'_iw_nonce': iwImageActionArgs._nonce,
			'action': 'iw_watermark_bulk_action',
			'iw-action': watermarkImageActions.action,
			'attachment_id': id
		    };

		    if ( watermarkImageActions.action_location == 'upload-list' ) {
			watermarkImageActions.scroll_to( '#post-' + id, 'bottom' );
		    }

		    // the ajax post!
		    $.post( ajaxurl, data, function ( response ) {
			// show result
			watermarkImageActions.result( response, id );
			// remove this ID/key from the selected attachments
			watermarkImageActions.selected.splice( 0, 1 );
			// Redo this function
			watermarkImageActions.post_loop();

			$( '.iw-overlay' ).first().each( function () {
			    $( this ).fadeOut( 'fast', function () {
				$( this ).remove();

				if ( response.data === 'watermarked' ) {
				    $( '#image_watermark_buttons .value' ).append( '<span class="dashicons dashicons-yes" style="font-size: 24px;float: none;min-width: 28px;padding: 0;margin: 0; display: none;"></span>' );
				    $( '#image_watermark_buttons .value .dashicons' ).fadeIn( 'fast' );
				} else if ( response.data === 'watermarkremoved' ) {
				    $( '#image_watermark_buttons .value' ).append( '<span class="dashicons dashicons-yes" style="font-size: 24px;float: none;min-width: 28px;padding: 0;margin: 0; display: none;"></span>' );
				    $( '#image_watermark_buttons .value .dashicons' ).fadeIn( 'fast' );
				}

				$( '#image_watermark_buttons .value .dashicons' ).delay( 1500 ).fadeOut( 'fast', function () {
				    $( this ).remove();
				} );
			    } );
			} );
		    } );

		} else {
		    // ID is not valid so remove this key from the selected attachments
		    watermarkImageActions.selected.splice( 0, 1 );
		    // Redo this function
		    watermarkImageActions.post_loop();
		}
	    } else {
		// All is done, reset this "class"
		watermarkImageActions.reset();
	    }
	},
	result: function ( response, id ) {

	    // Was the ajax post successful?
	    if ( true === response.success ) {

		// defaults
		var type = false;
		var message = '';
		var overwrite = true;
		// store response data
		watermarkImageActions.response = response.data;

		// Check what kind of action is done (watermarked, watermarkremoved or skipped)
		switch ( response.data ) {
		    case 'watermarked':
			// The css classes for the notice
			type = 'iw-notice updated iw-watermarked';
			// another successful update
			watermarkImageActions.successCount += 1;
			// did we have more success updates?
			if ( 1 < watermarkImageActions.successCount ) {
			    //yes
			    message = iwImageActionArgs.__applied_multi.replace( '%s', watermarkImageActions.successCount );
			} else {
			    //no
			    message = iwImageActionArgs.__applied_one;
			}
			// update the row feedback
			watermarkImageActions.row_image_feedback( 'success', id );
			// reload the image
			watermarkImageActions.reload_image( id );
			break;
		    case 'watermarkremoved':
			// The css classes for the notice
			type = 'iw-notice updated iw-watermarkremoved';
			// another successful update
			watermarkImageActions.successCount += 1;
			// did we have more success updates?
			if ( 1 < watermarkImageActions.successCount ) {
			    //yes
			    message = iwImageActionArgs.__removed_multi.replace( '%s', watermarkImageActions.successCount );
			} else {
			    //no
			    message = iwImageActionArgs.__removed_one;
			}
			// update the row feedback
			watermarkImageActions.row_image_feedback( 'success', id );
			// reload the image
			watermarkImageActions.reload_image( id );
			break;
		    case 'skipped':
			// The css classes for the notice
			type = 'iw-notice error iw-skipped';
			// another skipped update
			watermarkImageActions.skippedCount += 1;
			// adjust the message with the number of skipped updates
			message = iwImageActionArgs.__skipped + ': ' + watermarkImageActions.skippedCount;
			// update the row feedback
			watermarkImageActions.row_image_feedback( 'error', id );
			break;
		    default:
			// The css classes for the notice
			type = 'iw-notice error iw-message';
			// The error message
			message = response.data;
			// update the row feedback
			watermarkImageActions.row_image_feedback( 'error', id );
			// This can be anything so don't overwrite
			overwrite = false;
			break;
		}
		if ( false !== type ) {
		    // we have a valid terun type, show the notice! (Overwrite current notice if available)
		    watermarkImageActions.notice( type, message, overwrite );
		}
	    } else {
		// No success...
		watermarkImageActions.notice( 'iw-notice error', response.data, false );
		// update the row feedback
		watermarkImageActions.row_image_feedback( 'error', id );
	    }

	},
	row_image_feedback: function ( type, id ) {
	    var css = { },
		cssinner = { },
		container_selector;

	    switch ( watermarkImageActions.action_location ) {
		case 'upload-list':
		    container_selector = '.wp-list-table #post-' + id + ' .media-icon';
		    css = {
			display: 'table',
			width: $( container_selector ).width() + 'px',
			height: $( container_selector ).height() + 'px',
			top: '0',
			left: '0',
			position: 'absolute',
			font: 'normal normal normal dashicons',
			background: 'rgba(255,255,255,0.75)',
			content: ''
		    };
		    cssinner = {
			'vertical-align': 'middle',
			'text-align': 'center',
			display: 'table-cell',
			width: '100%',
			height: '100%',
		    };
		    break;

		case 'edit':
		    container_selector = '.wp_attachment_holder #thumbnail-head-' + id + '';
		    css = {
			display: 'table',
			width: $( container_selector + ' img' ).width() + 'px',
			height: $( container_selector + ' img' ).height() + 'px',
			top: '0',
			left: '0',
			position: 'absolute',
			font: 'normal normal normal dashicons',
			background: 'rgba(255,255,255,0.75)',
			content: ''
		    };
		    cssinner = {
			'vertical-align': 'middle',
			'text-align': 'center',
			display: 'table-cell',
			width: '100%',
			height: '100%',
		    };
		    break;

		case 'media-modal':
		    container_selector = '.media-modal #image_watermark_buttons[data-id="' + id + '"] .value';
		    css = {
			'float': 'none'
		    };
		    cssinner = {
			'float': 'none'
		    };
		    break;

		default:
		    return false;
	    }

	    // css rules
	    $( container_selector ).css( 'position', 'relative' );

	    // Only create the element if it doesn't exist
	    if ( !$( container_selector + ' .iw-overlay' ).length ) {
		$( container_selector ).append( '<span class="iw-overlay"><span class="iw-overlay-inner"></span></span>' );
	    }

	    // Overwrite with new data
	    $( container_selector + ' .iw-overlay' ).css( css );
	    $( container_selector + ' .iw-overlay .iw-overlay-inner' ).css( cssinner );
	    $( container_selector + ' .iw-overlay .iw-overlay-inner' ).html( '<span class="spinner is-active"></span>' );

	    if ( watermarkImageActions.action_location === 'media-modal' ) {
		$( container_selector + ' .iw-overlay .iw-overlay-inner .spinner' ).css( { 'float': 'none', 'padding': 0, 'margin': '-4px 0 0 10px' } );
	    }
	},
	notice: function ( type, message, overwrite ) {

	    if ( watermarkImageActions.action_location === 'media-modal' ) {
		return;
	    }

	    type += ' notice is-dismissible';

	    // Get the prefix based on the action location
	    switch ( watermarkImageActions.action_location ) {
		case 'upload-list':
		    prefix = '.wrap > h1';
		    break;
		default:
		    prefix = '#image_watermark_buttons';
		    break;
	    }

	    // Overwrite the current notice?
	    if ( true === overwrite ) {
		selector = false;

		// Get the selector based on the response
		switch ( watermarkImageActions.response ) {
		    case 'watermarked':
			selector = '.iw-notice.iw-watermarked';
			break;
		    case 'watermarkremoved':
			selector = '.iw-notice.iw-watermarkremoved';
			break;
		    case 'skipped':
			selector = '.iw-notice.iw-skipped';
			break;
		}
		// Do we have a selector and can we find it? If not, just create a new notice
		if ( selector && $( '.wrap ' + selector + ' > p' ).length ) {

		    // Get the selector based on the action location (not not forget the ending space)
		    switch ( watermarkImageActions.action_location ) {
			case 'upload-list':
			    prefix = '.wrap ';
			    break;
			default:
			    prefix = '#image_watermark_buttons ';
			    break;
		    }
		    $( prefix + selector + ' > p' ).html( message );
		} else {
		    $( prefix ).after( '<div class="' + type + '" style="display: none;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwImageActionArgs.__dismiss + '</span></button></div>' );
		    $( '.iw-notice' ).slideDown( 'fast' );
		}
	    } else {
		// create a new notice
		$( prefix ).after( '<div class="' + type + '" style="display: none;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwImageActionArgs.__dismiss + '</span></button></div>' );
		$( '.iw-notice' ).slideDown( 'fast' );
	    }

	},
	reset: function () {
	    watermarkImageActions.running = false;
	    watermarkImageActions.action = '';
	    watermarkImageActions.response = '';
	    watermarkImageActions.selected = [ ];
	    watermarkImageActions.successCount = 0;
	    watermarkImageActions.skippedCount = 0;

	    // remove the overlay
	    setTimeout( function () {
		$( '.iw-overlay' ).each( function () {
		    $( this ).fadeOut( 'fast', function () {
			$( this ).remove();
		    } );
		} );
	    }, 100 );
	},
	reload_image: function ( id ) {
	    // reload the images
	    time = new Date().getTime();
	    selector = false;
	    // Get the selector based on the action location
	    switch ( watermarkImageActions.action_location ) {
		case 'upload-list':
		    selector = '.wp-list-table #post-' + id + ' .image-icon img';
		    break;
		case 'media-modal':
		    selector = '.attachment-details[data-id="' + id + '"] img, .attachment[data-id="' + id + '"] img';
		    break;
		case 'edit':
		    selector = '.wp_attachment_holder img';
		    break;
	    }

	    if ( selector ) {
		image = $( selector );
		image.each( function () {
		    // Remove the responsive metadata, this prevents reloading the image
		    $( this ).removeAttr( 'srcset' );
		    $( this ).removeAttr( 'sizes' );
		    // Reload the image (actually a browser hack by adding a time parameter to the image)
		    $( this ).attr( 'src', watermarkImageActions.replace_url_param( $( this ).attr( 'src' ), 't', time ) );
		} );
	    }
	},
	rotate_icon: function ( icon ) {
	    // This function accepts selectors and objects
	    if ( typeof icon == 'string' ) {
		icon = $( icon );
	    }
	    // Check for the length of the selected object.
	    if ( $( icon.selector ).length ) {
		// Set rotation to 0
		icon.css( {
		    '-webkit-transform': 'rotate(0deg)',
		    '-ms-transform': 'rotate(0deg)',
		    'transform': 'rotate(0deg)',
		    'borderSpacing': '0',
		} );
		// Do animation (one rotation)
		icon.animate(
		    { borderSpacing: 360 },
		    {
			duration: 1000,
			step: function ( now, fx ) {
			    $( this ).css( '-webkit-transform', 'rotate(' + now + 'deg)' );
			    $( this ).css( '-ms-transform', 'rotate(' + now + 'deg)' );
			    $( this ).css( 'transform', 'rotate(' + now + 'deg)' );
			    if ( now == 360 ) {
				// Animation finished, stop loop and restart
				icon.stop();
				watermarkImageActions.rotate_icon( icon );
			    }
			},
		    }
		);
	    }
	},
	replace_url_param: function ( url, paramName, paramValue ) {
	    var pattern = new RegExp( '\\b(' + paramName + '=).*?(&|$)' );
	    if ( url.search( pattern ) >= 0 ) {
		return url.replace( pattern, '$1' + paramValue + '$2' );
	    }
	    return url + ( url.indexOf( '?' ) > 0 ? '&' : '?' ) + paramName + '=' + paramValue;
	},
	scroll_to: function ( elementSelector, verticalTarget ) {

	    var offset = $( elementSelector ).offset();
	    var offsetTop = offset.top;

	    // If the element it above the current viewport, scroll to it
	    if ( offset.top < $( window ).scrollTop() ) {
		$( window ).scrollTop( offsetTop );
		return; // No further actions needed
	    }

	    var windowTopOffset = $( window ).scrollTop();
	    var windowBottomOffset = windowTopOffset + $( window ).outerHeight();

	    switch ( verticalTarget ) {
		case 'top':
		    offsetTop = offsetTop - $( elementSelector ).outerHeight();
		    break;
		case 'bottom':
		    if ( offset.top < windowBottomOffset ) {
			return; // The element is in the viewport
		    }
		    offsetTop = offsetTop - $( window ).outerHeight();
		    offsetTop = offsetTop + $( elementSelector ).outerHeight();
		    break;
		case 'center':
		    if ( offsetTop < windowBottomOffset && offsetTop >= windowTopOffset ) {
			return; // The element is in the viewport
		    }
		    offsetTop = offsetTop - ( $( window ).outerHeight() / 2 );
		    offsetTop = offsetTop + ( $( elementSelector ).outerHeight() / 2 );
		    break;
	    }

	    $( window ).scrollTop( offsetTop );
	}
    };

    // We need that nonce!
    if ( typeof iwImageActionArgs._nonce != 'undefined' ) {
	watermarkImageActions.init();
    }

} );
