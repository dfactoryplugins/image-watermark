
jQuery( document ).ready( function ( $ ) {

	/**
	 * wp_localize_script object: iwBulkActionArgs
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

	watermarkBulkActions = {
		running: false,
		action: '',
		response: '',
		selected: [],
		successCount: 0,
		skippedCount: 0,

		init: function() {

			$(document).on('click', '.bulkactions input#doaction', function(e) {
				// Get the selected bulk action
				action = $(this).parent().children('select').val();

				// Validate action
				if ( action == 'applywatermark' || action == 'removewatermark' ) {
					// Stop default
					e.preventDefault();

					// store current action
					watermarkBulkActions.action = action;

					// Is this script running?
					if ( watermarkBulkActions.running === false ) {
						// No! set it on running
						watermarkBulkActions.running = true;

						// store selected attachment id's
						$('.wp-list-table .check-column input:checkbox:checked').each(function(){
							watermarkBulkActions.selected.push( $(this).val() );
						});

						// begin the update!
						watermarkBulkActions.post_loop();

					} else {
						// script is running, can't run two at the same time
						watermarkBulkActions.notice( 'error iw-notice', iwBulkActionArgs.__running, false );
					}

				}
			});

			// Since these are added later we'll need to enable dismissing again
			$(document).on('click', '.iw-notice.is-dismissible .notice-dismiss', function(){
				$(this).parents('.iw-notice').hide('fast', function(){ $(this).remove(); });
			});

		},

		post_loop: function() {
			// do we have selected attachments?
			if ( watermarkBulkActions.selected.length ) {

				id = watermarkBulkActions.selected[ 0 ];

				// check for a valid ID (needs to be numeric)
				if ( ! isNaN( id ) ) {

					// Show loading icon
					watermarkBulkActions.row_image_feedback( 'loading', id );

					// post data
					data = {
						'_iw_nonce': iwBulkActionArgs._nonce,
						'action': 'iw_watermark_bulk_action',
						'iw-action': watermarkBulkActions.action,
						'attachment_id': id
					};

					// the ajax post!
					$.post( ajaxurl, data, function(response) {
						// show result
						watermarkBulkActions.result(response, id);
						// remove this ID/key from the selected attachments
						watermarkBulkActions.selected.splice(0,1);
						// Redo this function
						watermarkBulkActions.post_loop();
					} );

				} else {
					// ID is not valid so remove this key from the selected attachments
					watermarkBulkActions.selected.splice(0,1);
					// Redo this function
					watermarkBulkActions.post_loop();
				}
			} else {
				// All is done, reset this function
				watermarkBulkActions.reset();
			}
		},

		result: function( response, id ) {
			
			// Was the ajax post successful?
			if ( response.success === true ) {

				// defaults
				type = false;
				message = '';
				watermarkBulkActions.response = response.data;

				// Check what kind of action is done (watermarked, watermarkremoved or skipped)
				switch ( response.data ) {
					case 'watermarked': 
						// The css classes for the notice
						type = 'updated iw-notice iw-watermarked';
						// another successful update
						watermarkBulkActions.successCount += 1;
						// Do we have more success updates?
						if ( watermarkBulkActions.successCount > 1 ) {
							//yes
							message = iwBulkActionArgs.__applied_multi.replace('%s', watermarkBulkActions.successCount);
						} else {
							//no
							message = iwBulkActionArgs.__applied_one;
						}
						// update the row feedback
						watermarkBulkActions.row_image_feedback( 'success', id );
						// reload the image
						watermarkBulkActions.reload_image( id );
					break;
					case 'watermarkremoved': 
						// The css classes for the notice
						type = 'updated iw-notice iw-watermarkremoved';
						// another successful update
						watermarkBulkActions.successCount += 1;
						// Do we have more success updates?
						if ( watermarkBulkActions.successCount > 1 ) {
							//yes
							message = iwBulkActionArgs.__removed_multi.replace('%s', watermarkBulkActions.successCount);
						} else {
							//no
							message = iwBulkActionArgs.__removed_one;
						}
						// update the row feedback
						watermarkBulkActions.row_image_feedback( 'success', id );
						// reload the image
						watermarkBulkActions.reload_image( id );
					break;
					case 'skipped': 
						// The css classes for the notice
						type = 'error iw-notice iw-skipped';
						// another skipped update
						watermarkBulkActions.skippedCount += 1;
						// adjust the message with the number of skipped updates
						message = iwBulkActionArgs.__skipped + ': ' + watermarkBulkActions.skippedCount;
						// update the row feedback
						watermarkBulkActions.row_image_feedback( 'error', id );
					break;
				}
				if ( type !== false ) {
					// we have a valid terun type, show the notice! (Overwrite current notice if available)
					watermarkBulkActions.notice( type, message, true );
				}
			} else {
				// No success...
				watermarkBulkActions.notice( 'error iw-notice', response.data, false );
				// update the row feedback
				watermarkBulkActions.row_image_feedback( 'error', id );
			}

		},

		row_image_feedback: function( type, id ) {
			container_selector = '.wp-list-table #post-'+id+' .image-icon';

			// css rules
			$(container_selector).css('position', 'relative');
			css = {
				display: 'table',
				width: '62px',
				height: '62px',
				top: '0',
				left: '0',
				position: 'absolute',
				font: 'normal normal normal dashicons',
				background: 'rgba(255,255,255,0.75)',
				content: '',
				color: '#111',
			};
			cssinner = {
				'vertical-align': 'middle',
				'text-align': 'center',
				display: 'table-cell',
				width: '100%',
				height: '100%',
			};
			icon = '';
			iconcss = {
				'font-size': '50px',
				'line-height': 'normal',
				'vertical-align': 'middle',
				width: '50px',
				height: '50px',
			};
			rotate = false;
			
			// Type specific rules
			switch ( type ) {
				case 'loading':
					icon = 'dashicons-update';
					rotate = true;
				break;
				case 'success': 
					css.color = '#46b450';
					icon = 'dashicons-yes';
				break;
				case 'error': 
					css.color = '#dc3232';
					icon = 'dashicons-no-alt';
				break;
			}

			// Only create the element if it doesn't exist
			if ( ! $(container_selector+' .iw-overlay').length ) {
				$(container_selector).append('<span class="iw-overlay"><span class="iw-overlay-inner"></span></span>');
				$(container_selector+' .iw-overlay .iw-overlay-inner').html('<span class="dashicons ' + icon + '"></span>');
			}
			// Overwrite with new data
			$(container_selector+' .iw-overlay').css(css);
			$(container_selector+' .iw-overlay .iw-overlay-inner').css(cssinner);
			$(container_selector+' .iw-overlay .iw-overlay-inner').html('<span class="dashicons '+icon+'"></span>');
			$(container_selector+' .iw-overlay .dashicons').css(iconcss);

			// Rotate the icon?
			if ( rotate ) {
				watermarkBulkActions.rotate_icon( $(container_selector+' .iw-overlay .dashicons') );
			}

		},

		notice: function( type, message, overwrite ) {

			type += ' notice is-dismissible';

			// Overwrite the current notice?
			if ( overwrite === true) {
				selector = false;
				// Get the selector based on the response
				switch ( watermarkBulkActions.response ) {
					case 'watermarked': selector = '.iw-notice.iw-watermarked'; break;
					case 'watermarkremoved': selector = '.iw-notice.iw-watermarkremoved'; break;
					case 'skipped': selector = '.iw-notice.iw-skipped'; break;
				}
				// Do we have a selector and can we find it? If not, just create a new notice
				if ( selector && $('.wrap '+selector+' > p').length ) {
					$('.wrap '+selector+' > p').html( message );
				} else {
					$('.wrap > h1 ').after('<div class="' + type + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwBulkActionArgs.__dismiss + '</span></button></div>');
				}
			} else {
				// create a new notice
				$('.wrap > h1 ').after('<div class="' + type + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwBulkActionArgs.__dismiss + '</span></button></div>');
			}

		},

		reset: function() {
			watermarkBulkActions.running = false;
			watermarkBulkActions.action = '';
			watermarkBulkActions.response = '';
			watermarkBulkActions.selected = [];
			watermarkBulkActions.successCount = 0;
			watermarkBulkActions.skippedCount = 0;

			// remove the overlay
			setTimeout( function(){
				$('.wp-list-table .image-icon .iw-overlay').each( function(){
					$(this).fadeOut('slow', function(){ $(this).remove(); }); 
				});
			}, 1000 );
		},

		reload_image: function( id ) {
			// reload the images
			time = new Date().getTime();
			image = $('.wp-list-table #post-'+id+' .image-icon img');
			image.attr('src', image.attr('src') + '?t=' + time );
			image.attr('srcset', '');
			image.attr('sizes', '');
		},

		rotate_icon: function( icon ) {
			icon.css({
				'-webkit-transform': 'rotate(0deg)',
				'-ms-transform': 'rotate(0deg)',
				'transform': 'rotate(0deg)',
				'borderSpacing': '0',
			});
			icon.animate(
				{ borderSpacing: 360 }, 
				{ 
					step: function(now, fx) {
				 		$(this).css('-webkit-transform', 'rotate('+now+'deg)');
				 		$(this).css('-ms-transform', 'rotate('+now+'deg)');
				 		$(this).css('transform', 'rotate('+now+'deg)');
					}, 
					duration: 'slow',
				}, 'linear', function(){
					watermarkBulkActions.rotate_icon( this );
				}
			);
		}
	};

	if ( typeof iwBulkActionArgs._nonce != 'undefined' ) {
		watermarkBulkActions.init();
	}

} );
