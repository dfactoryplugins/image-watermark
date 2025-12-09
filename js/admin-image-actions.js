( function( $ ) {

	// ready event
	$( function() {
		/**
		 * wp_localize_script object: iwArgsImageActions
		 *
		 * Params:
		 *
		 * _nonce
		 * __applied_none => 	'Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.'
		 * __applied_one => 	'Watermark was successfully applied to 1 image.
		 * __applied_multi => 	'Watermark was successfully applied to %s images.'
		 * __removed_none => 	'Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.'
		 * __removed_one => 	'Watermark was successfully removed from 1 image.'
		 * __removed_multi => 	'Watermark was successfully removed from %s images.'
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
			gridButtonsBound: false,
			gridFrame: null,
			gridButtons: null,
			gridDomInterval: null,
			init: function() {
				// Normal (list) mode
				$( document ).on( 'click', '.bulkactions input#doaction, .bulkactions input#doaction2', function( e ) {
					// Get the selected bulk action
					action = $( this ).parent().children( 'select' ).val();

					if ( ! iwArgsImageActions.backup_image && action === 'removewatermark' )
						return;

					// Validate action
					if ( 'applywatermark' === action || 'removewatermark' === action ) {
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
							$( '.wp-list-table .check-column input:checkbox:checked' ).each( function() {
								watermarkImageActions.selected.push( $( this ).val() );
							} );

							// remove current notices
							$( '.iw-notice' ).slideUp( 'fast', function() {
								$( this ).remove();
							} );

							// begin the update!
							watermarkImageActions.post_loop();
						} else {
							// script is running, can't run two at the same time
							watermarkImageActions.notice( 'iw-notice error', iwArgsImageActions.__running, false );
						}
					}
				} );

				// Media modal or edit attachment screen mode
				$( document ).on( 'click', '#image_watermark_buttons a.iw-watermark-action', function( e ) {
					// Get the selected bulk action
					action = $( this ).attr( 'data-action' );
					id = $( this ).attr( 'data-id' );

					// Validate action
					if ( 'applywatermark' === action || 'removewatermark' === action && ! isNaN( id ) ) {
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
							if ( $( this ).parents( '.media-modal ' ).length )
								watermarkImageActions.action_location = 'media-modal';
							else
								watermarkImageActions.action_location = 'edit';

							// store attachment id
							watermarkImageActions.selected.push( id );

							// remove current notices
							$( '.iw-notice' ).slideUp( 'fast', function() {
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
				$( document ).on( 'click', '.iw-notice.is-dismissible .notice-dismiss', function() {
					$( this ).parents( '.iw-notice' ).slideUp( 'fast', function() {
						$( this ).remove();
					} );
				} );

				// Media library grid (bulk select toolbar) mode
				watermarkImageActions.initGridMode();

			},
			initGridMode: function() {
				if ( watermarkImageActions.gridButtonsBound || typeof wp === 'undefined' || ! wp.media || typeof iwArgsMedia === 'undefined' ) {
					return;
				}

				var bindFrame = function( frame ) {
					watermarkImageActions.gridButtonsBound = true;
					watermarkImageActions.gridFrame = frame;

					frame.on( 'ready', watermarkImageActions.renderGridButtons );
					frame.on( 'select:activate', watermarkImageActions.renderGridButtons );
					frame.on( 'select:deactivate', watermarkImageActions.hideGridButtons );
					frame.on( 'selection:toggle selection:action:done library:selection:add', watermarkImageActions.updateGridButtonsState );

					watermarkImageActions.renderGridButtons();
				};

				var attemptBind = function() {
					var frame = wp.media && ( wp.media.frame || ( wp.media.frames && ( wp.media.frames.browse || wp.media.frames.manage ) ) );

					if ( frame && typeof frame.on === 'function' ) {
						bindFrame( frame );
						return true;
					}

					return false;
				};

				if ( ! attemptBind() ) {
					var interval = setInterval( function() {
						if ( attemptBind() ) {
							clearInterval( interval );
						}
					}, 300 );
				}

				// Fallback: watch for the DOM-based bulk select toggle.
				$( document ).on( 'click', '.select-mode-toggle-button', function() {
					watermarkImageActions.renderGridButtons();

					if ( watermarkImageActions.gridDomInterval ) {
						clearInterval( watermarkImageActions.gridDomInterval );
					}

					// Try a few times after the click in case the toolbar redraws.
					watermarkImageActions.gridDomInterval = setInterval( function() {
						watermarkImageActions.renderGridButtons();
					}, 400 );

					setTimeout( function() {
						if ( watermarkImageActions.gridDomInterval ) {
							clearInterval( watermarkImageActions.gridDomInterval );
							watermarkImageActions.gridDomInterval = null;
						}
					}, 3000 );
				} );

				// Update button state when selecting items in grid.
				$( document ).on( 'click', '.attachments-browser .attachment, .attachments-browser .attachment .check', function() {
					// Delay slightly to let core toggle selection classes first.
					setTimeout( watermarkImageActions.updateGridButtonsState, 50 );
				} );
			},
			ensureGridButtonsDom: function() {
				var $toolbar = ( function() {
					var $toolbars = $( '.media-frame.mode-grid .media-toolbar:visible' );

					if ( ! $toolbars.length )
						return null;

					var $withDelete = $toolbars.filter( function() {
						return $( this ).find( '.delete-selected-button' ).length;
					} );

					if ( $withDelete.length )
						return $withDelete.first();

					return $toolbars.last();
				} )();

				if ( ! $toolbar )
					return false;

				// Create buttons if they don't exist
				if ( ! watermarkImageActions.gridButtons ) {
					var $apply = $( '<button type="button" class="button media-button button-secondary button-large iw-grid-watermark-apply" />' )
						.text( iwArgsMedia.applyWatermark )
						.attr( 'data-action', 'applywatermark' );

					var $remove = $( '<button type="button" class="button media-button button-secondary button-large iw-grid-watermark-remove" />' )
						.text( iwArgsMedia.removeWatermark )
						.attr( 'data-action', 'removewatermark' );

					if ( ! iwArgsImageActions.backup_image ) {
						$remove.prop( 'disabled', true ).addClass( 'hidden' );
					}
					$apply.prop( 'disabled', true );
					$remove.prop( 'disabled', true );

					var buttonClick = function( e ) {
						e.preventDefault();
						var action = $( this ).attr( 'data-action' );
						if ( action ) {
							watermarkImageActions.startGridAction( action );
						}
					};

					$apply.on( 'click', buttonClick );
					$remove.on( 'click', buttonClick );

					watermarkImageActions.gridButtons = $apply.add( $remove );
				}

				// Check if buttons are already in this toolbar
				var parentEl = watermarkImageActions.gridButtons.parent().get(0);
					if ( parentEl !== $toolbar.get(0) ) {
						watermarkImageActions.gridButtons.detach();

						var $deleteButton = $toolbar.find( '.delete-selected-button' ).first();
					var $cancelButton = $toolbar.find( '.cancel-selection' ).first();

						if ( $deleteButton.length ) {
						$apply = watermarkImageActions.gridButtons.filter( '.iw-grid-watermark-apply' );
						$remove = watermarkImageActions.gridButtons.filter( '.iw-grid-watermark-remove' );

							if ( $cancelButton.length ) {
								$cancelButton.before( $remove );
								$cancelButton.before( $apply );
							} else {
								$deleteButton.after( $apply );
								$apply.after( $remove );
							}
						} else {
							$toolbar.append( watermarkImageActions.gridButtons );
						}
					}

				return true;
			},
			renderGridButtons: function() {
				var frame = watermarkImageActions.gridFrame;

				if ( frame && frame.isModeActive && ! frame.isModeActive( 'grid' ) ) {
					return;
				}

				if ( ! watermarkImageActions.ensureGridButtonsDom() ) {
					return;
				}

				var isSelectMode = frame && frame.isModeActive ? frame.isModeActive( 'select' ) : $( '.media-frame.mode-grid' ).hasClass( 'mode-select' );

				if ( isSelectMode ) {
					watermarkImageActions.gridButtons.show();
				} else {
					watermarkImageActions.gridButtons.hide();
				}
				watermarkImageActions.updateGridButtonsState();
			},
			hideGridButtons: function() {
				if ( watermarkImageActions.gridButtons ) {
					watermarkImageActions.gridButtons.hide();
				}
			},
			updateGridButtonsState: function() {
				var $buttons = watermarkImageActions.gridButtons;

				if ( ! $buttons || !$buttons.length ) {
					return;
				}

				var selection = watermarkImageActions.gridFrame && watermarkImageActions.gridFrame.state ? watermarkImageActions.gridFrame.state().get( 'selection' ) : null,
					selectedSupportedCount = 0,
					disabled;

				if ( selection && selection.length ) {
					selection.each( function( model ) {
						if ( watermarkImageActions.is_supported_model( model ) ) {
							selectedSupportedCount++;
						}
					} );
				} else {
					$( '.attachments-browser .attachments .attachment.selected' ).each( function() {
						if ( watermarkImageActions.is_supported_dom( $( this ) ) ) {
							selectedSupportedCount++;
						}
					} );
				}

				disabled = selectedSupportedCount === 0 || watermarkImageActions.running;

				$buttons.prop( 'disabled', disabled );

				if ( ! iwArgsImageActions.backup_image ) {
					$buttons.filter( '.iw-grid-watermark-remove' ).prop( 'disabled', true ).addClass( 'hidden' );
				}

				$buttons.removeClass( 'hidden' );
			},
			startGridAction: function( action ) {
				if ( ! iwArgsImageActions.backup_image && action === 'removewatermark' ) {
					return;
				}

				var selection = watermarkImageActions.gridFrame && watermarkImageActions.gridFrame.state ? watermarkImageActions.gridFrame.state().get( 'selection' ) : null,
					ids = [];

				if ( selection && selection.length ) {
					selection.each( function( model ) {
						if ( watermarkImageActions.is_supported_model( model ) ) {
							ids.push( model.get( 'id' ) );
						}
					} );
				} else {
					// Fallback: get selected IDs from DOM
					$( '.attachments-browser .attachments .attachment.selected' ).each( function() {
						var $item = $( this );
						var id = $item.data( 'id' );
						if ( id && watermarkImageActions.is_supported_dom( $item ) ) {
							ids.push( id );
						}
					} );
				}

				if ( ! ids.length )
					return;

				if ( false === watermarkImageActions.running ) {
					watermarkImageActions.running = true;
					watermarkImageActions.action = action;
					watermarkImageActions.action_location = 'grid';
					watermarkImageActions.selected = ids.slice( 0 );

					$( '.iw-notice' ).slideUp( 'fast', function() {
						$( this ).remove();
					} );

					watermarkImageActions.updateGridButtonsState();
					watermarkImageActions.post_loop();
				} else {
					watermarkImageActions.notice( 'iw-notice error', iwArgsImageActions.__running, false );
				}
			},
			post_loop: function() {
				// do we have selected attachments?
				if ( watermarkImageActions.selected.length ) {
					// take the first id
					id = watermarkImageActions.selected[ 0 ];

					// check for a valid ID (needs to be numeric)
					if ( ! isNaN( id ) ) {
						// Show loading icon
						watermarkImageActions.row_image_feedback( 'loading', id );

						// post data
						data = {
							'_iw_nonce': iwArgsImageActions._nonce,
							'action': 'iw_watermark_bulk_action',
							'iw-action': watermarkImageActions.action,
							'attachment_id': id
						};

						if ( watermarkImageActions.action_location == 'upload-list' )
							watermarkImageActions.scroll_to( '#post-' + id, 'bottom' );

						// the ajax post!
						$.post( ajaxurl, data, function( response ) {
							// show result
							watermarkImageActions.result( response, id );
							// remove this ID/key from the selected attachments
							watermarkImageActions.selected.splice( 0, 1 );
							// Redo this function
							watermarkImageActions.post_loop();

							$( '.iw-overlay' ).first().each( function() {
								$( this ).fadeOut( 'fast', function() {
									$( this ).remove();

									if ( response.data === 'watermarked' ) {
										$( '#image_watermark_buttons .value' ).append( '<span class="dashicons dashicons-yes" style="font-size: 24px;float: none;min-width: 28px;padding: 0;margin: 0; display: none;"></span>' );
										$( '#image_watermark_buttons .value .dashicons' ).fadeIn( 'fast' );
									} else if ( response.data === 'watermarkremoved' ) {
										$( '#image_watermark_buttons .value' ).append( '<span class="dashicons dashicons-yes" style="font-size: 24px;float: none;min-width: 28px;padding: 0;margin: 0; display: none;"></span>' );
										$( '#image_watermark_buttons .value .dashicons' ).fadeIn( 'fast' );
									}

									$( '#image_watermark_buttons .value .dashicons' ).delay( 1500 ).fadeOut( 'fast', function() {
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
			result: function( response, id ) {
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
							if ( 1 < watermarkImageActions.successCount )
								message = iwArgsImageActions.__applied_multi.replace( '%s', watermarkImageActions.successCount );
							else
								message = iwArgsImageActions.__applied_one;

							// update the row feedback
							watermarkImageActions.row_image_feedback( 'success', id );

							// reload the image
							watermarkImageActions.reload_image( id );
							watermarkImageActions.refresh_attachment_cache( id );
							break;

						case 'watermarkremoved':
							// The css classes for the notice
							type = 'iw-notice updated iw-watermarkremoved';

							// another successful update
							watermarkImageActions.successCount += 1;

							// did we have more success updates?
							if ( 1 < watermarkImageActions.successCount )
								message = iwArgsImageActions.__removed_multi.replace( '%s', watermarkImageActions.successCount );
							else
								message = iwArgsImageActions.__removed_one;

							// update the row feedback
							watermarkImageActions.row_image_feedback( 'success', id );

							// reload the image
							watermarkImageActions.reload_image( id );
							watermarkImageActions.refresh_attachment_cache( id );
							break;

						case 'skipped':
							// The css classes for the notice
							type = 'iw-notice error iw-skipped';

							// another skipped update
							watermarkImageActions.skippedCount += 1;

							// adjust the message with the number of skipped updates
							message = iwArgsImageActions.__skipped + ': ' + watermarkImageActions.skippedCount;

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
			row_image_feedback: function( type, id ) {
				var css = { };
				var cssinner = { };
				var container_selector;

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

					case 'grid':
						container_selector = '.attachments-browser .attachments [data-id="' + id + '"] .attachment-preview';
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
				if ( ! $( container_selector + ' .iw-overlay' ).length )
					$( container_selector ).append( '<span class="iw-overlay"><span class="iw-overlay-inner"></span></span>' );

				// Overwrite with new data
				$( container_selector + ' .iw-overlay' ).css( css );
				$( container_selector + ' .iw-overlay .iw-overlay-inner' ).css( cssinner );
				$( container_selector + ' .iw-overlay .iw-overlay-inner' ).html( '<span class="spinner is-active"></span>' );

				if ( watermarkImageActions.action_location === 'media-modal' )
					$( container_selector + ' .iw-overlay .iw-overlay-inner .spinner' ).css( { 'float': 'none', 'padding': 0, 'margin': '-4px 0 0 10px' } );
			},
			notice: function( type, message, overwrite ) {
				if ( watermarkImageActions.action_location === 'media-modal' )
					return;

				type += ' notice is-dismissible';

				// Get the prefix based on the action location
				switch ( watermarkImageActions.action_location ) {
					case 'upload-list':
						prefix = '.wrap > h1';
						break;

					default:
						prefix = '#image_watermark_buttons';
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
						}

						$( prefix + selector + ' > p' ).html( message );
					} else {
						$( prefix ).after( '<div class="' + type + '" style="display: none;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwArgsImageActions.__dismiss + '</span></button></div>' );
						$( '.iw-notice' ).slideDown( 'fast' );
					}
				} else {
					// create a new notice
					$( prefix ).after( '<div class="' + type + '" style="display: none;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwArgsImageActions.__dismiss + '</span></button></div>' );
					$( '.iw-notice' ).slideDown( 'fast' );
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
				setTimeout( function() {
					$( '.iw-overlay' ).each( function() {
						$( this ).fadeOut( 'fast', function() {
						$( this ).remove();
						} );
					} );
				}, 100 );

				// Re-enable grid buttons after processing completes.
				watermarkImageActions.updateGridButtonsState();
			},
			reload_image: function( id ) {
				// reload the images
				time = new Date().getTime();
				var selectors = [];

				// Get selectors based on the action location
				switch ( watermarkImageActions.action_location ) {
					case 'upload-list':
						selectors.push( '.wp-list-table #post-' + id + ' .image-icon img' );
						break;

					case 'grid':
						selectors.push( '.attachments-browser .attachments [data-id="' + id + '"] img' );
						// Also refresh modal/detail thumbnails in case the modal is opened after.
						selectors.push( '.attachment-details[data-id="' + id + '"] img, .attachment[data-id="' + id + '"] img, .attachment-info .thumbnail img, .attachment-media-view img' );
						break;

					case 'media-modal':
						selectors.push( '.attachment-details[data-id="' + id + '"] img, .attachment[data-id="' + id + '"] img, .attachment-info .thumbnail img, .attachment-media-view img' );
						break;

					case 'edit':
						selectors.push( '.attachment-info .thumbnail img, .attachment-media-view img, .wp_attachment_holder img' );
						break;
				}

				selectors = selectors.filter( Boolean );

				if ( selectors.length ) {
					$( selectors.join( ',' ) ).each( function() {
						// Remove the responsive metadata, this prevents reloading the image
						$( this ).removeAttr( 'srcset' );
						$( this ).removeAttr( 'sizes' );

						// Reload the image (actually a browser hack by adding a time parameter to the image)
						$( this ).attr( 'src', watermarkImageActions.replace_url_param( $( this ).attr( 'src' ), 't', time ) );
					} );
				}
			},
			is_supported_model: function( model ) {
				if ( ! model ) {
					return false;
				}

				var type = model.get( 'type' );
				var mime = model.get( 'mime' ) || model.get( 'mime_type' ) || ( type && model.get( 'subtype' ) ? type + '/' + model.get( 'subtype' ) : '' );

				if ( type !== 'image' && mime.indexOf( 'image/' ) !== 0 ) {
					return false;
				}

				if ( Array.isArray( iwArgsImageActions.allowed_mimes ) && iwArgsImageActions.allowed_mimes.length ) {
					return iwArgsImageActions.allowed_mimes.indexOf( mime ) !== -1;
				}

				return true;
			},
			is_supported_dom: function( $el ) {
				if ( ! $el || !$el.length ) {
					return false;
				}

				var type = $el.data( 'type' );
				var subtype = $el.data( 'subtype' );
				var mime = type && subtype ? type + '/' + subtype : '';

				if ( type !== 'image' && mime.indexOf( 'image/' ) !== 0 ) {
					return false;
				}

				if ( Array.isArray( iwArgsImageActions.allowed_mimes ) && iwArgsImageActions.allowed_mimes.length ) {
					return iwArgsImageActions.allowed_mimes.indexOf( mime ) !== -1;
				}

				return true;
			},
			refresh_attachment_cache: function( id ) {
				if ( typeof wp === 'undefined' || ! wp.media || ! wp.media.attachment ) {
					return;
				}

				var attachment = wp.media.attachment( id );

				if ( attachment ) {
					attachment.fetch( { cache: false } ).then( function() {
						watermarkImageActions.cache_bust_attachment_sources( attachment );
					} );
				}
			},
			cache_bust_attachment_sources: function( attachment ) {
				var time = new Date().getTime();
				var changed = {};

				if ( attachment.get( 'url' ) ) {
					changed.url = watermarkImageActions.replace_url_param( attachment.get( 'url' ), 't', time );
				}

				if ( attachment.get( 'sizes' ) ) {
					var sizes = attachment.get( 'sizes' );
					var newSizes = {};

					$.each( sizes, function( key, value ) {
						if ( value && value.url ) {
							var cloned = $.extend( {}, value );
							cloned.url = watermarkImageActions.replace_url_param( value.url, 't', time );
							newSizes[ key ] = cloned;
						} else {
							newSizes[ key ] = value;
						}
					} );

					changed.sizes = newSizes;
				}

				if ( attachment.get( 'icon' ) ) {
					changed.icon = watermarkImageActions.replace_url_param( attachment.get( 'icon' ), 't', time );
				}

				if ( Object.keys( changed ).length ) {
					attachment.set( changed );
				}
			},
			rotate_icon: function( icon ) {
				// This function accepts selectors and objects
				if ( typeof icon == 'string' )
				icon = $( icon );

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
							step: function( now, fx ) {
								$( this ).css( '-webkit-transform', 'rotate(' + now + 'deg)' );
								$( this ).css( '-ms-transform', 'rotate(' + now + 'deg)' );
								$( this ).css( 'transform', 'rotate(' + now + 'deg)' );

								if ( now == 360 ) {
									// Animation finished, stop loop and restart
									icon.stop();
									watermarkImageActions.rotate_icon( icon );
								}
							}
						}
					);
				}
			},
			replace_url_param: function( url, paramName, paramValue ) {
				var pattern = new RegExp( '\\b(' + paramName + '=).*?(&|$)' );

				if ( url.search( pattern ) >= 0 )
					return url.replace( pattern, '$1' + paramValue + '$2' );

				return url + ( url.indexOf( '?' ) > 0 ? '&' : '?' ) + paramName + '=' + paramValue;
			},
			scroll_to: function( elementSelector, verticalTarget ) {
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
						if ( offset.top < windowBottomOffset )
							return; // The element is in the viewport

						offsetTop = offsetTop - $( window ).outerHeight();
						offsetTop = offsetTop + $( elementSelector ).outerHeight();
						break;

					case 'center':
						if ( offsetTop < windowBottomOffset && offsetTop >= windowTopOffset )
							return; // The element is in the viewport

						offsetTop = offsetTop - ( $( window ).outerHeight() / 2 );
						offsetTop = offsetTop + ( $( elementSelector ).outerHeight() / 2 );
						break;
				}

				$( window ).scrollTop( offsetTop );
			}
		};

		// We need that nonce!
		if ( typeof iwArgsImageActions._nonce !== 'undefined' )
			watermarkImageActions.init();
	} );

} )( jQuery );
