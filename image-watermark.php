<?php
/*
Plugin Name: Image Watermark
Description: Image Watermark allows you to automatically watermark images uploaded to the WordPress Media Library and bulk watermark previously uploaded images.
Version: 1.9.1
Author: dFactory
Author URI: http://www.dfactory.co/
Plugin URI: http://www.dfactory.co/products/image-watermark/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Text Domain: image-watermark
Domain Path: /languages

Image Watermark
Copyright (C) 2013-2025, Digital Factory - info@digitalfactory.pl

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
 * @version	1.9.1
 */
final class Image_Watermark {

	private static $instance;
	private $extension = false;
	private $upload_handler;
	private $watermark_controller;
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
		'version'	 => '1.9.1'
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
		include_once( IMAGE_WATERMARK_PATH . 'includes/class-upload-handler.php' );
		include_once( IMAGE_WATERMARK_PATH . 'includes/class-actions-controller.php' );

		$this->upload_handler = new Image_Watermark_Upload_Handler( $this );
		$this->watermark_controller = new Image_Watermark_Actions_Controller( $this, $this->upload_handler );

		// actions
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_enqueue_media', [ $this, 'wp_enqueue_media' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );
		add_action( 'load-upload.php', [ $this, 'watermark_bulk_action' ] );
		add_action( 'admin_init', [ $this, 'update_plugin' ] );
		add_action( 'admin_init', [ $this, 'check_extensions' ] );
		add_action( 'admin_notices', [ $this, 'bulk_admin_notices' ] );
		add_action( 'delete_attachment', [ $this->upload_handler, 'delete_attachment' ] );
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
				'title'			=> __( 'Select watermark image', 'image-watermark' ),
				'originalSize'	=> __( 'Original size', 'image-watermark' ),
				'noSelectedImg'	=> __( 'No watermark image has been selected yet.', 'image-watermark' ),
				'notAllowedImg'	=> __( 'This image cannot be used as a watermark. Use a JPEG, PNG, WebP, or GIF image.', 'image-watermark' ),
				'px'			=> __( 'px', 'image-watermark' ),
				'frame'			=> 'select',
				'button'		=> [ 'text' => __( 'Add watermark image', 'image-watermark' ) ],
				'multiple'		=> false
			];

			wp_add_inline_script( 'image-watermark-upload-manager', 'var iwArgsUpload = ' . wp_json_encode( $script_data ) . ";\n", 'before' );

			wp_enqueue_script( 'image-watermark-admin-settings', IMAGE_WATERMARK_URL . '/js/admin-settings.js', [ 'jquery', 'jquery-ui-core', 'jquery-ui-button', 'jquery-ui-slider' ], $this->defaults['version'] );

			// prepare script data
			$script_data = [
				'resetToDefaults' => __( 'Are you sure you want to reset all settings to their default values?', 'image-watermark' )
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
				'allowed_mimes'		=> $this->get_allowed_mime_types(),
				'__applied_none'	=> __( 'The watermark could not be applied to the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'image-watermark' ),
				'__applied_one'		=> __( 'Watermark was successfully applied to 1 image.', 'image-watermark' ),
				'__applied_multi'	=> __( 'Watermark was successfully applied to %s images.', 'image-watermark' ),
				'__removed_none'	=> __( 'The watermark could not be removed from the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'image-watermark' ),
				'__removed_one'		=> __( 'Watermark was successfully removed from 1 image.', 'image-watermark' ),
				'__removed_multi'	=> __( 'Watermark was successfully removed from %s images.', 'image-watermark' ),
				'__skipped'			=> __( 'Skipped images', 'image-watermark' ),
				'__running'			=> __( 'A bulk action is currently running. Please wait…', 'image-watermark' ),
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
		return $this->get_upload_handler()->handle_upload_files( $file );
	}

	/**
	 * Handle manual watermark AJAX requests.
	 *
	 * @return void
	 */
	public function watermark_action_ajax() {
		$this->get_watermark_controller()->watermark_action_ajax();
	}

	/**
	 * Handle media library bulk watermark actions.
	 *
	 * @return void
	 */
	public function watermark_bulk_action() {
		$this->get_watermark_controller()->watermark_bulk_action();
	}

	/**
	 * Add watermark buttons on attachment image locations.
	 *
	 * @param array $form_fields
	 * @param object $post
	 * @return array
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

			if ( isset( $_REQUEST['watermarked'], $_REQUEST['watermarkremoved'], $_REQUEST['skipped'] ) && $post_type === 'attachment' ) {
				$watermarked = (int) $_REQUEST['watermarked'];
				$watermarkremoved = (int) $_REQUEST['watermarkremoved'];
				$skipped = (int) $_REQUEST['skipped'];
				$messages = [];

				if ( isset( $_REQUEST['messages'] ) ) {
					$raw_messages = wp_unslash( $_REQUEST['messages'] );
					$raw_messages = is_array( $raw_messages ) ? $raw_messages : [ $raw_messages ];
					$messages = array_filter( array_map( 'sanitize_text_field', $raw_messages ) );
				}

				if ( $watermarked === 0 )
					echo '<div class="error"><p>' . __( 'The watermark could not be applied to the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'image-watermark' ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				elseif ( $watermarked > 0 )
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was successfully applied to 1 image.', 'Watermark was successfully applied to %s images.', $watermarked, 'image-watermark' ), number_format_i18n( $watermarked ) ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';

				if ( $watermarkremoved === 0 )
					echo '<div class="error"><p>' . __( 'The watermark could not be removed from the selected files because no valid images (JPEG, PNG, WebP) were selected.', 'image-watermark' ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';
				elseif ( $watermarkremoved > 0 )
					echo '<div class="updated"><p>' . sprintf( _n( 'Watermark was successfully removed from 1 image.', 'Watermark was successfully removed from %s images.', $watermarkremoved, 'image-watermark' ), number_format_i18n( $watermarkremoved ) ) . ($skipped > 0 ? ' ' . __( 'Skipped images', 'image-watermark' ) . ': ' . $skipped . '.' : '') . '</p></div>';

				if ( ! empty( $messages ) ) {
					echo '<div class="error"><p>' . implode( '<br />', array_map( 'esc_html', $messages ) ) . '</p></div>';
				}

				$_SERVER['REQUEST_URI'] = esc_url( remove_query_arg( [ 'watermarked', 'watermarkremoved', 'skipped', 'messages' ], $_SERVER['REQUEST_URI'] ) );
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
	 * Get active image extension.
	 *
	 * @return string|false
	 */
	public function get_extension() {
		return $this->extension;
	}

	/**
	 * Get allowed mime types.
	 *
	 * @return array
	 */
	public function get_allowed_mime_types() {
		return $this->allowed_mime_types;
	}

	/**
	 * Get meta key for watermark flag.
	 *
	 * @return string
	 */
	public function get_watermarked_meta_key() {
		return $this->is_watermarked_metakey;
	}

	/**
	 * Get upload handler.
	 *
	 * @return Image_Watermark_Upload_Handler
	 */
	public function get_upload_handler() {
		return $this->upload_handler;
	}

	/**
	 * Get watermark controller.
	 *
	 * @return Image_Watermark_Actions_Controller
	 */
	public function get_watermark_controller() {
		return $this->watermark_controller;
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
		return $this->get_upload_handler()->apply_watermark( $data, $attachment_id, $method );
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
				<p><?php _e( 'Image Watermark', 'image-watermark' ); ?> - <?php _e( 'Image backup', 'image-watermark' ); ?>: <?php _e( "Your uploads folder is not writable, so we can't create backups of your images. This feature has been disabled for now.", 'image-watermark' ); ?></p>
			</div>
			<?php
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
