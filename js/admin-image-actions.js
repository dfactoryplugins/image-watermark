
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
		selected: [],
		successCount: 0,
		skippedCount: 0,

		init: function() {

			// Normal (list) mode
			$(document).on('click', '.bulkactions input#doaction', function(e) {
				// Get the selected bulk action
				action = $(this).parent().children('select').val();

				// Validate action
				if ( action == 'applywatermark' || action == 'removewatermark' ) {
					// Stop default
					e.preventDefault();

					// Is this script running?
					if ( watermarkImageActions.running === false ) {
						// No! set it on running
						watermarkImageActions.running = true;

						// store current action
						watermarkImageActions.action = action;

						// store current location where the action was fired
						watermarkImageActions.action_location = 'upload-list';

						// store selected attachment id's
						$('.wp-list-table .check-column input:checkbox:checked').each(function(){
							watermarkImageActions.selected.push( $(this).val() );
						});

						// begin the update!
						watermarkImageActions.post_loop();

					} else {
						// script is running, can't run two at the same time
						watermarkImageActions.notice( 'error iw-notice', iwImageActionArgs.__running, false );
					}

				}
			});

			// Media modal mode
			$(document).on('click', '#image_watermark_buttons button.iw-watermark-action', function(e) {
				// Get the selected bulk action
				action = $(this).attr('data-action');
				id = $(this).attr('data-id');

				// Validate action
				if ( action == 'applywatermark' || action == 'removewatermark' && ! isNaN( id ) ) {
					// Stop default
					e.preventDefault();

					// store current action
					watermarkImageActions.action = action;

					// Is this script running?
					if ( watermarkImageActions.running === false ) {
						// No! set it on running
						watermarkImageActions.running = true;

						// store current action
						watermarkImageActions.action = action;

						// store current location where the action was fired
						if ( $(this).parents('.media-modal ').length ) {
							watermarkImageActions.action_location = 'media-modal';
						} else {
							watermarkImageActions.action_location = 'edit';
						}

						// store attachment id
						watermarkImageActions.selected.push( id );

						// begin the update!
						watermarkImageActions.post_loop();

					} else {
						// script is running, can't run two at the same time
						watermarkImageActions.notice( 'error iw-notice', iwMediaModal.__running, false );
					}

				}
			});

			// Since these are added later we'll need to enable dismissing again
			$(document).on('click', '.iw-notice.is-dismissible .notice-dismiss', function(){
				$(this).parents('.iw-notice').fadeOut('fast', function(){ 
					$(this).slideUp('fast', function() {	
						$(this).remove(); 
					});
				});
			});

		},

		post_loop: function() {
			// do we have selected attachments?
			if ( watermarkImageActions.selected.length ) {

				id = watermarkImageActions.selected[ 0 ];

				// check for a valid ID (needs to be numeric)
				if ( ! isNaN( id ) ) {

					// Show loading icon
					watermarkImageActions.row_image_feedback( 'loading', id );

					// post data
					data = {
						'_iw_nonce': iwImageActionArgs._nonce,
						'action': 'iw_watermark_bulk_action',
						'iw-action': watermarkImageActions.action,
						'attachment_id': id
					};

					// the ajax post!
					$.post( ajaxurl, data, function(response) {
						// show result
						watermarkImageActions.result(response, id);
						// remove this ID/key from the selected attachments
						watermarkImageActions.selected.splice(0,1);
						// Redo this function
						watermarkImageActions.post_loop();
					} );

				} else {
					// ID is not valid so remove this key from the selected attachments
					watermarkImageActions.selected.splice(0,1);
					// Redo this function
					watermarkImageActions.post_loop();
				}
			} else {
				// All is done, reset this function
				watermarkImageActions.reset();
			}
		},

		result: function( response, id ) {
			
			// Was the ajax post successful?
			if ( response.success === true ) {

				// defaults
				type = false;
				message = '';
				watermarkImageActions.response = response.data;

				// Check what kind of action is done (watermarked, watermarkremoved or skipped)
				switch ( response.data ) {
					case 'watermarked': 
						// The css classes for the notice
						type = 'updated iw-notice iw-watermarked';
						// another successful update
						watermarkImageActions.successCount += 1;
						// Do we have more success updates?
						if ( watermarkImageActions.successCount > 1 ) {
							//yes
							message = iwImageActionArgs.__applied_multi.replace('%s', watermarkImageActions.successCount);
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
						type = 'updated iw-notice iw-watermarkremoved';
						// another successful update
						watermarkImageActions.successCount += 1;
						// Do we have more success updates?
						if ( watermarkImageActions.successCount > 1 ) {
							//yes
							message = iwImageActionArgs.__removed_multi.replace('%s', watermarkImageActions.successCount);
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
						type = 'error iw-notice iw-skipped';
						// another skipped update
						watermarkImageActions.skippedCount += 1;
						// adjust the message with the number of skipped updates
						message = iwImageActionArgs.__skipped + ': ' + watermarkImageActions.skippedCount;
						// update the row feedback
						watermarkImageActions.row_image_feedback( 'error', id );
					break;
				}
				if ( type !== false ) {
					// we have a valid terun type, show the notice! (Overwrite current notice if available)
					watermarkImageActions.notice( type, message, true );
				}
			} else {
				// No success...
				watermarkImageActions.notice( 'error iw-notice', response.data, false );
				// update the row feedback
				watermarkImageActions.row_image_feedback( 'error', id );
			}

		},

		row_image_feedback: function( type, id ) {

			css = cssinner = iconcss = {};
			switch ( watermarkImageActions.action_location ) {
				case 'upload-list': 
					container_selector = '.wp-list-table #post-'+id+' .image-icon';
					css = {
						display: 'table',
						width: $(container_selector).width() + 'px',
						height: $(container_selector).height() + 'px',
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
					iconcss = {
						'font-size': '50px',
						'line-height': 'normal',
						'vertical-align': 'middle',
						width: '50px',
						height: '50px',
					};
				break;
				case 'edit': 
					container_selector = '.wp_attachment_holder #thumbnail-head-'+id+'';
					css = {
						display: 'table',
						width: $(container_selector+' img').width() + 'px',
						height: $(container_selector+' img').height() + 'px',
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
					iconcss = {
						'font-size': '50px',
						'line-height': 'normal',
						'vertical-align': 'middle',
						width: '50px',
						height: '50px',
					};
				break;
				case 'media-modal':
					container_selector = '.media-modal #image_watermark_buttons[data-id="'+id+'"]';
					iconcss = {
						'font-size': '25px',
						'line-height': 'normal',
						'vertical-align': 'middle',
						width: '25px',
						height: '25px',
					};
				break;
				default:
					return false;
			}

			// css rules
			$(container_selector).css('position', 'relative');
			icon = '';
			rotate = false;
			
			// Type specific rules
			switch ( type ) {
				case 'loading':
					css.color = '#111';
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
				watermarkImageActions.rotate_icon( $(container_selector+' .iw-overlay .dashicons') );
			}

		},

		notice: function( type, message, overwrite ) {

			type += ' notice is-dismissible';

			// Get the prefix based on the action location
			switch ( watermarkImageActions.action_location ) {
				case 'upload-list':	prefix = '.wrap > h1'; break;
				default: prefix = '#image_watermark_buttons > h3'; break;
			}

			// Overwrite the current notice?
			if ( overwrite === true) {
				selector = false;

				// Get the selector based on the response
				switch ( watermarkImageActions.response ) {
					case 'watermarked': selector = '.iw-notice.iw-watermarked'; break;
					case 'watermarkremoved': selector = '.iw-notice.iw-watermarkremoved'; break;
					case 'skipped': selector = '.iw-notice.iw-skipped'; break;
				}
				// Do we have a selector and can we find it? If not, just create a new notice
				if ( selector && $('.wrap '+selector+' > p').length ) {

					// Get the selector based on the action location (not not forget the ending space)
					switch ( watermarkImageActions.action_location ) {
						case 'upload-list':	prefix = '.wrap '; break;
						default: prefix = '#image_watermark_buttons '; break;
					}
					$(prefix+selector+' > p').html( message );
				} else {
					$(prefix).after('<div class="' + type + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwImageActionArgs.__dismiss + '</span></button></div>');
				}
			} else {

				// create a new notice
				$(prefix).after('<div class="' + type + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwImageActionArgs.__dismiss + '</span></button></div>');
			}

		},

		reset: function() {
			watermarkImageActions.running = false;
			watermarkImageActions.action = '';
			watermarkImageActions.response = '';
			watermarkImageActions.selected = [];
			watermarkImageActions.successCount = 0;
			watermarkImageActions.skippedCount = 0;

			// remove the overlay
			setTimeout( function(){
				$('.iw-overlay').each( function(){
					$(this).fadeOut('slow', function(){ $(this).remove(); }); 
				});
			}, 1000 );
		},

		reload_image: function( id ) {
			// reload the images
			time = new Date().getTime();
			selector = false;
			// Get the selector based on the action location
			switch ( watermarkImageActions.action_location ) {
				case 'upload-list':	selector = '.wp-list-table #post-'+id+' .image-icon img'; break;
				case 'media-modal': selector = '.attachment-details[data-id="'+id+'"] img, .attachment[data-id="'+id+'"] img'; break;
				case 'edit': selector = '.wp_attachment_holder img'; break;
			}

			if ( selector ) {
				image = $( selector );
				image.each( function () {
					$(this).removeAttr('srcset');
					$(this).removeAttr('sizes');
					$(this).attr('src', watermarkImageActions.replace_url_param( $(this).attr('src'), 't', time ) );
					//$(this).attr('src', $(this).attr('src') + '?t=' + time );
				});
			}
		},

		rotate_icon: function( icon ) {
			// This function accepts selectors and objects
			if ( typeof icon == 'string') {
				icon = $( icon );
			}
			// Check for the length of the selected object.
			if ( $( icon.selector ).length ) {
				icon.css({
					'-webkit-transform': 'rotate(0deg)',
					'-ms-transform': 'rotate(0deg)',
					'transform': 'rotate(0deg)',
					'borderSpacing': '0',
				});
				icon.animate(
					{ borderSpacing: 360 }, 
					{ 
						duration: 1000,
						step: function(now, fx) {
					 		$(this).css('-webkit-transform', 'rotate('+now+'deg)');
					 		$(this).css('-ms-transform', 'rotate('+now+'deg)');
					 		$(this).css('transform', 'rotate('+now+'deg)');
					 		if (now == 360) {
					 			// Animation finished, stop loop and restart
					 			icon.stop();
					 			watermarkImageActions.rotate_icon( icon );
					 		}
						}, 
					}
				);
			}
		},

		replace_url_param: function( url, paramName, paramValue ) {
		    var pattern = new RegExp('\\b('+paramName+'=).*?(&|$)');
		    if(url.search(pattern)>=0){
		        return url.replace(pattern,'$1' + paramValue + '$2');
		    }
		    return url + (url.indexOf('?')>0 ? '&' : '?') + paramName + '=' + paramValue;
		},
	};

	if ( typeof iwImageActionArgs._nonce != 'undefined' ) {
		watermarkImageActions.init();
	}

} );