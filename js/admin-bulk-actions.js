
jQuery( document ).ready( function ( $ ) {
	watermarkBulkActions = {
		nonce: '',
		running: false,
		action: '',
		response: '',
		selected: [],
		successCount: 0,
		skippedCount: 0,

		init: function() {

			watermarkBulkActions.nonce = iwBulkActionArgs._nonce;

			$(document).on('click', '.bulkactions input#doaction', function(e) {
				// Get the selected bulk action
				action = $(this).parent().children('select').val();

				// Validate action
				if ( action == 'applywatermark' || action == 'removewatermark' ) {
					// Stop default
					e.preventDefault();

					watermarkBulkActions.action = action;

					if ( watermarkBulkActions.running === false ) {
						watermarkBulkActions.running = true;

						$('.wp-list-table .check-column input:checkbox:checked').each(function(){
							watermarkBulkActions.selected.push( $(this).val() );
						});

						watermarkBulkActions.post_loop();

					} else {
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
			if ( watermarkBulkActions.selected.length ) {
				id = watermarkBulkActions.selected[ 0 ];
				if ( ! isNaN( id ) ) {
					watermarkBulkActions.animate_row_image( 'loading', id );
					data = {
						'_iw_nonce': watermarkBulkActions.nonce,
						'action': 'iw_watermark_bulk_action',
						'iw-action': watermarkBulkActions.action,
						'attachment_id': id
					};
					$.post(ajaxurl, data, function(response) {
						watermarkBulkActions.result(response, id);
						watermarkBulkActions.selected.splice(0,1);
						watermarkBulkActions.post_loop();
					});
				} else {
					watermarkBulkActions.selected.splice(0,1);
					watermarkBulkActions.post_loop();
				}
			} else {
				watermarkBulkActions.reset();
			}
		},

		result: function( response, id ) {
			
			if ( response.success === true ) {
				type = false;
				message = '';
				watermarkBulkActions.response = response.data;
				switch ( response.data ) {
					case 'watermarked': 
						type = 'updated iw-notice iw-watermarked';
						watermarkBulkActions.successCount += 1;
						if ( watermarkBulkActions.successCount > 1 ) {
							message = iwBulkActionArgs.__applied_multi.replace('%s', watermarkBulkActions.successCount);
						} else {
							message = iwBulkActionArgs.__applied_one;
						}
						watermarkBulkActions.animate_row_image( 'success', id );
						watermarkBulkActions.reload_image( id );
					break;
					case 'watermarkremoved': 
						type = 'updated iw-notice iw-watermarkremoved';
						watermarkBulkActions.successCount += 1;
						if ( watermarkBulkActions.successCount > 1 ) {
							message = iwBulkActionArgs.__removed_multi.replace('%s', watermarkBulkActions.successCount);
						} else {
							message = iwBulkActionArgs.__removed_one;
						}
						watermarkBulkActions.animate_row_image( 'success', id );
						watermarkBulkActions.reload_image( id );
					break;
					case 'skipped': 
						type = 'error iw-notice iw-skipped';
						watermarkBulkActions.skippedCount += 1;
						message = iwBulkActionArgs.__skipped + ': ' + watermarkBulkActions.skippedCount;
						watermarkBulkActions.animate_row_image( 'error', id );
					break;
				}
				if ( type !== false ) {
					watermarkBulkActions.notice( type, message, true );
				}
			} else {
				watermarkBulkActions.notice( 'error iw-notice', response.data, false );
				watermarkBulkActions.animate_row_image( 'error', id );
			}

		},

		animate_row_image: function( type, id ) {
			container_selector = '.wp-list-table #post-'+id+' .image-icon';
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
			}
			icon = '';
			iconcss = {
				'font-size': '50px',
				'line-height': 'normal',
				'vertical-align': 'middle',
				width: '50px',
				height: '50px',
			};
			rotate = false;
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
			if ( ! $(container_selector+' .iw-overlay').length ) {
				$(container_selector).append('<span class="iw-overlay"><span class="iw-overlay-inner"></span></span>');
				$(container_selector+' .iw-overlay .iw-overlay-inner').html('<span class="dashicons ' + icon + '"></span>');
			}
			$(container_selector+' .iw-overlay').css(css);
			$(container_selector+' .iw-overlay .iw-overlay-inner').css(cssinner);
			$(container_selector+' .iw-overlay .iw-overlay-inner').html('<span class="dashicons '+icon+'"></span>');
			$(container_selector+' .iw-overlay .dashicons').css(iconcss);

			if ( rotate ) {
				watermarkBulkActions.rotate_icon( $(container_selector+' .iw-overlay .dashicons') );
			}

		},

		notice: function( type, message, overwrite ) {

			type += ' notice is-dismissible';
			if ( overwrite === true) {
				selector = '';
				switch ( watermarkBulkActions.response ) {
					case 'watermarked': selector = '.iw-notice.iw-watermarked'; break;
					case 'watermarkremoved': selector = '.iw-notice.iw-watermarkremoved'; break;
					case 'skipped': selector = '.iw-notice.iw-skipped'; break;
				}
				if ( $('.wrap '+selector+' > p').length ) {
					$('.wrap '+selector+' > p').html( message );
				} else {
					$('.wrap > h1 ').after('<div class="' + type + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' + iwBulkActionArgs.__dismiss + '</span></button></div>');
				}
			} else {
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
					watermarkBulkActions.rotate_icon( icon );
				}
			);
		}
	};

	if ( typeof iwBulkActionArgs._nonce != 'undefined' ) {
		watermarkBulkActions.init();
	}

} );
