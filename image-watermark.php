<?php
/*
Plugin Name: Image Watermark
Description: Image Watermark allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.
Version: 1.6.1
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/image-watermark/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: image-watermark
Domain Path: /languages

Image Watermark
Copyright (C) 2013-2016, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

define( 'IMAGE_WATERMARK_URL', plugins_url( '', __FILE__ ) );
define( 'IMAGE_WATERMARK_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Image Watermark class.
 *
 * @class Image_Watermark
 * @version	1.6.1
 */
final class Image_Watermark {

	private static $instance;
	private $is_admin = true;
	private $extension = false;
	private $allowed_mime_types = array(
		'image/jpeg',
		'image/pjpeg',
		'image/png'
	);
	private $is_watermarked_metakey = 'iw-is-watermarked';
	public $is_backup_folder_writable = null;
	public $extensions;
	public $defaults = array(
		'options'	 => array(
			'watermark_on'		 => array(),
			'watermark_cpt_on'	 => array( 'everywhere' ),
			'watermark_image'	 => array(
				'extension'				 => '',
				'url'					 => 0,
				'width'					 => 80,
				'plugin_off'			 => 0,
				'frontend_active'		 => false,
				'manual_watermarking'	 => 0,
				'position'				 => 'bottom_right',
				'watermark_size_type'	 => 2,
				'offset_width'			 => 0,
				'offset_height'			 => 0,
				'absolute_width'		 => 0,
				'absolute_height'		 => 0,
				'transparent'			 => 50,
				'quality'				 => 90,
				'jpeg_format'			 => 'baseline',
				'deactivation_delete'	 => false,
				'media_library_notice'	 => true
			),
			'image_protection'	 => array(
				'rightclick'	 => 0,
				'draganddrop'	 => 0,
				'forlogged'		 => 0,
			),
			'backup'			 => array(
				'backup_image'	 => true,
				'backup_quality' => 90,
			),
		),
		'version'	 => '1.6.1'
	);
	public $options = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// installer
		register_activation_hook( __FILE__, array( $this, 'activate_watermark' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate_watermark' ) );

		// settings
		$this->options = array_merge( $this->defaults['options'], get_option( 'image_watermark_options', $this->defaults['options'] ) );

		include_once( IMAGE_WATERMARK_PATH . 'includes/class-update.php' );
		include_once( IMAGE_WATERMARK_PATH . 'includes/class-settings.php' );

		// actions
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_scripts', array( $this, 'admin_print_scripts' ), 20 );
		add_action( 'wp_enqueue_media', array( $this, 'wp_enqueue_media' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'load-upload.php', array( $this, 'watermark_bulk_action' ) );
		add_action( 'admin_init', array( $this, 'update_plugin' ) );
		add_action( 'admin_init', array( $this, 'check_extensions' ) );
		add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
		add_action( 'delete_attachment', array( $this, 'delete_attachment' ) );
		add_action( 'wp_ajax_iw_watermark_bulk_action', array( $this, 'watermark_action_ajax' ) );

		// filters
		add_filter( 'plugin_row_meta', array( $this, 'plugin_extend_links' ), 10, 2 );
		add_filter( 'plugin_action_links', array( $this, 'plugin_settings_link' ), 10, 2 );
		add_filter( 'wp_handle_upload', array( $this, 'handle_upload_files' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );

		// define our backup location
		$upload_dir = wp_upload_dir();
		define( 'IMAGE_WATERMARK_BACKUP_DIR', apply_filters( 'image_watermark_backup_dir', $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'iw-backup' ) );

		// create backup folder and security if enabled
		if ( $this->options['backup']['backup_image'] ) {

			if ( is_writable( $upload_dir['basedir'] ) ) {
				$this->is_backup_folder_writable = true;

				// create backup folder ( if it exists this returns true: https://codex.wordpress.org/Function_Reference/wp_mkdir_p )
				$backup_folder_created = wp_mkdir_p( IMAGE_WATERMARK_BACKUP_DIR );

				// check if the folder exists and is writable
				if ( $backup_folder_created && is_writable( IMAGE_WATERMARK_BACKUP_DIR ) ) {
					// check if the htaccess file exists
					if ( ! file_exists( IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
						// htaccess security
						file_put_contents( IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all' );
					}
				} else {
					$this->is_backup_folder_writable = false;
				}
			} else {
				$this->is_backup_folder_writable = false;
			}
			if ( true !== $this->is_backup_folder_writable ) {
				// disable backup setting
				$this->options['backup']['backup_image'] = false;
				update_option( 'image_watermark_options', $this->options );
			}

			add_action( 'admin_notices', array( $this, 'folder_writable_admin_notice' ) );
		}
	}

	/**
	 * Create single instance.
	 *
	 * @return object Main plugin instance
	 */
	public static function instance() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Plugin activation.
	 */
	public function activate_watermark() {
		add_option( 'image_watermark_options', $this->defaults['options'], '', 'no' );
		add_option( 'image_watermark_version', $this->defaults['version'], '', 'no' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate_watermark() {
		// remove options from database?
		if ( $this->options['watermark_image']['deactivation_delete'] )
			delete_option( 'image_watermark_options' );
	}

	/**
	 * Plugin update, fix for version < 1.5.0
	 */
	public function update_plugin() {
		if ( ! current_user_can( 'install_plugins' ) )
			return;

		$db_version = get_option( 'image_watermark_version' );
		$db_version = ! ( $db_version ) && ( get_option( 'df_watermark_installed' ) != false ) ? get_option( 'version' ) : $db_version;

		if ( $db_version != false ) {
			if ( version_compare( $db_version, '1.5.0', '<' ) ) {
				$options = array();

				$old_new = array(
					'df_watermark_on'			 => 'watermark_on',
					'df_watermark_cpt_on'		 => 'watermark_cpt_on',
					'df_watermark_image'		 => 'watermark_image',
					'df_image_protection'		 => 'image_protection',
					'df_watermark_installed'	 => '',
					'version'					 => '',
					'image_watermark_version'	 => '',
				);

				foreach ( $old_new as $old => $new ) {
					if ( $new ) {
						$options[$new] = get_option( $old );
					}
					delete_option( $old );
				}

				add_option( 'image_watermark_options', $options, '', 'no' );
				add_option( 'image_watermark_version', $this->defaults['version'], '', 'no' );
			}
		}
	}

	/**
	 * Load textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'image-watermark', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Admin inline scripts.
	 * 
	 * @global $pagenow
	 */
	public function admin_print_scripts() {
		global $pagenow;

		if ( $pagenow === 'upload.php' ) {
			if ( $this->options['watermark_image']['manual_watermarking'] == 1 ) {
				?>
				<script type="text/javascript">
					jQuery( function( $ ) {
						$( document ).ready( function() {
							var backup = <?php echo (int) $this->options['backup']['backup_image']; ?>;

							$( "<option>" ).val( "applywatermark" ).text( "<?php _e( 'Apply watermark', 'image-watermark' ); ?>" ).appendTo( "select[name='action'], select[name='action2']" );

							if ( backup === 1 ) {
								$( "<option>" ).val( "removewatermark" ).text( "<?php _e( 'Remove watermark', 'image-watermark' ); ?>" ).appendTo( "select[name='action'], select[name='action2']" );
							}
						});
					});
				</script>
				<?php
			}
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function wp_enqueue_media( $page ) {
		wp_enqueue_style( 'watermark-style', plugins_url( 'css/image-watermark.css', __FILE__ ), array(), $this->defaults['version'] );
	}

	/**
	 * Enqueue admin scripts and styles.
	 * 
	 * @global $pagenow
	 */
	public function admin_enqueue_scripts( $page ) {
		global $pagenow;

		wp_register_style( 'watermark-style', plugins_url( 'css/image-watermark.css', __FILE__ ), array(), $this->defaults['version'] );

		if ( $page === 'settings_page_watermark-options' ) {
			wp_enqueue_media();

			wp_enqueue_script( 'upload-manager', plugins_url( '/js/admin-upload.js', __FILE__ ), array(), $this->defaults['version'] );

			wp_localize_script(
			'upload-manager', 'iwUploadArgs', array(
				'title'			 => __( 'Select watermark', 'image-watermark' ),
				'originalSize'	 => __( 'Original size', 'image-watermark' ),
				'noSelectedImg'	 => __( 'Watermak has not been selected yet.', 'image-watermark' ),
				'notAllowedImg'	 => __( 'This image is not supported as watermark. Use JPEG, PNG or GIF.', 'image-watermark' ),
				'px'			 => __( 'px', 'image-watermark' ),
				'frame'			 => 'select',
				'button'		 => array( 'text' => __( 'Add watermark', 'image-watermark' ) ),
				'multiple'		 => false
			)
			);

			wp_enqueue_script( 'watermark-admin-script', plugins_url( 'js/admin-settings.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-slider' ), $this->defaults['version'] );

			wp_localize_script(
			'watermark-admin-script', 'iwArgs', array(
				'resetToDefaults' => __( 'Are you sure you want to reset settings to defaults?', 'image-watermark' )
			)
			);

			wp_enqueue_style( 'wp-like-ui-theme', plugins_url( 'css/wp-like-ui-theme.css', __FILE__ ), array(), $this->defaults['version'] );
			wp_enqueue_style( 'watermark-style' );

			wp_enqueue_script( 'postbox' );
		}

		if ( $pagenow === 'upload.php' ) {
			wp_enqueue_style( 'watermark-style' );
		}

		// I've omitted $pagenow === 'upload.php' because the image modal could be loaded in various places
		if ( $this->options['watermark_image']['manual_watermarking'] == 1 ) {

			wp_enqueue_script( 'watermark-admin-image-actions', plugins_url( '/js/admin-image-actions.js', __FILE__ ), array( 'jquery' ), $this->defaults['version'], true );

			wp_localize_script(
			'watermark-admin-image-actions', 'iwImageActionArgs', array(
				'backup_image'		 => (int) $this->options['backup']['backup_image'],
				'_nonce'			 => wp_create_nonce( 'image-watermark' ),
				'__applied_none'	 => __( 'Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ),
				'__applied_one'		 => __( 'Watermark was succesfully applied to 1 image.', 'image-watermark' ),
				'__applied_multi'	 => __( 'Watermark was succesfully applied to %s images.', 'image-watermark' ),
				'__removed_none'	 => __( 'Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ),
				'__removed_one'		 => __( 'Watermark was succesfully removed from 1 image.', 'image-watermark' ),
				'__removed_multi'	 => __( 'Watermark was succesfully removed from %s images.', 'image-watermark' ),
				'__skipped'			 => __( 'Skipped files', 'image-watermark' ),
				'__running'			 => __( 'Bulk action is currently running, please wait.', 'image-watermark' ),
				'__dismiss'			 => __( 'Dismiss this notice.' ), // Wordpress default string
			)
			);
		}
	}

	/**
	 * Enqueue frontend script with 'no right click' and 'drag and drop' functions.
	 */
	public function wp_enqueue_scripts() {
		if ( ($this->options['image_protection']['forlogged'] == 0 && is_user_logged_in()) || ($this->options['image_protection']['draganddrop'] == 0 && $this->options['image_protection']['rightclick'] == 0) )
			return;

		wp_enqueue_script( 'iw-no-right-click', plugins_url( 'js/no-right-click.js', __FILE__ ), array(), $this->defaults['version'] );

		wp_localize_script(
			'iw-no-right-click', 'IwNRCargs', array(
				'rightclick'	 => ($this->options['image_protection']['rightclick'] == 1 ? 'Y' : 'N'),
				'draganddrop'	 => ($this->options['image_protection']['draganddrop'] == 1 ? 'Y' : 'N')
			)
		);
	}

	/**
	 * Check which extension is available and set it.
	 *
	 * @return void
	 */
	public function check_extensions() {
		$ext = null;

		if ( $this->check_imagick() ) {
			$this->extensions['imagick'] = 'ImageMagick';
			$ext = 'imagick';
		}

		if ( $this->check_gd() ) {
			$this->extensions['gd'] = 'GD';

			if ( is_null( $ext ) )
				$ext = 'gd';
		}

		if ( isset( $this->options['watermark_image']['extension'] ) ) {
			if ( $this->options['watermark_image']['extension'] === 'imagick' && isset( $this->extensions['imagick'] ) )
				$this->extension = 'imagick';
			elseif ( $this->options['watermark_image']['extension'] === 'gd' && isset( $this->extensions['gd'] ) )
				$this->extension = 'gd';
			else
				$this->extension = $ext;
		} else
			$this->extension = $ext;
	}

	/**
	 * Apply watermark everywhere or for specific post types.
	 *
	 * @param	resource $file
	 * @return	resource
	 */
	public function handle_upload_files( $file ) {
		// is extension available?
		if ( $this->extension ) {
			// determine ajax frontend or backend request
			$script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ? $_SERVER['SCRIPT_FILENAME'] : '';

			// try to figure out if frontend AJAX request... if we are DOING_AJAX; let's look closer
			if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				// from wp-includes/functions.php, wp_get_referer() function.
				// required to fix: https://core.trac.wordpress.org/ticket/25294
				$ref = '';
				if ( ! empty( $_REQUEST['_wp_http_referer'] ) )
					$ref = wp_unslash( $_REQUEST['_wp_http_referer'] );
				elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) )
					$ref = wp_unslash( $_SERVER['HTTP_REFERER'] );

				// if referer does not contain admin URL and we are using the admin-ajax.php endpoint, this is likely a frontend AJAX request
				if ( ( ( strpos( $ref, admin_url() ) === false ) && ( basename( $script_filename ) === 'admin-ajax.php' ) ) )
					$this->is_admin = false;
				else
					$this->is_admin = true;
				// not an AJAX request, simple here
			} else {
				if ( is_admin() )
					$this->is_admin = true;
				else
					$this->is_admin = false;
			}

			// admin
			if ( $this->is_admin === true ) {
				if ( $this->options['watermark_image']['plugin_off'] == 1 && $this->options['watermark_image']['url'] != 0 && in_array( $file['type'], $this->allowed_mime_types ) ) {
					add_filter( 'wp_generate_attachment_metadata', array( $this, 'apply_watermark' ), 10, 2 );
				}
				// frontend
			} else {
				if ( $this->options['watermark_image']['frontend_active'] == 1 && $this->options['watermark_image']['url'] != 0 && in_array( $file['type'], $this->allowed_mime_types ) ) {
					add_filter( 'wp_generate_attachment_metadata', array( $this, 'apply_watermark' ), 10, 2 );
				}
			}
		}

		return $file;
	}

	/**
	 * Add watermark buttons on attachment image locations
	 */
	public function attachment_fields_to_edit( $form_fields, $post ) {

		if ( $this->options['watermark_image']['manual_watermarking'] == 1 && $this->options['backup']['backup_image'] ) {

			$data = wp_get_attachment_metadata( $post->ID, false );

			// is this really an image?
			if ( in_array( get_post_mime_type( $post->ID ), $this->allowed_mime_types ) && is_array( $data ) ) {
				$form_fields['image_watermark'] = array(
					'show_in_edit'	 => false,
					'tr'			 => '
					<div id="image_watermark_buttons"' . ( get_post_meta( $post->ID, $this->is_watermarked_metakey, true ) ? ' class="watermarked"' : '' ) . ' data-id="' . $post->ID . '" style="display: none;">
						<label class="setting">
							<span class="name">' . __( 'Image Watermark', 'image-watermark' ) . '</span>
							<span class="value" style="width: 63%"><a href="#" class="iw-watermark-action" data-action="applywatermark" data-id="' . $post->ID . '">' . __( 'Apply watermark', 'image-watermark' ) . '</a> | <a href="#" class="iw-watermark-action delete-watermark" data-action="removewatermark" data-id="' . $post->ID . '">' . __( 'Remove watermark', 'image-watermark' ) . '</a></span>
						</label>
						<div class="clear"></div>
					</div>
					<script>
						jQuery( document ).ready( function ( $ ) {
							if ( typeof watermarkImageActions != "undefined" ) {
								$( "#image_watermark_buttons" ).show();
							}
						});
					</script>'
				);
			}
		}
		return $form_fields;
	}

	/**
	 * Apply watermark for selected images on media page.
	 */
	public function watermark_action_ajax() {
		// Security & data check
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! isset( $_POST['_iw_nonce'] ) || ! isset( $_POST['iw-action'] ) || ! isset( $_POST['attachment_id'] ) || ! is_numeric( $_POST['attachment_id'] ) || ! wp_verify_nonce( $_POST['_iw_nonce'], 'image-watermark' )
		)
			wp_send_json_error( __( 'Cheatin uh?', 'image-watermark' ) );

		$post_id = (int) $_POST['attachment_id'];
		$action = false;

		switch ( $_POST['iw-action'] ) {
			case 'applywatermark': $action = 'applywatermark';
				break;
			case 'removewatermark': $action = 'removewatermark';
				break;
		}

		// only if manual watermarking is turned and we have a valid action
		// if the action is NOT "removewatermark" we also require a watermark image to be set
		if ( $post_id > 0 && $action && $this->options['watermark_image']['manual_watermarking'] == 1 && ( $this->options['watermark_image']['url'] != 0 || $action == 'removewatermark' ) ) {

			$data = wp_get_attachment_metadata( $post_id, false );

			// is this really an image?
			if ( in_array( get_post_mime_type( $post_id ), $this->allowed_mime_types ) && is_array( $data ) ) {

				if ( $action === 'applywatermark' ) {
					$success = $this->apply_watermark( $data, $post_id, 'manual' );
					if ( ! empty( $success['error'] ) ) {
						wp_send_json_success( $success['error'] );
					} else {
						wp_send_json_success( 'watermarked' );
					}
				} elseif ( $action === 'removewatermark' ) {
					$success = $this->remove_watermark( $data, $post_id, 'manual' );
					if ( $success ) {
						wp_send_json_success( 'watermarkremoved' );
					} else {
						wp_send_json_success( 'skipped' );
					}
				}
			} else {
				wp_send_json_success( 'skipped' );
			}
		}

		wp_send_json_error( __( 'Cheatin uh?', 'image-watermark' ) );
	}

	/**
	 * Apply watermark for selected images on media page.
	 * 
	 * @return void
	 */
	public function watermark_bulk_action() {
		global $pagenow;

		if ( $pagenow == 'upload.php' && $this->extension ) {
			$wp_list_table = _get_list_table( 'WP_Media_List_Table' );

			$action = false;
			switch ( $wp_list_table->current_action() ) {
				case 'applywatermark': $action = 'applywatermark';
					break;
				case 'removewatermark': $action = 'removewatermark';
					break;
			}
			// only if manual watermarking is turned and we have a valid action
			// if the action is NOT "removewatermark" we also require a watermark image to be set
			if ( $action && $this->options['watermark_image']['manual_watermarking'] == 1 && ( $this->options['watermark_image']['url'] != 0 || $action == 'removewatermark' ) ) {
				// security check
				check_admin_referer( 'bulk-media' );

				$location = esc_url( remove_query_arg( array( 'watermarked', 'watermarkremoved', 'skipped', 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted' ), wp_get_referer() ) );

				if ( ! $location ) {
					$location = 'upload.php';
				}

				$location = esc_url( add_query_arg( 'paged', $wp_list_table->get_pagenum(), $location ) );

				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if ( isset( $_REQUEST['media'] ) ) {
					$post_ids = array_map( 'intval', $_REQUEST['media'] );
				}

				// do we have selected attachments?
				if ( $post_ids ) {

					$watermarked = $watermarkremoved = $skipped = 0;
					$messages = array();

					foreach ( $post_ids as $post_id ) {
						$data = wp_get_attachment_metadata( $post_id, false );

						// is this really an image?
						if ( in_array( get_post_mime_type( $post_id ), $this->allowed_mime_types ) && is_array( $data ) ) {
							if ( $action === 'applywatermark' ) {
								$success = $this->apply_watermark( $data, $post_id, 'manual' );
								if ( ! empty( $success['error'] ) ) {
									$messages[] = $success['error'];
								} else {
									$watermarked ++;
									$watermarkremoved = -1;
								}
							} elseif ( $action === 'removewatermark' ) {
								$success = $this->remove_watermark( $data, $post_id, 'manual' );
								if ( $success ) {
									$watermarkremoved ++;
								} else {
									$skipped ++;
								}
								$watermarked = -1;
							}
						} else {
							$skipped ++;
						}
					}

					$location = esc_url( add_query_arg( array( 'watermarked' => $watermarked, 'watermarkremoved' => $watermarkremoved, 'skipped' => $skipped, 'messages' => $messages ), $location ), null, '' );
				}

				wp_redirect( $location );
				exit();
			} else
				return;
		}
	}

	/**
	 * Display admin notices.
	 * 
	 * @return mixed
	 */
	public function bulk_admin_notices() {
		global $post_type, $pagenow;

		if ( $pagenow === 'upload.php' ) {

			if ( ! current_user_can( 'upload_files' ) )
				return;

			// hide media library notice
			if ( isset( $_GET['iw_action'] ) && $_GET['iw_action'] == 'hide_library_notice' ) {
				$this->options['watermark_image']['media_library_notice'] = false;
				update_option( 'image_watermark_options', $this->options );
			}

			// check if manual watermarking is enabled
			if ( ! empty( $this->options['watermark_image']['manual_watermarking'] ) && ( ! isset( $this->options['watermark_image']['media_library_notice'] ) || $this->options['watermark_image']['media_library_notice'] === true ) ) {
				$mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

				if ( isset( $_GET['mode'] ) && in_array( $_GET['mode'], array( 'grid', 'list' ) ) ) {
					$mode = $_GET['mode'];
				}

				// display notice in grid mode only
				if ( $mode === 'grid' ) {
					// get current admin url
					$query_string = array();
					parse_str( $_SERVER['QUERY_STRING'], $query_string );
					$current_url = esc_url( add_query_arg( array_merge( (array) $query_string, array( 'iw_action' => 'hide_library_notice' ) ), '', admin_url( trailingslashit( $pagenow ) ) ) );

					echo '<div class="error notice"><p>' . sprintf( __( '<strong>Image Watermark:</strong> Bulk watermarking is available in list mode only, under <em>Bulk Actions</em> dropdown. <a href="%1$s">Got to List Mode</a> or <a href="%2$s">Hide this notice</a>', 'image-watermark' ), esc_url( admin_url( 'upload.php?mode=list' ) ), esc_url( $current_url ) ) . '</p></div>';
				}
			}

			if ( isset( $_REQUEST['watermarked'], $_REQUEST['watermarkremoved'], $_REQUEST['skipped'] ) && $post_type === 'attachment' ) {
				$watermarked = (int) $_REQUEST['watermarked'];
				$watermarkremoved = (int) $_REQUEST['watermarkremoved'];
				$skipped = (int) $_REQUEST['skipped'];

				if ( $watermarked === 0 ) {
					echo '<div class="error"><p>' . __( 'Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ) . ($skipped > 0 ? ' ' . __( 'Images skipped', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				} elseif ( $watermarked > 0 ) {
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was succesfully applied to 1 image.', 'Watermark was succesfully applied to %s images.', $watermarked, 'image-watermark' ), number_format_i18n( $watermarked ) ) . ($skipped > 0 ? ' ' . __( 'Skipped files', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				}
				if ( $watermarkremoved === 0 ) {
					echo '<div class="error"><p>' . __( 'Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ) . ($skipped > 0 ? ' ' . __( 'Images skipped', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				} elseif ( $watermarkremoved > 0 ) {
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was succesfully removed from 1 image.', 'Watermark was succesfully removed from %s images.', $watermarkremoved, 'image-watermark' ), number_format_i18n( $watermarkremoved ) ) . ($skipped > 0 ? ' ' . __( 'Skipped files', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				}

				$_SERVER['REQUEST_URI'] = esc_url( remove_query_arg( array( 'watermarked', 'skipped' ), $_SERVER['REQUEST_URI'] ) );
			}
		}
	}

	/**
	 * Check whether ImageMagick extension is available.
	 *
	 * @return boolean True if extension is available
	 */
	public function check_imagick() {
		// check Imagick's extension and classes
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick', false ) || ! class_exists( 'ImagickPixel', false ) )
			return false;

		// check version
		if ( version_compare( phpversion( 'imagick' ), '2.2.0', '<' ) )
			return false;

		// check for deep requirements within Imagick
		if ( ! defined( 'imagick::COMPRESSION_JPEG' ) || ! defined( 'imagick::COMPOSITE_OVERLAY' ) || ! defined( 'Imagick::INTERLACE_PLANE' ) || ! defined( 'imagick::FILTER_CATROM' ) || ! defined( 'Imagick::CHANNEL_ALL' ) )
			return false;

		// check methods
		if ( array_diff( array( 'clear', 'destroy', 'valid', 'getimage', 'writeimage', 'getimagegeometry', 'getimageformat', 'setimageformat', 'setimagecompression', 'setimagecompressionquality', 'scaleimage' ), get_class_methods( 'Imagick' ) ) )
			return false;

		return true;
	}

	/**
	 * Check whether GD extension is available.
	 *
	 * @return boolean True if extension is available
	 */
	public function check_gd( $args = array() ) {
		// check extension
		if ( ! extension_loaded( 'gd' ) || ! function_exists( 'gd_info' ) )
			return false;

		return true;
	}

	/**
	 * Apply watermark to selected image sizes.
	 *
	 * @param array	$data
	 * @param int|string $attachment_id	Attachment ID
	 * @param string $method
	 * @return array
	 */
	public function apply_watermark( $data, $attachment_id, $method = '' ) {
		$post = get_post( (int) $attachment_id );
		$post_id = ( ! empty( $post ) ? (int) $post->post_parent : 0 );

		if ( $attachment_id == $this->options['watermark_image']['url'] ) {
			// this is the current watermark, do not apply
			return array( 'error' => __( 'Watermark prevented, this is your selected watermark image', 'image-watermark' ) );
		}

		// something went wrong or is it automatic mode?
		if ( $method !== 'manual' && (
			$this->is_admin === true && ! (
				( isset( $this->options['watermark_cpt_on'][0] ) && $this->options['watermark_cpt_on'][0] === 'everywhere' ) || ( $post_id > 0 && in_array( get_post_type( $post_id ), array_keys( $this->options['watermark_cpt_on'] ) ) === true )
				)
			)
		)
			return $data;

		if ( apply_filters( 'iw_watermark_display', $attachment_id ) === false )
			return $data;

		$upload_dir = wp_upload_dir();

		// is this really an image?
		if ( getimagesize( $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'] ) !== false ) {
			// remove the watermark if this image was already watermarked, not === because the database can't hold booleans
			if ( get_post_meta( $attachment_id, $this->is_watermarked_metakey ) == true )
				$this->remove_watermark( $data, $attachment_id, 'manual' );

			// create a backup if this is enabled
			if ( $this->options['backup']['backup_image'] )
				$this->do_backup( $data, $upload_dir, $attachment_id );

			// loop through active image sizes
			foreach ( $this->options['watermark_on'] as $image_size => $active_size ) {

				if ( $active_size === 1 ) {
					switch ( $image_size ) {
						case 'full':
							$filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];
							break;

						default:
							if ( ! empty( $data['sizes'] ) && array_key_exists( $image_size, $data['sizes'] ) )
								$filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . dirname( $data['file'] ) . DIRECTORY_SEPARATOR . $data['sizes'][$image_size]['file'];
							else
							// early getaway
								continue 2;
					}

					do_action( 'iw_before_apply_watermark', $attachment_id, $image_size );

					// apply watermark
					$this->do_watermark( $attachment_id, $filepath, $image_size, $upload_dir );

					do_action( 'iw_after_apply_watermark', $attachment_id, $image_size );
				}
			}
			// update watermark status
			update_post_meta( $attachment_id, $this->is_watermarked_metakey, true );
		}

		// pass forward attachment metadata
		return $data;
	}

	/**
	 * Remove watermark from selected image sizes.
	 *
	 * @param array	$data
	 * @param int|string $attachment_id	Attachment ID
	 * @param string $method
	 * @return array
	 */
	private function remove_watermark( $data, $attachment_id, $method = '' ) {
		if ( $method !== 'manual' ) {
			return $data;
		}

		$upload_dir = wp_upload_dir();

		// is this really an image?
		if ( getimagesize( $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'] ) !== false ) {

			// live file path (probably watermarked)
			$filepath = get_attached_file( $attachment_id );

			// backup file path (not watermarked)
			$backup_filepath = $this->get_image_backup_filepath( get_post_meta( $attachment_id, '_wp_attached_file', true ) );

			// replace the image in uploads with our backup if one exists
			if ( file_exists( $backup_filepath ) ) {
				if ( ! copy( $backup_filepath, $filepath ) ) {
					// Failed to copy
				}
			}
			// if no backup exists, use the current full-size image to regenerate
			// if the "full" size is enabled for watermarks and no backup has been made the removal of watermarks can't be done
			// regenerate metadata (and thumbs)
			$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );

			// update attachment metadata with new metadata
			wp_update_attachment_metadata( $attachment_id, $metadata );

			// update watermark status
			update_post_meta( $attachment_id, $this->is_watermarked_metakey, false );

			// ureturn the attachment metadata
			return wp_get_attachment_metadata( $attachment_id );
		}
		return false;
	}

	/**
	 * Apply watermark to image.
	 *
	 * @param int $attachment_id Attachment ID
	 * @param string $image_path Path to the file
	 * @param string $image_size Image size
	 * @param array	$upload_dir	Upload media data
	 * @return void
	 */
	public function do_watermark( $attachment_id, $image_path, $image_size, $upload_dir ) {
		$options = apply_filters( 'iw_watermark_options', $this->options );

		// get image mime type
		$mime = wp_check_filetype( $image_path );

		// get watermark path
		$watermark_file = wp_get_attachment_metadata( $options['watermark_image']['url'], true );
		$watermark_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];

		// imagick extension
		if ( $this->extension === 'imagick' ) {
			// create image resource
			$image = new Imagick( $image_path );

			// create watermark resource
			$watermark = new Imagick( $watermark_path );

			// set transparency
			$image->setImageOpacity( round( 1 - (float) ( $options['watermark_image']['transparent'] / 100 ), 2 ) );

			// set compression quality
			if ( $mime['type'] === 'image/jpeg' ) {
				$image->setImageCompressionQuality( $options['watermark_image']['quality'] );
				$image->setImageCompression( imagick::COMPRESSION_JPEG );
			} else
				$image->setImageCompressionQuality( $options['watermark_image']['quality'] );

			// set image output to progressive
			if ( $options['watermark_image']['jpeg_format'] === 'progressive' )
				$image->setImageInterlaceScheme( Imagick::INTERLACE_PLANE );

			// get image dimensions
			$image_dim = $image->getImageGeometry();

			// get watermark dimensions
			$watermark_dim = $watermark->getImageGeometry();

			// calculate watermark new dimensions
			list( $width, $height ) = $this->calculate_watermark_dimensions( $image_dim['width'], $image_dim['height'], $watermark_dim['width'], $watermark_dim['height'], $options );

			// resize watermark
			$watermark->resizeImage( $width, $height, imagick::FILTER_CATROM, 1 );

			// calculate image coordinates
			list( $dest_x, $dest_y ) = $this->calculate_image_coordinates( $image_dim['width'], $image_dim['height'], $width, $height, $options );

			// combine two images together
			$image->compositeImage( $watermark, Imagick::COMPOSITE_OVERLAY, $dest_x, $dest_y, Imagick::CHANNEL_ALL );

			// save watermarked image
			$image->writeImage( $image_path );

			// clear image memory
			$image->clear();
			$image->destroy();
			$image = null;

			// clear watermark memory
			$watermark->clear();
			$watermark->destroy();
			$watermark = null;
			// gd extension
		} else {
			// get image resource
			$image = $this->get_image_resource( $image_path, $mime['type'] );

			if ( $image !== false ) {
				// add watermark image to image
				$image = $this->add_watermark_image( $image, $options, $upload_dir );

				if ( $image !== false ) {
					// save watermarked image
					$this->save_image_file( $image, $mime['type'], $image_path, $options['watermark_image']['quality'] );

					// clear watermark memory
					imagedestroy( $image );

					$image = null;
				}
			}
		}
	}

	/**
	 * Make a backup of the full size image.
	 *
	 * @param array $data
	 * @param string $upload_dir
	 * @param int $attachment_id
	 * @return bool
	 */
	private function do_backup( $data, $upload_dir, $attachment_id ) {
		// get the filepath for the backup image we're creating
		$backup_filepath = $this->get_image_backup_filepath( $data['file'] );

		// make sure the backup isn't created yet
		if ( ! file_exists( $backup_filepath ) ) {
			// the original (full size) image
			$filepath = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];
			$mime = wp_check_filetype( $filepath );

			// get image resource
			$image = $this->get_image_resource( $filepath, $mime['type'] );

			if ( false !== $image ) {
				// create backup directory if needed
				wp_mkdir_p( $this->get_image_backup_folder_location( $data['file'] ) );

				// save backup image
				$this->save_image_file( $image, $mime['type'], $backup_filepath, $this->options['backup']['backup_quality'] );

				// clear backup memory
				imagedestroy( $image );
				$image = null;
			}
		}
	}

	/**
	 * Get image resource accordingly to mimetype.
	 *
	 * @param string $filepath
	 * @param string $mime_type
	 * @return resource
	 */
	private function get_image_resource( $filepath, $mime_type ) {
		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/pjpeg':
				$image = imagecreatefromjpeg( $filepath );
				break;

			case 'image/png':
				$image = imagecreatefrompng( $filepath );

				imagefilledrectangle( $image, 0, 0, imagesx( $image ), imagesy( $image ), imagecolorallocatealpha( $image, 255, 255, 255, 127 ) );
				break;

			default:
				$image = false;
		}

		if ( is_resource( $image ) ) {
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}

		return $image;
	}

	/**
	 * Get image filename without the uploaded folders.
	 *
	 * @param string $filepath
	 * @return string $filename
	 */
	private function get_image_filename( $filepath ) {
		return basename( $filepath );
	}

	/**
	 * Get image backup folder.
	 *
	 * @param string $filepath
	 * @return string $image_backup_folder
	 */
	private function get_image_backup_folder_location( $filepath ) {
		$path = explode( DIRECTORY_SEPARATOR, $filepath );
		array_pop( $path );
		$path = implode( DIRECTORY_SEPARATOR, $path );
		
		// Multisite?
		/* if ( is_multisite() && ! is_main_site() ) {
		  $path = 'sites' . DIRECTORY_SEPARATOR . get_current_blog_id() . DIRECTORY_SEPARATOR . $path;
		  } */
		
		return IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Get image resource from the backup folder (if available).
	 *
	 * @param string $filepath
	 * @return string $backup_filepath
	 */
	private function get_image_backup_filepath( $filepath ) {
		// Multisite?
		/* if ( is_multisite() && ! is_main_site() ) {
		  $filepath = 'sites' . DIRECTORY_SEPARATOR . get_current_blog_id() . DIRECTORY_SEPARATOR . $filepath;
		  } */
		return IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . $filepath;
	}

	/**
	 * Delete the image backup if one exists.
	 *
	 * @param int $attachment_id
	 * @return bool $force_delete
	 */
	public function delete_attachment( $attachment_id ) {
		// see get_attached_file() in wp-includes/post.php
		$filepath = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$backup_filepath = $this->get_image_backup_filepath( $filepath );

		if ( file_exists( $backup_filepath ) ) {
			unlink( $backup_filepath );
		}
	}

	/**
	 * Create admin notice when we can't create the backup folder.
	 * 
	 * @return	void
	 */
	function folder_writable_admin_notice() {
		if ( current_user_can( 'manage_options' ) && true !== $this->is_backup_folder_writable ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php _e( 'Image Watermark', 'image-watermark' ); ?> - <?php _e( 'Image backup', 'image-watermark' ); ?>: <?php _e( "Your uploads folder is not writable so we can't create a backup of your image uploads. We've disabled this feature for now.", 'image-watermark' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Calculate watermark dimensions.
	 *
	 * @param $image_width Image width
	 * @param $image_height Image height
	 * @param $watermark_width Watermark width
	 * @param $watermark_height	Watermark height
	 * @param $options Options
	 * @return array Watermark new dimensions
	 */
	private function calculate_watermark_dimensions( $image_width, $image_height, $watermark_width, $watermark_height, $options ) {
		// custom
		if ( $options['watermark_image']['watermark_size_type'] === 1 ) {
			$width = $options['watermark_image']['absolute_width'];
			$height = $options['watermark_image']['absolute_height'];
			// scale
		} elseif ( $options['watermark_image']['watermark_size_type'] === 2 ) {
			$ratio = $image_width * $options['watermark_image']['width'] / 100 / $watermark_width;

			$width = (int) ( $watermark_width * $ratio );
			$height = (int) ( $watermark_height * $ratio );

			// if watermark scaled height is bigger then image watermark
			if ( $height > $image_height ) {
				$width = (int) ( $image_height * $width / $height );
				$height = $image_height;
			}
			// original
		} else {
			$width = $watermark_width;
			$height = $watermark_height;
		}

		return array( $width, $height );
	}

	/**
	 * Calculate image coordinates for watermark.
	 *
	 * @param $image_width Image width
	 * @param $image_height	Image height
	 * @param $watermark_width Watermark width
	 * @param $watermark_height	Watermark height
	 * @param $options Options
	 * @return array Image coordinates
	 */
	private function calculate_image_coordinates( $image_width, $image_height, $watermark_width, $watermark_height, $options ) {
		switch ( $options['watermark_image']['position'] ) {
			case 'top_left':
				$dest_x = $dest_y = 0;
				break;

			case 'top_center':
				$dest_x = ( $image_width / 2 ) - ( $watermark_width / 2 );
				$dest_y = 0;
				break;

			case 'top_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = 0;
				break;

			case 'middle_left':
				$dest_x = 0;
				$dest_y = ( $image_height / 2 ) - ( $watermark_height / 2 );
				break;

			case 'middle_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = ( $image_height / 2 ) - ( $watermark_height / 2 );
				break;

			case 'bottom_left':
				$dest_x = 0;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom_center':
				$dest_x = ( $image_width / 2 ) - ( $watermark_width / 2 );
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'middle_center':
			default:
				$dest_x = ( $image_width / 2 ) - ( $watermark_width / 2 );
				$dest_y = ( $image_height / 2 ) - ( $watermark_height / 2 );
		}

		$dest_x += $options['watermark_image']['offset_width'];
		$dest_y += $options['watermark_image']['offset_height'];

		return array( $dest_x, $dest_y );
	}

	/**
	 * Add watermark image to an image.
	 *
	 * @param resource $image Image resource
	 * @param array	$options Plugin options
	 * @param array	$upload_dir	WP upload dir data
	 * @return resource	Watermarked image
	 */
	private function add_watermark_image( $image, $options, $upload_dir ) {
		$watermark_file = wp_get_attachment_metadata( $options['watermark_image']['url'], true );
		$url = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];
		$watermark_file_info = getimagesize( $url );

		switch ( $watermark_file_info['mime'] ) {
			case 'image/jpeg':
			case 'image/pjpeg':
				$watermark = imagecreatefromjpeg( $url );
				break;

			case 'image/gif':
				$watermark = imagecreatefromgif( $url );
				break;

			case 'image/png':
				$watermark = imagecreatefrompng( $url );
				break;

			default:
				return false;
		}

		// get image dimensions
		$image_width = imagesx( $image );
		$image_height = imagesy( $image );

		// calculate watermark new dimensions
		list( $w, $h ) = $this->calculate_watermark_dimensions( $image_width, $image_height, imagesx( $watermark ), imagesy( $watermark ), $options );

		// calculate image coordinates
		list( $dest_x, $dest_y ) = $this->calculate_image_coordinates( $image_width, $image_height, $w, $h, $options );

		// combine two images together
		$this->imagecopymerge_alpha( $image, $this->resize( $watermark, $w, $h, $watermark_file_info ), $dest_x, $dest_y, 0, 0, $w, $h, $options['watermark_image']['transparent'] );

		if ( $options['watermark_image']['jpeg_format'] === 'progressive' )
			imageinterlace( $image, true );

		return $image;
	}

	/**
	 * Create a new image function.
	 *
	 * @param resource $dst_im
	 * @param resource $src_im
	 * @param int $dst_x
	 * @param int $dst_y
	 * @param int $src_x
	 * @param int $src_y
	 * @param int $src_w
	 * @param int $src_h
	 * @param int $pct
	 */
	private function imagecopymerge_alpha( $dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct ) {
		// create a cut resource
		$cut = imagecreatetruecolor( $src_w, $src_h );

		// copy relevant section from background to the cut resource
		imagecopy( $cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h );

		// copy relevant section from watermark to the cut resource
		imagecopy( $cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h );

		// insert cut resource to destination image
		imagecopymerge( $dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct );
	}

	/**
	 * Resize image.
	 *
	 * @param resource $image Image resource
	 * @param int $width Image width
	 * @param int $height Image height
	 * @param array	$info Image data
	 * @return resource	Resized image
	 */
	private function resize( $image, $width, $height, $info ) {
		$new_image = imagecreatetruecolor( $width, $height );

		// check if this image is PNG, then set if transparent
		if ( $info[2] === 3 ) {
			imagealphablending( $new_image, false );
			imagesavealpha( $new_image, true );
			imagefilledrectangle( $new_image, 0, 0, $width, $height, imagecolorallocatealpha( $new_image, 255, 255, 255, 127 ) );
		}

		imagecopyresampled( $new_image, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1] );

		return $new_image;
	}

	/**
	 * Save image from image resource.
	 *
	 * @param resource $image Image resource
	 * @param string $mime_type	Image mime type
	 * @param string $filepath	Path where image should be saved
	 * @return void
	 */
	private function save_image_file( $image, $mime_type, $filepath, $quality ) {
		switch ( $mime_type ) {
			case 'image/jpeg':
			case 'image/pjpeg':
				imagejpeg( $image, $filepath, $quality );
				break;

			case 'image/png':
				imagepng( $image, $filepath, (int) round( 9 - ( 9 * $quality / 100 ), 0 ) );
				break;
		}
	}

	/**
	 * Add links to support forum.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_extend_links( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) )
			return $links;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin ) {
			return array_merge(
			$links, array( sprintf( '<a href="http://www.dfactory.eu/support/forum/image-watermark/" target="_blank">%s</a>', __( 'Support', 'image-watermark' ) ) )
			);
		}

		return $links;
	}

	/**
	 * Add links to settings page.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	function plugin_settings_link( $links, $file ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) )
			return $links;

		static $plugin;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin ) {
			$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php' ) . '?page=watermark-options', __( 'Settings', 'image-watermark' ) );
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

}

/**
 * Get instance of main class.
 *
 * @return object Instance
 */
function Image_Watermark() {
	static $instance;

	// first call to instance() initializes the plugin
	if ( $instance === null || ! ( $instance instanceof Image_Watermark ) )
		$instance = Image_Watermark::instance();

	return $instance;
}

Image_Watermark();
