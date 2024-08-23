<?php
/*
Plugin Name: Image Watermark
Description: Image Watermark allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.
Version: 1.8.4
Author: dFactory
Author URI: http://www.dfactory.co/
Plugin URI: http://www.dfactory.co/products/image-watermark/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: image-watermark
Domain Path: /languages

Image Watermark
Copyright (C) 2013-2024, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Image Watermark class.
 *
 * @class Image_Watermark
 * @version	1.8.4
 */
final class Image_Watermark {

	private static $instance;
	private $is_admin = true;
	private $extension = false;
	private $allowed_mime_types = [
		'image/webp',
		'image/jpeg',
		'image/pjpeg',
		'image/png'
	];
	private $is_watermarked_metakey = 'iw-is-watermarked';
	public $is_backup_folder_writable = null;
	public $extensions;
	public $defaults = [
		'options'	 => [
			'watermark_on'		 => [],
			'watermark_cpt_on'	 => [ 'everywhere' ],
			'watermark_image'	 => [
				'extension'				 => '',
				'url'					 => 0,
				'width'					 => 80,
				'plugin_off'			 => 0,
				'frontend_active'		 => false,
				'manual_watermarking'	 => 0,
				'position'				 => 'bottom_right',
				'watermark_size_type'	 => 2,
				'offset_unit'			 => 'pixels',
				'offset_width'			 => 0,
				'offset_height'			 => 0,
				'absolute_width'		 => 0,
				'absolute_height'		 => 0,
				'transparent'			 => 50,
				'quality'				 => 90,
				'jpeg_format'			 => 'baseline',
				'deactivation_delete'	 => false,
				'media_library_notice'	 => true
			],
			'image_protection'	 => [
				'rightclick'	 => 0,
				'draganddrop'	 => 0,
				'forlogged'		 => 0
			],
			'backup'			 => [
				'backup_image'	 => true,
				'backup_quality' => 90
			]
		],
		'version'	 => '1.7.4'
	];
	public $options = [];

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// define plugin constants
		$this->define_constants();

		// activation hooks
		register_activation_hook( __FILE__, [ $this, 'activate_watermark' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_watermark' ] );

		// settings
		$options = get_option( 'image_watermark_options', $this->defaults['options'] );

		$this->options = array_merge( $this->defaults['options'], $options );
		$this->options['watermark_image'] = array_merge( $this->defaults['options']['watermark_image'], $options['watermark_image'] );
		$this->options['image_protection'] = array_merge( $this->defaults['options']['image_protection'], $options['image_protection'] );
		$this->options['backup'] = array_merge( $this->defaults['options']['backup'], isset( $options['backup'] ) ? $options['backup'] : [] );

		include_once( IMAGE_WATERMARK_PATH . 'includes/class-update.php' );
		include_once( IMAGE_WATERMARK_PATH . 'includes/class-settings.php' );

		// actions
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_enqueue_media', [ $this, 'wp_enqueue_media' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
		add_action( 'load-upload.php', [ $this, 'watermark_bulk_action' ] );
		add_action( 'admin_init', [ $this, 'update_plugin' ] );
		add_action( 'admin_init', [ $this, 'check_extensions' ] );
		add_action( 'admin_notices', [ $this, 'bulk_admin_notices' ] );
		add_action( 'delete_attachment', [ $this, 'delete_attachment' ] );
		add_action( 'wp_ajax_iw_watermark_bulk_action', [ $this, 'watermark_action_ajax' ] );

		// filters
		add_filter( 'plugin_action_links_' . IMAGE_WATERMARK_BASENAME, [ $this, 'plugin_settings_link' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_extend_links' ], 10, 2 );
		add_filter( 'wp_handle_upload', [ $this, 'handle_upload_files' ] );
		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields_to_edit' ], 10, 2 );

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
				} else
					$this->is_backup_folder_writable = false;
			} else
				$this->is_backup_folder_writable = false;

			if ( $this->is_backup_folder_writable !== true ) {
				// disable backup setting
				$this->options['backup']['backup_image'] = false;

				update_option( 'image_watermark_options', $this->options );
			}

			add_action( 'admin_notices', [ $this, 'folder_writable_admin_notice' ] );
		}
	}

	/**
	 * Disable object cloning.
	 *
	 * @return void
	 */
	public function __clone() {}

	/**
	 * Disable unserializing of the class.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Main plugin instance, insures that only one instance of the plugin exists in memory at one time.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( self::$instance === null )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Setup plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		define( 'IMAGE_WATERMARK_URL', plugins_url( '', __FILE__ ) );
		define( 'IMAGE_WATERMARK_PATH', plugin_dir_path( __FILE__ ) );
		define( 'IMAGE_WATERMARK_BASENAME', plugin_basename( __FILE__ ) );
		define( 'IMAGE_WATERMARK_REL_PATH', dirname( IMAGE_WATERMARK_BASENAME ) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public function activate_watermark() {
		// add default options
		add_option( 'image_watermark_options', $this->defaults['options'], null, false );
		add_option( 'image_watermark_version', $this->defaults['version'], null, false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate_watermark() {
		// remove options from database?
		if ( $this->options['watermark_image']['deactivation_delete'] )
			delete_option( 'image_watermark_options' );
	}

	/**
	 * Plugin update, fix for version < 1.5.0.
	 *
	 * @return void
	 */
	public function update_plugin() {
		if ( ! current_user_can( 'install_plugins' ) )
			return;

		$db_version = get_option( 'image_watermark_version' );
		$db_version = ! ( $db_version ) && ( get_option( 'df_watermark_installed' ) != false ) ? get_option( 'version' ) : $db_version;

		if ( $db_version != false ) {
			if ( version_compare( $db_version, '1.5.0', '<' ) ) {
				$options = [];

				$old_new = [
					'df_watermark_on'			=> 'watermark_on',
					'df_watermark_cpt_on'		=> 'watermark_cpt_on',
					'df_watermark_image'		=> 'watermark_image',
					'df_image_protection'		=> 'image_protection',
					'df_watermark_installed'	=> '',
					'version'					=> '',
					'image_watermark_version'	=> '',
				];

				foreach ( $old_new as $old => $new ) {
					if ( $new )
						$options[$new] = get_option( $old );

					delete_option( $old );
				}

				add_option( 'image_watermark_options', $options, null, false );
				add_option( 'image_watermark_version', $this->defaults['version'], null, false );
			}
		}
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'image-watermark', false, IMAGE_WATERMARK_REL_PATH . '/languages/' );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function wp_enqueue_media( $page ) {
		wp_enqueue_style( 'watermark-style', IMAGE_WATERMARK_URL . '/css/image-watermark.css', [], $this->defaults['version'] );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @global $pagenow
	 * @return void
	 */
	public function admin_enqueue_scripts( $page ) {
		global $pagenow;

		wp_register_style( 'watermark-style', IMAGE_WATERMARK_URL . '/css/image-watermark.css', [], $this->defaults['version'] );

		if ( $page === 'settings_page_watermark-options' ) {
			wp_enqueue_media();

			wp_enqueue_script( 'image-watermark-upload-manager', IMAGE_WATERMARK_URL . '/js/admin-upload.js', [], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'title'			=> __( 'Select watermark', 'image-watermark' ),
				'originalSize'	=> __( 'Original size', 'image-watermark' ),
				'noSelectedImg'	=> __( 'Watermak has not been selected yet.', 'image-watermark' ),
				'notAllowedImg'	=> __( 'This image is not supported as watermark. Use JPEG, PNG or GIF.', 'image-watermark' ),
				'px'			=> __( 'px', 'image-watermark' ),
				'frame'			=> 'select',
				'button'		=> [ 'text' => __( 'Add watermark', 'image-watermark' ) ],
				'multiple'		=> false
			];

			wp_add_inline_script( 'image-watermark-upload-manager', 'var iwArgsUpload = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

			wp_enqueue_script( 'image-watermark-admin-settings', IMAGE_WATERMARK_URL . '/js/admin-settings.js', [ 'jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-slider' ], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'resetToDefaults' => __( 'Are you sure you want to reset settings to defaults?', 'image-watermark' )
			];

			wp_add_inline_script( 'image-watermark-admin-settings', 'var iwArgsSettings = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

			wp_enqueue_style( 'wp-like-ui-theme', IMAGE_WATERMARK_URL . '/css/wp-like-ui-theme.css', [], $this->defaults['version'] );
			wp_enqueue_style( 'watermark-style' );

			wp_enqueue_script( 'postbox' );
		}

		if ( $pagenow === 'upload.php' ) {
			if ( $this->options['watermark_image']['manual_watermarking'] == 1 && current_user_can( 'upload_files' ) ) {
				wp_enqueue_script( 'image-watermark-admin-media', IMAGE_WATERMARK_URL . '/js/admin-media.js', [ 'jquery' ], $this->defaults['version'], false );

				// prepare script data
				$script_data = [
					'backupImage'		=> (bool) $this->options['backup']['backup_image'],
					'applyWatermark'	=> __( 'Apply watermark', 'image-watermark' ),
					'removeWatermark'	=> __( 'Remove watermark', 'image-watermark' )
				];

				wp_add_inline_script( 'image-watermark-admin-media', 'var iwArgsMedia = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
			}

			wp_enqueue_style( 'watermark-style' );
		}

		// image modal could be loaded in various places
		if ( $this->options['watermark_image']['manual_watermarking'] == 1 ) {
			wp_enqueue_script( 'image-watermark-admin-image-actions', IMAGE_WATERMARK_URL . '/js/admin-image-actions.js', [ 'jquery' ], $this->defaults['version'], true );

			// prepare script data
			$script_data = [
				'backup_image'		=> (bool) $this->options['backup']['backup_image'],
				'_nonce'			=> wp_create_nonce( 'image-watermark' ),
				'__applied_none'	=> __( 'Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ),
				'__applied_one'		=> __( 'Watermark was successfully applied to 1 image.', 'image-watermark' ),
				'__applied_multi'	=> __( 'Watermark was successfully applied to %s images.', 'image-watermark' ),
				'__removed_none'	=> __( 'Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ),
				'__removed_one'		=> __( 'Watermark was successfully removed from 1 image.', 'image-watermark' ),
				'__removed_multi'	=> __( 'Watermark was successfully removed from %s images.', 'image-watermark' ),
				'__skipped'			=> __( 'Skipped files', 'image-watermark' ),
				'__running'			=> __( 'Bulk action is currently running, please wait.', 'image-watermark' ),
				'__dismiss'			=> __( 'Dismiss this notice.' ) // WordPress default string
			];

			wp_add_inline_script( 'image-watermark-admin-image-actions', 'var iwArgsImageActions = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
		}
	}

	/**
	 * Enqueue frontend script with 'no right click' and 'drag and drop' functions.
	 *
	 * @return void
	 */
	public function wp_enqueue_scripts() {
		$right_click = true;

		if ( ( $this->options['image_protection']['forlogged'] == 0 && is_user_logged_in() ) || ( $this->options['image_protection']['draganddrop'] == 0 && $this->options['image_protection']['rightclick'] == 0 ) )
			$right_click = false;

		if ( apply_filters( 'iw_block_right_click', (bool) $right_click ) === true ) {
			wp_enqueue_script( 'image-watermark-no-right-click', IMAGE_WATERMARK_URL . '/js/no-right-click.js', [], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'rightclick'	=> ( $this->options['image_protection']['rightclick'] == 1 ? 'Y' : 'N' ),
				'draganddrop'	=> ( $this->options['image_protection']['draganddrop'] == 1 ? 'Y' : 'N' )
			];

			wp_add_inline_script( 'image-watermark-no-right-click', 'var iwArgsNoRightClick = ' . wp_json_encode( $script_data ) . ";\n", 'before' );
		}
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
	 * @param resource $file
	 * @return resource
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
				if ( $this->options['watermark_image']['plugin_off'] == 1 && wp_attachment_is_image( $this->options['watermark_image']['url'] ) && in_array( $file['type'], $this->allowed_mime_types ) )
					add_filter( 'wp_generate_attachment_metadata', [ $this, 'apply_watermark' ], 10, 2 );
			// frontend
			} else {
				if ( $this->options['watermark_image']['frontend_active'] == 1 && wp_attachment_is_image( $this->options['watermark_image']['url'] ) && in_array( $file['type'], $this->allowed_mime_types ) )
					add_filter( 'wp_generate_attachment_metadata', [ $this, 'apply_watermark' ], 10, 2 );
			}
		}

		return $file;
	}

	/**
	 * Add watermark buttons on attachment image locations.
	 *
	 * @param array $form_fields
	 * @param object $post
	 * return array
	 */
	public function attachment_fields_to_edit( $form_fields, $post ) {
		if ( $this->options['watermark_image']['manual_watermarking'] == 1 && $this->options['backup']['backup_image'] ) {
			$data = wp_get_attachment_metadata( $post->ID, false );

			// is this really an image?
			if ( in_array( get_post_mime_type( $post->ID ), $this->allowed_mime_types ) && is_array( $data ) ) {
				$form_fields['image_watermark'] = [
					'show_in_edit'	=> false,
					'tr'			=> '
					<div id="image_watermark_buttons"' . ( get_post_meta( $post->ID, $this->is_watermarked_metakey, true ) ? ' class="watermarked"' : '' ) . ' data-id="' . $post->ID . '" style="display: none;">
						<label class="setting">
							<span class="name">' . __( 'Image Watermark', 'image-watermark' ) . '</span>
							<span class="value" style="width: 63%"><a href="#" class="iw-watermark-action" data-action="applywatermark" data-id="' . $post->ID . '">' . __( 'Apply watermark', 'image-watermark' ) . '</a> | <a href="#" class="iw-watermark-action delete-watermark" data-action="removewatermark" data-id="' . $post->ID . '">' . __( 'Remove watermark', 'image-watermark' ) . '</a></span>
						</label>
						<div class="clear"></div>
					</div>
					<script>
						jQuery( function() {
							if ( typeof watermarkImageActions !== "undefined" )
								jQuery( "#image_watermark_buttons" ).show();
						} );
					</script>'
				];
			}
		}

		return $form_fields;
	}

	/**
	 * Apply watermark for selected images on media page.
	 *
	 * @return void
	 */
	public function watermark_action_ajax() {
		// Security & data check
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! isset( $_POST['_iw_nonce'] ) || ! isset( $_POST['iw-action'] ) || ! isset( $_POST['attachment_id'] ) || ! is_numeric( $_POST['attachment_id'] ) || ! wp_verify_nonce( $_POST['_iw_nonce'], 'image-watermark' ) || ! current_user_can( 'upload_files' ) )
			wp_send_json_error( __( 'Cheatin uh?', 'image-watermark' ) );

		// cast post id
		$post_id = (int) $_POST['attachment_id'];

		// sanitize action name
		$action = sanitize_key( $_POST['iw-action'] );
		$action = in_array( $action, [ 'applywatermark', 'removewatermark' ], true ) ? $action : false;

		// only if manual watermarking is turned and we have a valid action
		// if the action is NOT "removewatermark" we also require a watermark image to be set
		if ( $post_id > 0 && $action && $this->options['watermark_image']['manual_watermarking'] == 1 && ( wp_attachment_is_image( $this->options['watermark_image']['url'] ) || $action == 'removewatermark' ) ) {
			$data = wp_get_attachment_metadata( $post_id, false );

			// is this really an image?
			if ( in_array( get_post_mime_type( $post_id ), $this->allowed_mime_types ) && is_array( $data ) ) {
				if ( $action === 'applywatermark' ) {
					$success = $this->apply_watermark( $data, $post_id, 'manual' );

					if ( ! empty( $success['error'] ) )
						wp_send_json_success( $success['error'] );
					else
						wp_send_json_success( 'watermarked' );
				} elseif ( $action === 'removewatermark' ) {
					$success = $this->remove_watermark( $data, $post_id, 'manual' );

					if ( $success )
						wp_send_json_success( 'watermarkremoved' );
					else
						wp_send_json_success( 'skipped' );
				}
			} else
				wp_send_json_success( 'skipped' );
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
			$action = $wp_list_table->current_action();
			$action = in_array( $action, [ 'applywatermark', 'removewatermark' ], true ) ? $action : false;

			// only if manual watermarking is turned and we have a valid action
			// if the action is NOT "removewatermark" we also require a watermark image to be set
			if ( $action && $this->options['watermark_image']['manual_watermarking'] == 1 && ( wp_attachment_is_image( $this->options['watermark_image']['url'] ) || $action == 'removewatermark' ) ) {
				// security check
				check_admin_referer( 'bulk-media' );

				$location = esc_url( remove_query_arg( [ 'watermarked', 'watermarkremoved', 'skipped', 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted' ], wp_get_referer() ) );

				if ( ! $location )
					$location = 'upload.php';

				$location = esc_url( add_query_arg( 'paged', $wp_list_table->get_pagenum(), $location ) );

				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if ( isset( $_REQUEST['media'] ) )
					$post_ids = array_map( 'intval', $_REQUEST['media'] );

				// do we have selected attachments?
				if ( $post_ids ) {
					$watermarked = $watermarkremoved = $skipped = 0;
					$messages = [];

					foreach ( $post_ids as $post_id ) {
						$data = wp_get_attachment_metadata( $post_id, false );

						// is this really an image?
						if ( in_array( get_post_mime_type( $post_id ), $this->allowed_mime_types ) && is_array( $data ) ) {
							if ( $action === 'applywatermark' ) {
								$success = $this->apply_watermark( $data, $post_id, 'manual' );
								if ( ! empty( $success['error'] ) )
									$messages[] = $success['error'];
								else {
									$watermarked++;
									$watermarkremoved = -1;
								}
							} elseif ( $action === 'removewatermark' ) {
								$success = $this->remove_watermark( $data, $post_id, 'manual' );

								if ( $success )
									$watermarkremoved++;
								else
									$skipped++;

								$watermarked = -1;
							}
						} else
							$skipped++;
					}

					$location = esc_url( add_query_arg( [ 'watermarked' => $watermarked, 'watermarkremoved' => $watermarkremoved, 'skipped' => $skipped, 'messages' => $messages ], $location ), null, '' );
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
	 * @return void
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

				if ( isset( $_GET['mode'] ) && in_array( $_GET['mode'], [ 'grid', 'list' ] ) )
					$mode = $_GET['mode'];

				// display notice in grid mode only
				if ( $mode === 'grid' ) {
					// get current admin url
					$query_string = [];

					parse_str( $_SERVER['QUERY_STRING'], $query_string );

					$current_url = esc_url( add_query_arg( array_merge( (array) $query_string, [ 'iw_action' => 'hide_library_notice' ] ), '', admin_url( trailingslashit( $pagenow ) ) ) );

					echo '<div class="error notice"><p>' . sprintf( __( '<strong>Image Watermark:</strong> Bulk watermarking is available in list mode only, under <em>Bulk Actions</em> dropdown. <a href="%1$s">Got to List Mode</a> or <a href="%2$s">Hide this notice</a>', 'image-watermark' ), esc_url( admin_url( 'upload.php?mode=list' ) ), esc_url( $current_url ) ) . '</p></div>';
				}
			}

			if ( isset( $_REQUEST['watermarked'], $_REQUEST['watermarkremoved'], $_REQUEST['skipped'] ) && $post_type === 'attachment' ) {
				$watermarked = (int) $_REQUEST['watermarked'];
				$watermarkremoved = (int) $_REQUEST['watermarkremoved'];
				$skipped = (int) $_REQUEST['skipped'];

				if ( $watermarked === 0 )
					echo '<div class="error"><p>' . __( 'Watermark could not be applied to selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ) . ($skipped > 0 ? ' ' . __( 'Images skipped', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				elseif ( $watermarked > 0 )
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was successfully applied to 1 image.', 'Watermark was successfully applied to %s images.', $watermarked, 'image-watermark' ), number_format_i18n( $watermarked ) ) . ($skipped > 0 ? ' ' . __( 'Skipped files', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';

				if ( $watermarkremoved === 0 )
					echo '<div class="error"><p>' . __( 'Watermark could not be removed from selected files or no valid images (JPEG, PNG) were selected.', 'image-watermark' ) . ($skipped > 0 ? ' ' . __( 'Images skipped', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				elseif ( $watermarkremoved > 0 )
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was successfully removed from 1 image.', 'Watermark was successfully removed from %s images.', $watermarkremoved, 'image-watermark' ), number_format_i18n( $watermarkremoved ) ) . ($skipped > 0 ? ' ' . __( 'Skipped files', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';

				$_SERVER['REQUEST_URI'] = esc_url( remove_query_arg( [ 'watermarked', 'skipped' ], $_SERVER['REQUEST_URI'] ) );
			}
		}
	}

	/**
	 * Check whether ImageMagick extension is available.
	 *
	 * @return bool
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
		if ( array_diff( [ 'clear', 'destroy', 'valid', 'getimage', 'writeimage', 'getimagegeometry', 'getimageformat', 'setimageformat', 'setimagecompression', 'setimagecompressionquality', 'scaleimage' ], get_class_methods( 'Imagick' ) ) )
			return false;

		return true;
	}

	/**
	 * Check whether GD extension is available.
	 *
	 * @return bool
	 */
	public function check_gd( $args = [] ) {
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
		$attachment_id = (int) $attachment_id;
		$post = get_post( $attachment_id );
		$post_id = ( ! empty( $post ) ? (int) $post->post_parent : 0 );

		if ( $attachment_id === (int) $this->options['watermark_image']['url'] ) {
			// this is the current watermark, do not apply
			return [ 'error' => __( 'Watermark prevented, this is your selected watermark image', 'image-watermark' ) ];
		}

		// something went wrong or is it automatic mode?
		if ( $method !== 'manual' && ( $this->is_admin === true && ! ( ( isset( $this->options['watermark_cpt_on'][0] ) && $this->options['watermark_cpt_on'][0] === 'everywhere' ) || ( $post_id > 0 && in_array( get_post_type( $post_id ), array_keys( $this->options['watermark_cpt_on'] ) ) === true ) ) ) )
			return $data;

		if ( apply_filters( 'iw_watermark_display', $attachment_id ) === false )
			return $data;

		// get upload dir data
		$upload_dir = wp_upload_dir();

		// assign original (full) file
		$original_file = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $data['file'];

		// is this really an image?
		if ( getimagesize( $original_file, $original_image_info ) !== false ) {
			$metadata = $this->get_image_metadata( $original_image_info );

			// remove the watermark if this image was already watermarked
			if ( (int) get_post_meta( $attachment_id, $this->is_watermarked_metakey, true ) === 1 )
				$this->remove_watermark( $data, $attachment_id, 'manual' );

			// create a backup if this is enabled
			if ( $this->options['backup']['backup_image'] )
				$this->do_backup( $data, $upload_dir, $attachment_id );

			// loop through active image sizes
			foreach ( $this->options['watermark_on'] as $image_size => $active_size ) {
				if ( $active_size === 1 ) {
					switch ( $image_size ) {
						case 'full':
							$filepath = $original_file;
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
					$this->do_watermark( $attachment_id, $filepath, $image_size, $upload_dir, $metadata );

					// save metadata
					$this->save_image_metadata( $metadata, $filepath );

					do_action( 'iw_after_apply_watermark', $attachment_id, $image_size );
				}
			}

			// update watermark status
			update_post_meta( $attachment_id, $this->is_watermarked_metakey, 1 );
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
		if ( $method !== 'manual' )
			return $data;

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
			update_post_meta( $attachment_id, $this->is_watermarked_metakey, 0 );

			// ureturn the attachment metadata
			return wp_get_attachment_metadata( $attachment_id );
		}

		return false;
	}

	/**
	 * Get image metadata.
	 *
	 * @param array $imageinfo
	 * @return array
	 */
	public function get_image_metadata( $imageinfo ) {
		$metadata = [
			'exif'	=> null,
			'iptc'	=> null
		];

		if ( is_array( $imageinfo ) ) {
			// prepare EXIF data bytes from source file
			$exifdata = key_exists( 'APP1', $imageinfo ) ? $imageinfo['APP1'] : null;

			if ( $exifdata ) {
				$exiflength = strlen( $exifdata ) + 2;

				// construct EXIF segment
				if ( $exiflength > 0xFFFF ) {
					return $metadata;
				} else
					$metadata['exif'] = chr( 0xFF ) . chr( 0xE1 ) . chr( ( $exiflength >> 8 ) & 0xFF ) . chr( $exiflength & 0xFF ) . $exifdata;
			}

			// prepare IPTC data bytes from source file
			$iptcdata = key_exists( 'APP13', $imageinfo ) ? $imageinfo['APP13'] : null;

			if ( $iptcdata ) {
				$iptclength = strlen( $iptcdata ) + 2;

				// construct IPTC segment
				if ( $iptclength > 0xFFFF ) {
					return $metadata;
				} else
					$metadata['iptc'] = chr( 0xFF ) . chr( 0xED ) . chr( ( $iptclength >> 8 ) & 0xFF ) . chr( $iptclength & 0xFF ) . $iptcdata;
			}
		}

		return $metadata;
	}

	/**
	 * Save EXIF and IPTC metadata from one image to another.
	 *
	 * @param array $metadata
	 * @param string $file
	 * @return bool|int
	 */
	public function save_image_metadata( $metadata, $file ) {
		$mime = wp_check_filetype( $file );

		if ( file_exists( $file ) && ($mime['type'] !== 'image/webp') && ($mime['type'] !== 'image/png') ) {
			$exifdata = $metadata['exif'];
			$iptcdata = $metadata['iptc'];

			$destfilecontent = @file_get_contents( $file );

			if ( ! $destfilecontent )
				return false;

			if ( strlen( $destfilecontent ) > 0 ) {
				$destfilecontent = substr( $destfilecontent, 2 );

				// variable accumulates new & original IPTC application segments
				$portiontoadd = chr( 0xFF ) . chr( 0xD8 );

				$exifadded = ! $exifdata;
				$iptcadded = ! $iptcdata;

				while ( ( $this->get_safe_chunk( substr( $destfilecontent, 0, 2 ) ) & 0xFFF0 ) === 0xFFE0 ) {
					$segmentlen = ( $this->get_safe_chunk( substr( $destfilecontent, 2, 2 ) ) & 0xFFFF );

					// last 4 bits of second byte is IPTC segment
					$iptcsegmentnumber = ( $this->get_safe_chunk( substr( $destfilecontent, 1, 1 ) ) & 0x0F );

					if ( $segmentlen <= 2 )
						return false;

					$thisexistingsegment = substr( $destfilecontent, 0, $segmentlen + 2 );

					if ( ( $iptcsegmentnumber >= 1 ) && ( ! $exifadded ) ) {
						$portiontoadd .= $exifdata;
						$exifadded = true;

						if ( $iptcsegmentnumber === 1 )
							$thisexistingsegment = '';
					}

					if ( ( $iptcsegmentnumber >= 13 ) && ( ! $iptcadded ) ) {
						$portiontoadd .= $iptcdata;
						$iptcadded = true;

						if ( $iptcsegmentnumber === 13 )
							$thisexistingsegment = '';
					}

					$portiontoadd .= $thisexistingsegment;
					$destfilecontent = substr( $destfilecontent, $segmentlen + 2 );
				}

				// add EXIF data if not added already
				if ( ! $exifadded )
					$portiontoadd .= $exifdata;

				// add IPTC data if not added already
				if ( ! $iptcadded )
					$portiontoadd .= $iptcdata;

				$outputfile = fopen( $file, 'w' );

				if ( $outputfile )
					return fwrite( $outputfile, $portiontoadd . $destfilecontent );
				else
					return false;
			} else
				return false;
		} else
			return false;
	}

	/**
	 * Get integer value of binary chunk.
	 *
	 * @param bin $value Binary data
	 * @return int
	 */
	private function get_safe_chunk( $value ) {
		// check for numeric value
		if ( is_numeric( $value ) ) {
			// cast to integer to do bitwise AND operation
			return (int) $value;
		} else
			return 0;
	}

	/**
	 * Apply watermark to image.
	 *
	 * @param int $attachment_id Attachment ID
	 * @param string $image_path Path to the file
	 * @param string $image_size Image size
	 * @param array	$upload_dir Upload media data
	 * @param array	$metadata EXIF and ITPC metadata
	 * @return void
	 */
	public function do_watermark( $attachment_id, $image_path, $image_size, $upload_dir, $metadata = [] ) {
		$options = apply_filters( 'iw_watermark_options', $this->options );

		// get image mime type
		$mime = wp_check_filetype( $image_path );

		if ( ! wp_attachment_is_image( $options['watermark_image']['url'] ) )
			return;

		// get watermark path
		$watermark_file = wp_get_attachment_metadata( $options['watermark_image']['url'], true );
		$watermark_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $watermark_file['file'];

		// imagick extension
		if ( $this->extension === 'imagick' ) {
			// create image resource
			$image = new Imagick( $image_path );

			// create watermark resource
			$watermark = new Imagick( $watermark_path );

			// alpha channel exists?
			if ( $watermark->getImageAlphaChannel() > 0 )
				$watermark->evaluateImage( Imagick::EVALUATE_MULTIPLY, round( (float) ( $options['watermark_image']['transparent'] / 100 ), 2 ), Imagick::CHANNEL_ALPHA );
			// no alpha channel
			else
				$watermark->setImageOpacity( round( (float) ( $options['watermark_image']['transparent'] / 100 ), 2 ) );

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
			$image->compositeImage( $watermark, Imagick::COMPOSITE_DEFAULT, $dest_x, $dest_y, Imagick::CHANNEL_ALL );

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

			if ( $image !== false ) {
				// create backup directory if needed
				wp_mkdir_p( $this->get_image_backup_folder_location( $data['file'] ) );

				// get path to the backup file
				$path = pathinfo( $backup_filepath );

				// create subfolders in backup folder if needed
				wp_mkdir_p( $path['dirname'] );

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

				if ( is_resource( $image ) )
					imagefilledrectangle( $image, 0, 0, imagesx( $image ), imagesy( $image ), imagecolorallocatealpha( $image, 255, 255, 255, 127 ) );
				break;

			case 'image/webp':
				$image = imagecreatefromwebp( $filepath );
				if ( is_resource( $image ) )
					imagefilledrectangle( $image, 0, 0, imagesx( $image ), imagesy( $image ), imagecolorallocatealpha( $image, 255, 255, 255, 127 ) );
				break;
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
	 * @return string
	 */
	private function get_image_backup_folder_location( $filepath ) {
		$path = explode( DIRECTORY_SEPARATOR, $filepath );

		array_pop( $path );

		$path = implode( DIRECTORY_SEPARATOR, $path );

		return IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Get image resource from the backup folder (if available).
	 *
	 * @param string $filepath
	 * @return string
	 */
	private function get_image_backup_filepath( $filepath ) {
		return IMAGE_WATERMARK_BACKUP_DIR . DIRECTORY_SEPARATOR . $filepath;
	}

	/**
	 * Delete the image backup if one exists.
	 *
	 * @param int $attachment_id
	 * @return void
	 */
	public function delete_attachment( $attachment_id ) {
		// see get_attached_file() in wp-includes/post.php
		$filepath = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$backup_filepath = $this->get_image_backup_filepath( $filepath );

		if ( file_exists( $backup_filepath ) )
			unlink( $backup_filepath );
	}

	/**
	 * Create admin notice when we can't create the backup folder.
	 *
	 * @return void
	 */
	function folder_writable_admin_notice() {
		if ( current_user_can( 'manage_options' ) && $this->is_backup_folder_writable !== true ) {
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
	 * @param int $image_width Image width
	 * @param int $image_height Image height
	 * @param int $watermark_width Watermark width
	 * @param int $watermark_height	Watermark height
	 * @param array $options
	 * @return array
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

		return [ $width, $height ];
	}

	/**
	 * Calculate image coordinates for watermark.
	 *
	 * @param int $image_width Image width
	 * @param int $image_height	Image height
	 * @param int $watermark_width Watermark width
	 * @param int $watermark_height	Watermark height
	 * @param array $options Options
	 * @return array
	 */
	private function calculate_image_coordinates( $image_width, $image_height, $watermark_width, $watermark_height, $options ) {
		switch ( $options['watermark_image']['position'] ) {
			case 'top_left':
				$dest_x = $dest_y = 0;
				break;

			case 'top_center':
				$dest_x = (int) round( ( $image_width / 2 ) - ( $watermark_width / 2 ), 0 );
				$dest_y = 0;
				break;

			case 'top_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = 0;
				break;

			case 'middle_left':
				$dest_x = 0;
				$dest_y = (int) round( ( $image_height / 2 ) - ( $watermark_height / 2 ), 0 );
				break;

			case 'middle_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = (int) round( ( $image_height / 2 ) - ( $watermark_height / 2 ), 0 );
				break;

			case 'bottom_left':
				$dest_x = 0;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom_center':
				$dest_x = (int) round( ( $image_width / 2 ) - ( $watermark_width / 2 ), 0 );
				$dest_y = $image_height - $watermark_height;
				break;

			case 'bottom_right':
				$dest_x = $image_width - $watermark_width;
				$dest_y = $image_height - $watermark_height;
				break;

			case 'middle_center':
			default:
				$dest_x = (int) round( ( $image_width / 2 ) - ( $watermark_width / 2 ), 0 );
				$dest_y = (int) round( ( $image_height / 2 ) - ( $watermark_height / 2 ), 0 );
		}

		if ( $options['watermark_image']['offset_unit'] === 'pixels' ) {
			$dest_x += $options['watermark_image']['offset_width'];
			$dest_y += $options['watermark_image']['offset_height'];
		} else {
			$dest_x += (int) round( $image_width * $options['watermark_image']['offset_width'] / 100, 0 );
			$dest_y += (int) round( $image_height * $options['watermark_image']['offset_height'] / 100, 0 );
		}

		return [ (int) $dest_x, (int) $dest_y ];
	}

	/**
	 * Add watermark image to an image.
	 *
	 * @param resource $image Image resource
	 * @param array $options Plugin options
	 * @param array $upload_dir WP upload dir data
	 * @return bool|resource
	 */
	private function add_watermark_image( $image, $options, $upload_dir ) {
		if ( ! wp_attachment_is_image( $options['watermark_image']['url'] ) )
			return false;

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
			case 'image/webp':
				$watermark = imagecreatefromwebp( $url );
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
	 * @return void
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
	 * @return resource
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
	 * @param string $mime_type Image mime type
	 * @param string $filepath Path where image should be saved
	 * @param int $quality Image quality
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
			case 'image/webp':
				imagewebp( $image, $filepath, $quality );
				break;
	
		}
	}

	/**
	 * Add link to Settings page.
	 *
	 * @param array $links
	 * @return array
	 */
	public function plugin_settings_link( $links ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) )
			return $links;

		array_unshift( $links, sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php' ) . '?page=watermark-options', __( 'Settings', 'image-watermark' ) ) );

		return $links;
	}

	/**
	 * Add link to Support Forum.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_extend_links( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) )
			return $links;

		if ( $file === IMAGE_WATERMARK_BASENAME )
			return array_merge( $links, [ sprintf( '<a href="http://www.dfactory.co/support/forum/image-watermark/" target="_blank">%s</a>', __( 'Support', 'image-watermark' ) ) ] );

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

$image_watermark = Image_Watermark();