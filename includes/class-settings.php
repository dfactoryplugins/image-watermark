<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

new Image_Watermark_Settings( );

/**
 * Image Watermark settings class.
 *
 * @class Image_Watermark_Settings
 */
class Image_Watermark_Settings {
	private $image_sizes;
	private $watermark_positions = [
		'x'	 => [ 'left', 'center', 'right' ],
		'y'	 => [ 'top', 'middle', 'bottom' ]
	];

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct( )	{
		// actions
		add_action( 'admin_init', [ $this, 'register_settings' ], 11 );
		add_action( 'admin_menu', [ $this, 'options_page' ] );
		add_action( 'wp_loaded', [ $this, 'load_image_sizes' ] );
	}

	/**
	 * Load available image sizes.
	 *
	 * @return void
	 */
	public function load_image_sizes() {
		$this->image_sizes = get_intermediate_image_sizes();
		$this->image_sizes[] = 'full';

		sort( $this->image_sizes, SORT_STRING );
	}

	/**
	 * Get post types.
	 *
	 * @return array
	 */
	private function get_post_types() {
		return array_merge( [ 'post', 'page' ], get_post_types( [ '_builtin' => false ], 'names' ) );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'image_watermark_options', 'image_watermark_options', [ $this, 'validate_options' ] );

		// general
		add_settings_section( 'image_watermark_general', __( 'General settings', 'image-watermark' ), '', 'image_watermark_options' );

		// is imagick available?
		if ( isset( Image_Watermark()->extensions['imagick'] ) )
			add_settings_field( 'iw_extension', __( 'PHP library', 'image-watermark' ), [ $this, 'iw_extension' ], 'image_watermark_options', 'image_watermark_general' );

		add_settings_field( 'iw_automatic_watermarking', __( 'Automatic watermarking', 'image-watermark' ), [ $this, 'iw_automatic_watermarking' ], 'image_watermark_options', 'image_watermark_general' );
		add_settings_field( 'iw_manual_watermarking', __( 'Manual watermarking', 'image-watermark' ), [ $this, 'iw_manual_watermarking' ], 'image_watermark_options', 'image_watermark_general' );
		add_settings_field( 'iw_enable_for', __( 'Enable watermark for', 'image-watermark' ), [ $this, 'iw_enable_for' ], 'image_watermark_options', 'image_watermark_general' );
		add_settings_field( 'iw_frontend_watermarking', __( 'Frontend watermarking', 'image-watermark' ), [ $this, 'iw_frontend_watermarking' ], 'image_watermark_options', 'image_watermark_general' );
		add_settings_field( 'iw_deactivation', __( 'Deactivation', 'image-watermark' ), [ $this, 'iw_deactivation' ], 'image_watermark_options', 'image_watermark_general' );

		// watermark position
		add_settings_section( 'image_watermark_position', __( 'Watermark position', 'image-watermark' ), '', 'image_watermark_options' );
		add_settings_field( 'iw_alignment', __( 'Watermark alignment', 'image-watermark' ), [ $this, 'iw_alignment' ], 'image_watermark_options', 'image_watermark_position' );
		add_settings_field( 'iw_offset', __( 'Watermark offset', 'image-watermark' ), [ $this, 'iw_offset' ], 'image_watermark_options', 'image_watermark_position' );
		add_settings_field( 'iw_offset_unit', __( 'Offset unit', 'image-watermark' ), [ $this, 'iw_offset_unit' ], 'image_watermark_options', 'image_watermark_position' );

		// watermark image
		add_settings_section( 'image_watermark_image', __( 'Watermark image', 'image-watermark' ), '', 'image_watermark_options' );
		add_settings_field( 'iw_watermark_image', __( 'Watermark image', 'image-watermark' ), [ $this, 'iw_watermark_image' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_watermark_preview', __( 'Watermark preview', 'image-watermark' ), [ $this, 'iw_watermark_preview' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_watermark_size', __( 'Watermark size', 'image-watermark' ), [ $this, 'iw_watermark_size' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_watermark_size_custom', __( 'Watermark custom size', 'image-watermark' ), [ $this, 'iw_watermark_size_custom' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_watermark_size_scaled', __( 'Watermark scale', 'image-watermark' ), [ $this, 'iw_watermark_size_scaled' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_watermark_opacity', __( 'Watermark transparency / opacity', 'image-watermark' ), [ $this, 'iw_watermark_opacity' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_image_quality', __( 'Image quality', 'image-watermark' ), [ $this, 'iw_image_quality' ], 'image_watermark_options', 'image_watermark_image' );
		add_settings_field( 'iw_image_format', __( 'Image format', 'image-watermark' ), [ $this, 'iw_image_format' ], 'image_watermark_options', 'image_watermark_image' );

		// watermark protection
		add_settings_section( 'image_watermark_protection', __( 'Image protection', 'image-watermark' ), '', 'image_watermark_options' );
		add_settings_field( 'iw_protection_right_click', __( 'Right click', 'image-watermark' ), [ $this, 'iw_protection_right_click' ], 'image_watermark_options', 'image_watermark_protection' );
		add_settings_field( 'iw_protection_drag_drop', __( 'Drag and drop', 'image-watermark' ), [ $this, 'iw_protection_drag_drop' ], 'image_watermark_options', 'image_watermark_protection' );
		add_settings_field( 'iw_protection_logged', __( 'Logged-in users', 'image-watermark' ), [ $this, 'iw_protection_logged' ], 'image_watermark_options', 'image_watermark_protection' );

		// Backup
		add_settings_section( 'image_watermark_backup', __( 'Image backup', 'image-watermark' ), '', 'image_watermark_options' );
		add_settings_field( 'iw_backup_image', __( 'Backup full size image', 'image-watermark' ), [ $this, 'iw_backup_image' ], 'image_watermark_options', 'image_watermark_backup' );
		add_settings_field( 'iw_backup_image_quality', __( 'Backup image quality', 'image-watermark' ), [ $this, 'iw_backup_image_quality' ], 'image_watermark_options', 'image_watermark_backup' );
	}

	/**
	 * Create options page in menu.
	 *
	 * @return void
	 */
	public function options_page() {
		add_options_page( __( 'Image Watermark Options', 'image-watermark' ), __( 'Watermark', 'image-watermark' ), 'manage_options', 'watermark-options', [ $this, 'options_page_output' ] );
	}

	/**
	 * Options page output.
	 *
	 * @return void
	 */
	public function options_page_output() {
		if ( ! current_user_can( 'manage_options' ) )
			return;

		echo '
		<div class="wrap">
			<h2>' . __( 'Image Watermark', 'image-watermark' ) . '</h2>
			<div class="image-watermark-settings">
				<div class="df-credits">
					<h3 class="hndle">' . esc_html__( 'Image Watermark', 'image-watermark' ) . ' ' . esc_html( Image_Watermark()->defaults['version'] ) . '</h3>
					<div class="inside">
						<h4 class="inner">' . esc_html__( 'Need support?', 'image-watermark' ) . '</h4>
						<p class="inner">' . sprintf( esc_html__( 'If you are having problems with this plugin, please browse it\'s %s or talk about them in the %s.', 'image-watermark' ), '<a href="http://www.dfactory.co/docs/image-watermark/?utm_source=image-watermark-settings&utm_medium=link&utm_campaign=docs" target="_blank">' . esc_html__( 'Documentation', 'image-watermark' ) . '</a>', '<a href="http://www.dfactory.co/support/?utm_source=image-watermark-settings&utm_medium=link&utm_campaign=support" target="_blank">' . esc_html__( 'Support forum', 'image-watermark' ) . '</a>' ) . '</p
						<hr />
						<h4 class="inner">' . esc_html__( 'Do you like this plugin?', 'image-watermark' ) . '</h4>
						<p class="inner">' . sprintf( esc_html__( '%s on WordPress.org', 'image-watermark' ), '<a href="https://wordpress.org/support/plugin/image-watermark/reviews/?filter=5" target="_blank">' . esc_html__( 'Rate it 5', 'image-watermark' ) . '</a>' ) . '<br />' .
						sprintf( esc_html__( 'Blog about it & link to the %s.', 'image-watermark' ), '<a href="http://www.dfactory.co/products/image-watermark/?utm_source=image-watermark-settings&utm_medium=link&utm_campaign=blog-about" target="_blank">' . esc_html__( 'plugin page', 'image-watermark' ) . '</a>' ) . '<br />' .
						sprintf( esc_html__( 'Check out our other %s.', 'image-watermark' ), '<a href="http://www.dfactory.co/products/?utm_source=image-watermark-settings&utm_medium=link&utm_campaign=other-plugins" target="_blank">' . esc_html__( 'WordPress plugins', 'image-watermark' ) . '</a>' ) . '
						</p>
						<hr />
						<p class="df-link inner"><a href="http://www.dfactory.co/?utm_source=image-watermark-settings&utm_medium=link&utm_campaign=created-by" target="_blank" title="Digital Factory"><img src="' . IMAGE_WATERMARK_URL . '/images/df-black-sm.png" alt="Digital Factory" /></a></p>
					</div>
				</div>
				<form action="options.php" method="post">';

		settings_fields( 'image_watermark_options' );
		$this->do_settings_sections( 'image_watermark_options' );

		echo '
					<p class="submit">';
		submit_button( '', 'primary', 'save_image_watermark_options', false );

		echo ' ';

		submit_button( __( 'Reset to defaults', 'image-watermark' ), 'secondary', 'reset_image_watermark_options', false );

		echo '
					</p>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
	}

	/**
	 * Validate options.
	 *
	 * @param array $input
	 * @return array
	 */
	public function validate_options( $input ) {
		if ( ! current_user_can( 'manage_options' ) )
			return $input;

		if ( isset( $_POST['save_image_watermark_options'] ) ) {
			$input['watermark_image']['plugin_off'] = isset( $_POST['iw_options']['watermark_image']['plugin_off'] ) ? ( (bool) $_POST['iw_options']['watermark_image']['plugin_off'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['watermark_image']['plugin_off'];
			$input['watermark_image']['manual_watermarking'] = isset( $_POST['iw_options']['watermark_image']['manual_watermarking'] ) ? ( (bool) $_POST['iw_options']['watermark_image']['manual_watermarking'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['watermark_image']['manual_watermarking'];

			$watermark_on = [];

			if ( isset( $_POST['iw_options']['watermark_on'] ) && is_array( $_POST['iw_options']['watermark_on'] ) ) {
				foreach ( $this->image_sizes as $size ) {
					if ( in_array( $size, array_keys( $_POST['iw_options']['watermark_on'] ) ) )
						$watermark_on[$size] = 1;
				}
			}

			$input['watermark_on'] = $watermark_on;

			$input['watermark_cpt_on'] = Image_Watermark()->defaults['options']['watermark_cpt_on'];

			if ( isset( $_POST['iw_options']['watermark_cpt_on'] ) && in_array( $_POST['iw_options']['watermark_cpt_on'], [ 'everywhere', 'specific' ] ) ) {
				if ( $_POST['iw_options']['watermark_cpt_on'] === 'specific' ) {
					if ( isset( $_POST['iw_options']['watermark_cpt_on_type'] ) ) {
						$tmp = [];

						foreach ( $this->get_post_types() as $cpt ) {
							if ( in_array( $cpt, array_keys( $_POST['iw_options']['watermark_cpt_on_type'] ) ) )
								$tmp[$cpt] = 1;
						}

						if ( count( $tmp ) > 0 )
							$input['watermark_cpt_on'] = $tmp;
					}
				}
			}

			// extension
			$input['watermark_image']['extension'] = isset( $_POST['iw_options']['watermark_image']['extension'], Image_Watermark()->extensions[$_POST['iw_options']['watermark_image']['extension']] ) ? $_POST['iw_options']['watermark_image']['extension'] : Image_Watermark()->defaults['options']['watermark_image']['extension'];

			$input['watermark_image']['frontend_active'] = isset( $_POST['iw_options']['watermark_image']['frontend_active'] ) ? ( (bool) $_POST['iw_options']['watermark_image']['frontend_active'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['watermark_image']['frontend_active'];
			$input['watermark_image']['deactivation_delete'] = isset( $_POST['iw_options']['watermark_image']['deactivation_delete'] ) ? ( (bool) $_POST['iw_options']['watermark_image']['deactivation_delete'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['watermark_image']['deactivation_delete'];


			$positions = [];

			foreach ( $this->watermark_positions['y'] as $position_y ) {
				foreach ( $this->watermark_positions['x'] as $position_x ) {
					$positions[] = $position_y . '_' . $position_x;
				}
			}
			$input['watermark_image']['position'] = isset( $_POST['iw_options']['watermark_image']['position'] ) && in_array( $_POST['iw_options']['watermark_image']['position'], $positions ) ? $_POST['iw_options']['watermark_image']['position'] : Image_Watermark()->defaults['options']['watermark_image']['position'];

			$input['watermark_image']['offset_unit'] = isset( $_POST['iw_options']['watermark_image']['offset_unit'] ) && in_array( $_POST['iw_options']['watermark_image']['offset_unit'], [ 'pixels', 'percentages' ], true ) ? $_POST['iw_options']['watermark_image']['offset_unit'] : Image_Watermark()->defaults['options']['watermark_image']['offset_unit'];
			$input['watermark_image']['offset_width'] = isset( $_POST['iw_options']['watermark_image']['offset_width'] ) ? (int) $_POST['iw_options']['watermark_image']['offset_width'] : Image_Watermark()->defaults['options']['watermark_image']['offset_width'];
			$input['watermark_image']['offset_height'] = isset( $_POST['iw_options']['watermark_image']['offset_height'] ) ? (int) $_POST['iw_options']['watermark_image']['offset_height'] : Image_Watermark()->defaults['options']['watermark_image']['offset_height'];
			$input['watermark_image']['url'] = isset( $_POST['iw_options']['watermark_image']['url'] ) ? (int) $_POST['iw_options']['watermark_image']['url'] : Image_Watermark()->defaults['options']['watermark_image']['url'];
			$input['watermark_image']['watermark_size_type'] = isset( $_POST['iw_options']['watermark_image']['watermark_size_type'] ) ? (int) $_POST['iw_options']['watermark_image']['watermark_size_type'] : Image_Watermark()->defaults['options']['watermark_image']['watermark_size_type'];
			$input['watermark_image']['absolute_width'] = isset( $_POST['iw_options']['watermark_image']['absolute_width'] ) ? (int) $_POST['iw_options']['watermark_image']['absolute_width'] : Image_Watermark()->defaults['options']['watermark_image']['absolute_width'];
			$input['watermark_image']['absolute_height'] = isset( $_POST['iw_options']['watermark_image']['absolute_height'] ) ? (int) $_POST['iw_options']['watermark_image']['absolute_height'] : Image_Watermark()->defaults['options']['watermark_image']['absolute_height'];
			$input['watermark_image']['width'] = isset( $_POST['iw_options']['watermark_image']['width'] ) ? (int) $_POST['iw_options']['watermark_image']['width'] : Image_Watermark()->defaults['options']['watermark_image']['width'];
			$input['watermark_image']['transparent'] = isset( $_POST['iw_options']['watermark_image']['transparent'] ) ? (int) $_POST['iw_options']['watermark_image']['transparent'] : Image_Watermark()->defaults['options']['watermark_image']['transparent'];
			$input['watermark_image']['quality'] = isset( $_POST['iw_options']['watermark_image']['quality'] ) ? (int) $_POST['iw_options']['watermark_image']['quality'] : Image_Watermark()->defaults['options']['watermark_image']['quality'];
			$input['watermark_image']['jpeg_format'] = isset( $_POST['iw_options']['watermark_image']['jpeg_format'] ) && in_array( $_POST['iw_options']['watermark_image']['jpeg_format'], [ 'baseline', 'progressive' ] ) ? $_POST['iw_options']['watermark_image']['jpeg_format'] : Image_Watermark()->defaults['options']['watermark_image']['jpeg_format'];

			$input['image_protection']['rightclick'] = isset( $_POST['iw_options']['image_protection']['rightclick'] ) ? ( (bool) $_POST['iw_options']['image_protection']['rightclick'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['image_protection']['rightclick'];
			$input['image_protection']['draganddrop'] = isset( $_POST['iw_options']['image_protection']['draganddrop'] ) ? ( (bool) $_POST['iw_options']['image_protection']['draganddrop'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['image_protection']['draganddrop'];
			$input['image_protection']['forlogged'] = isset( $_POST['iw_options']['image_protection']['forlogged'] ) ? ( (bool) $_POST['iw_options']['image_protection']['forlogged'] == 1 ? true : false ) : Image_Watermark()->defaults['options']['image_protection']['forlogged'];

			$input['backup']['backup_image'] = isset( $_POST['iw_options']['backup']['backup_image'] );
			$input['backup']['backup_quality'] = isset( $_POST['iw_options']['backup']['backup_quality'] ) ? (int) $_POST['iw_options']['backup']['backup_quality'] : Image_Watermark()->defaults['options']['backup']['backup_quality'];

			add_settings_error( 'iw_settings_errors', 'iw_settings_saved', __( 'Settings saved.', 'image-watermark' ), 'updated' );
		} elseif ( isset( $_POST['reset_image_watermark_options'] ) ) {

			$input = Image_Watermark()->defaults['options'];

			add_settings_error( 'iw_settings_errors', 'iw_settings_reset', __( 'Settings restored to defaults.', 'image-watermark' ), 'updated' );
		}

		if ( $input['watermark_image']['plugin_off'] != 0 || $input['watermark_image']['manual_watermarking'] != 0 ) {
			if ( empty( $input['watermark_image']['url'] ) )
				add_settings_error( 'iw_settings_errors', 'iw_image_not_set', __( 'Watermark will not be applied when watermark image is not set.', 'image-watermark' ), 'error' );

			if ( empty( $input['watermark_on'] ) )
				add_settings_error( 'iw_settings_errors', 'iw_sizes_not_set', __( 'Watermark will not be applied when no image sizes are selected.', 'image-watermark' ), 'error' );
		}

		return $input;
	}

	/**
	 * PHP extension.
	 *
	 * @return void
	 */
	public function iw_extension() {
		echo '
		<div id="iw_extension">
			<fieldset>
				<select name="iw_options[watermark_image][extension]">';

		foreach ( Image_Watermark()->extensions as $extension => $label ) {
			echo '
					<option value="' . esc_attr( $extension ) . '" ' . selected( $extension, Image_Watermark()->options['watermark_image']['extension'], false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '
				</select>
				<p class="description">' . esc_html__( 'Select extension.', 'image-watermark' ) . '</p>
			</fieldset>
		</div>';
	}

	/**
	 * Automatic watermarking option.
	 *
	 * @return void
	 */
	public function iw_automatic_watermarking() {
		?>
		<label for="iw_automatic_watermarking">
			<input id="iw_automatic_watermarking" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['watermark_image']['plugin_off'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[watermark_image][plugin_off]"><?php echo __( 'Enable watermark for uploaded images.', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Manual watermarking option.
	 *
	 * @return void
	 */
	public function iw_manual_watermarking() {
		?>
		<label for="iw_manual_watermarking">
			<input id="iw_manual_watermarking" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['watermark_image']['manual_watermarking'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[watermark_image][manual_watermarking]"><?php echo __( 'Enable Apply Watermark option for Media Library images.', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Enable watermark for option.
	 *
	 * @return void
	 */
	public function iw_enable_for() {
		?>
		<fieldset id="iw_enable_for">
			<div id="thumbnail-select">
				<?php
				foreach ( $this->image_sizes as $image_size ) {
					?>
					<input name="iw_options[watermark_on][<?php echo $image_size; ?>]" type="checkbox" id="image_size_<?php echo $image_size; ?>" value="1" <?php echo ( in_array( $image_size, array_keys( Image_Watermark()->options['watermark_on'] ) ) ? ' checked="checked"' : '' ); ?> /><label for="image_size_<?php echo $image_size; ?>"><?php echo $image_size; ?></label>
					<?php
				}
				?>
			</div>
			<p class="description">
				<?php echo __( 'Check the image sizes watermark will be applied to.', 'image-watermark' ); ?><br />
				<?php echo __( '<strong>IMPORTANT:</strong> checking full size is NOT recommended as it\'s the original image. You may need it later - for removing or changing watermark, image sizes regeneration or any other image manipulations. Use it only if you know what you are doing.', 'image-watermark' ); ?>
			</p>

			<?php
			$watermark_cpt_on = Image_Watermark()->options['watermark_cpt_on'];
			$post_types = array_keys( Image_Watermark()->options['watermark_cpt_on'] );

			if ( in_array( 'everywhere', $watermark_cpt_on ) && count( $watermark_cpt_on ) === 1 ) {
				$first_checked = true;
				$second_checked = false;
				$watermark_cpt_on = [];
			} else {
				$first_checked = false;
				$second_checked = true;
			}
			?>

			<div id="cpt-specific">
				<input id="df_option_everywhere" type="radio" name="iw_options[watermark_cpt_on]" value="everywhere" <?php echo ( $first_checked === true ? 'checked="checked"' : '' ); ?>/><label for="df_option_everywhere"><?php _e( 'everywhere', 'image-watermark' ); ?></label>
				<input id="df_option_cpt" type="radio" name="iw_options[watermark_cpt_on]" value="specific" <?php echo ( $second_checked === true ? 'checked="checked"' : '' ); ?> /><label for="df_option_cpt"><?php _e( 'on selected post types only', 'image-watermark' ); ?></label>
			</div>

			<div id="cpt-select" <?php echo ( $second_checked === false ? 'style="display: none;"' : '' ); ?>>
			<?php
			foreach ( $this->get_post_types() as $cpt ) {
				?>
				<input name="iw_options[watermark_cpt_on_type][<?php echo $cpt; ?>]" type="checkbox" id="post_type_<?php echo $cpt; ?>" value="1" <?php echo ( in_array( $cpt, $post_types ) ? ' checked="checked"' : '' ); ?> /><label for="post_type_<?php echo $cpt; ?>"><?php echo $cpt; ?></label>
				<?php
			}
				?>
			</div>

			<p class="description"><?php echo __( 'Check custom post types on which watermark should be applied to uploaded images.', 'image-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Frontend watermarking option.
	 *
	 * @return void
	 */
	public function iw_frontend_watermarking() {
		?>
		<label for="iw_frontend_watermarking">
			<input id="iw_frontend_watermarking" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['watermark_image']['frontend_active'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[watermark_image][frontend_active]"><?php echo __( 'Enable frontend image uploading. (uploading script is not included, but you may use a plugin or custom code).', 'image-watermark' ); ?>
		</label>
		<span class="description"><?php echo __( '<br /><strong>Notice:</strong> This functionality works only if uploaded images are processed using WordPress native upload methods.', 'image-watermark' ); ?></span>
		<?php
	}

	/**
	 * Remove data on deactivation option.
	 *
	 * @return void
	 */
	public function iw_deactivation() {
		?>
		<label for="iw_deactivation">
			<input id="iw_deactivation" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['watermark_image']['deactivation_delete'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[watermark_image][deactivation_delete]"><?php echo __( 'Delete all database settings on plugin deactivation.', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Watermark alignment option.
	 *
	 * @return void
	 */
	public function iw_alignment() {
		?>
		<fieldset id="iw_alignment">
			<table id="watermark_position" border="1">
			<?php
			$watermark_position = Image_Watermark()->options['watermark_image']['position'];

			foreach ( $this->watermark_positions['y'] as $y ) {
			?>
				<tr>
				<?php
				foreach ( $this->watermark_positions['x'] as $x ) {
				?>
					<td title="<?php echo ucfirst( $y . ' ' . $x ); ?>">
						<input name="iw_options[watermark_image][position]" type="radio" value="<?php echo $y . '_' . $x; ?>"<?php echo ( $watermark_position == $y . '_' . $x ? ' checked="checked"' : '' ); ?> />
					</td>
					<?php }
					?>
				</tr>
				<?php
			}
		?>
			</table>
			<p class="description"><?php echo __( 'Select the watermark alignment.', 'image-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark offset unit option.
	 *
	 * @return void
	 */
	public function iw_offset_unit() {
		?>
		<fieldset id="iw_offset_unit">
			<input type="radio" id="offset_pixels" value="pixels" name="iw_options[watermark_image][offset_unit]" <?php checked( Image_Watermark()->options['watermark_image']['offset_unit'], 'pixels', true ); ?> /><label for="offset_pixels"><?php _e( 'pixels', 'image-watermark' ); ?></label>
			<input type="radio" id="offset_percentages" value="percentages" name="iw_options[watermark_image][offset_unit]" <?php checked( Image_Watermark()->options['watermark_image']['offset_unit'], 'percentages', true ); ?> /><label for="offset_percentages"><?php _e( 'percentages', 'image-watermark' ); ?></label>
			<p class="description"><?php _e( 'Select the watermark offset unit.', 'image-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark offset option.
	 *
	 * @return void
	 */
	public function iw_offset() {
		?>
		<fieldset id="iw_offset">
			<?php echo __( 'x:', 'image-watermark' ); ?> <input type="number" class="small-text" name="iw_options[watermark_image][offset_width]" value="<?php echo Image_Watermark()->options['watermark_image']['offset_width']; ?>">
			<br />
			<?php echo __( 'y:', 'image-watermark' ); ?> <input type="number" class="small-text" name="iw_options[watermark_image][offset_height]" value="<?php echo Image_Watermark()->options['watermark_image']['offset_height']; ?>">
			<p class="description"><?php _e( 'Enter watermark offset value.', 'image-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark image option.
	 *
	 * @return void
	 */
	public function iw_watermark_image() {
		if ( Image_Watermark()->options['watermark_image']['url'] !== null && Image_Watermark()->options['watermark_image']['url'] != 0 ) {
			$image = wp_get_attachment_image_src( Image_Watermark()->options['watermark_image']['url'], [ 300, 300 ], false );
			$image_selected = true;
		} else {
			$image_selected = false;
		}
		?>
		<div class="iw_watermark_image">
			<input id="iw_upload_image" type="hidden" name="iw_options[watermark_image][url]" value="<?php echo (int) Image_Watermark()->options['watermark_image']['url']; ?>" />
			<input id="iw_upload_image_button" type="button" class="button button-secondary" value="<?php echo __( 'Select image', 'image-watermark' ); ?>" />
			<input id="iw_turn_off_image_button" type="button" class="button button-secondary" value="<?php echo __( 'Remove image', 'image-watermark' ); ?>" <?php if ( $image_selected === false ) echo 'disabled="disabled"'; ?>/>
			<p class="description"><?php _e( 'You have to save changes after the selection or removal of the image.', 'image-watermark' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Watermark image preview.
	 *
	 * @return void
	 */
	public function iw_watermark_preview() {
		if ( Image_Watermark()->options['watermark_image']['url'] !== null && Image_Watermark()->options['watermark_image']['url'] != 0 ) {
			$image = wp_get_attachment_image_src( Image_Watermark()->options['watermark_image']['url'], [ 300, 300 ], false );
			$image_selected = true;
		} else
			$image_selected = false;
		?>
		<fieldset id="iw_watermark_preview">
			<div id="previewImg_imageDiv">
			<?php
				if ( $image_selected ) {
					$image = wp_get_attachment_image_src( Image_Watermark()->options['watermark_image']['url'], [ 300, 300 ], false );
					?>
					<img id="previewImg_image" src="<?php echo $image[0]; ?>" alt="" width="300" />
				<?php } else { ?>
					<img id="previewImg_image" src="" alt="" width="300" style="display: none;" />
				<?php }
			?>
			</div>
			<p id="previewImageInfo" class="description">
			<?php
			if ( ! $image_selected ) {
				_e( 'Watermak has not been selected yet.', 'image-watermark' );
			} else {
				$image_full_size = wp_get_attachment_image_src( Image_Watermark()->options['watermark_image']['url'], 'full', false );

				echo __( 'Original size', 'image-watermark' ) . ': ' . $image_full_size[1] . ' ' . __( 'px', 'image-watermark' ) . ' / ' . $image_full_size[2] . ' ' . __( 'px', 'image-watermark' );
			}
		?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark size option.
	 *
	 * @return void
	 */
	public function iw_watermark_size() {
		?>
		<fieldset id="iw_watermark_size">
			<div id="watermark-type">
				<input type="radio" id="type1" value="0" name="iw_options[watermark_image][watermark_size_type]" <?php checked( Image_Watermark()->options['watermark_image']['watermark_size_type'], 0, true ); ?> /><label for="type1"><?php _e( 'original', 'image-watermark' ); ?></label>
				<input type="radio" id="type2" value="1" name="iw_options[watermark_image][watermark_size_type]" <?php checked( Image_Watermark()->options['watermark_image']['watermark_size_type'], 1, true ); ?> /><label for="type2"><?php _e( 'custom', 'image-watermark' ); ?></label>
				<input type="radio" id="type3" value="2" name="iw_options[watermark_image][watermark_size_type]" <?php checked( Image_Watermark()->options['watermark_image']['watermark_size_type'], 2, true ); ?> /><label for="type3"><?php _e( 'scaled', 'image-watermark' ); ?></label>
			</div>
			<p class="description"><?php _e( 'Select method of aplying watermark size.', 'image-watermark' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Watermark custom size option.
	 *
	 * @return void
	 */
	public function iw_watermark_size_custom() {
		?>
		<fieldset id="iw_watermark_size_custom">
			<?php _e( 'x:', 'image-watermark' ); ?> <input type="text" size="5"  name="iw_options[watermark_image][absolute_width]" value="<?php echo Image_Watermark()->options['watermark_image']['absolute_width']; ?>"> <?php _e( 'px', 'image-watermark' ); ?>
			<br />
			<?php _e( 'y:', 'image-watermark' ); ?> <input type="text" size="5"  name="iw_options[watermark_image][absolute_height]" value="<?php echo Image_Watermark()->options['watermark_image']['absolute_height']; ?>"> <?php _e( 'px', 'image-watermark' ); ?>
		</fieldset>
		<p class="description"><?php _e( 'Those dimensions will be used if "custom" method is selected above.', 'image-watermark' ); ?></p>
		<?php
	}

	/**
	 * Watermark scaled size option.
	 *
	 * @return void
	 */
	public function iw_watermark_size_scaled() {
		?>
		<fieldset id="iw_watermark_size_scaled">
			<div>
				<input type="text" id="iw_size_input" maxlength="3" class="hide-if-js" name="iw_options[watermark_image][width]" value="<?php echo Image_Watermark()->options['watermark_image']['width']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="iw_size_span" title="<?php echo Image_Watermark()->options['watermark_image']['width']; ?>"><span class="iw-current-value" style="left: <?php echo Image_Watermark()->options['watermark_image']['width']; ?>%;"><?php echo Image_Watermark()->options['watermark_image']['width']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.', 'image-watermark' ); ?></p>
		<?php
	}

	/**
	 * Watermark custom size option.
	 *
	 * @return void
	 */
	public function iw_watermark_opacity() {
		?>
		<fieldset id="iw_watermark_opacity">
			<div>
				<input type="text" id="iw_opacity_input" maxlength="3" class="hide-if-js" name="iw_options[watermark_image][transparent]" value="<?php echo Image_Watermark()->options['watermark_image']['transparent']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="iw_opacity_span" title="<?php echo Image_Watermark()->options['watermark_image']['transparent']; ?>"><span class="iw-current-value" style="left: <?php echo Image_Watermark()->options['watermark_image']['transparent']; ?>%;"><?php echo Image_Watermark()->options['watermark_image']['transparent']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Enter a number ranging from 0 to 100. 0 makes watermark image completely transparent, 100 shows it as is.', 'image-watermark' ); ?></p>
		<?php
	}

	/**
	 * Image quality option.
	 *
	 * @return void
	 */
	public function iw_image_quality() {
		?>
		<fieldset id="iw_image_quality">
			<div>
				<input type="text" id="iw_quality_input" maxlength="3" class="hide-if-js" name="iw_options[watermark_image][quality]" value="<?php echo Image_Watermark()->options['watermark_image']['quality']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="iw_quality_span" title="<?php echo Image_Watermark()->options['watermark_image']['quality']; ?>"><span class="iw-current-value" style="left: <?php echo Image_Watermark()->options['watermark_image']['quality']; ?>%;"><?php echo Image_Watermark()->options['watermark_image']['quality']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Set output image quality.', 'image-watermark' ); ?></p>
		<?php
	}

	/**
	 * Image format option.
	 *
	 * @return void
	 */
	public function iw_image_format() {
		?>
		<fieldset id="iw_image_format">
			<div id="jpeg-format">
				<input type="radio" id="baseline" value="baseline" name="iw_options[watermark_image][jpeg_format]" <?php checked( Image_Watermark()->options['watermark_image']['jpeg_format'], 'baseline', true ); ?> /><label for="baseline"><?php _e( 'baseline', 'image-watermark' ); ?></label>
				<input type="radio" id="progressive" value="progressive" name="iw_options[watermark_image][jpeg_format]" <?php checked( Image_Watermark()->options['watermark_image']['jpeg_format'], 'progressive', true ); ?> /><label for="progressive"><?php _e( 'progressive', 'image-watermark' ); ?></label>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Select baseline or progressive image format.', 'image-watermark' ); ?></p>
		<?php
	}

	/**
	 * Right click image protection option.
	 *
	 * @return void
	 */
	public function iw_protection_right_click() {
		?>
		<label for="iw_protection_right_click">
			<input id="iw_protection_right_click" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['image_protection']['rightclick'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[image_protection][rightclick]"><?php _e( 'Disable right mouse click on images', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Drag and drop image protection option.
	 *
	 * @return void
	 */
	public function iw_protection_drag_drop() {
		?>
		<label for="iw_protection_drag_drop">
			<input id="iw_protection_drag_drop" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['image_protection']['draganddrop'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[image_protection][draganddrop]"><?php _e( 'Prevent drag and drop', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Logged-in users image protection option.
	 *
	 * @return void
	 */
	public function iw_protection_logged() {
		?>
		<label for="iw_protection_logged">
			<input id="iw_protection_logged" type="checkbox" <?php checked( ( ! empty( Image_Watermark()->options['image_protection']['forlogged'] ) ? 1 : 0 ), 1, true ); ?> value="1" name="iw_options[image_protection][forlogged]"><?php _e( 'Enable image protection for logged-in users also', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Backup the original image.
	 *
	 * @return void
	 */
	public function iw_backup_image() {
		?>
		<label for="iw_backup_size_full">
			<input id="iw_backup_size_full" type="checkbox" <?php checked( ! empty( Image_Watermark()->options['backup']['backup_image'] ), true, true ); ?> value="1" name="iw_options[backup][backup_image]"><?php echo __( 'Backup the full size image.', 'image-watermark' ); ?>
		</label>
		<?php
	}

	/**
	 * Image backup quality option.
	 *
	 * @return void
	 */
	public function iw_backup_image_quality() {
		?>
		<fieldset id="iw_backup_image_quality">
			<div>
				<input type="text" id="iw_backup_quality_input" maxlength="3" class="hide-if-js" name="iw_options[backup][backup_quality]" value="<?php echo Image_Watermark()->options['backup']['backup_quality']; ?>" />
				<div class="wplike-slider">
					<span class="left hide-if-no-js">0</span><span class="middle" id="iw_backup_quality_span" title="<?php echo Image_Watermark()->options['backup']['backup_quality']; ?>"><span class="iw-current-value" style="left: <?php echo Image_Watermark()->options['backup']['backup_quality']; ?>%;"><?php echo Image_Watermark()->options['backup']['backup_quality']; ?></span></span><span class="right hide-if-no-js">100</span>
				</div>
			</div>
		</fieldset>
		<p class="description"><?php _e( 'Set output image quality.', 'image-watermark' ); ?></p>
		<?php
	}

	/**
	 * This function is similar to the function in the Settings API, only the output HTML is changed.
	 * Print out the settings fields for a particular settings section
	 *
	 * @global $wp_settings_fields Storage array of settings fields and their pages/sections
	 *
	 * @since 0.1
	 *
	 * @param string $page Slug title of the admin page who's settings fields you want to show.
	 * @return void
	 */
	function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections[$page] ) )
			return;

		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo '<div id="" class="'.$section['id'].'">';

			if ( $section['title'] )
				echo "<h3><span>{$section['title']}</span></h3>\n";

			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );

			if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[$page] ) || ! isset( $wp_settings_fields[$page][$section['id']] ) )
				continue;

			echo '<div class="inside"><table class="form-table">';

			do_settings_fields( $page, $section['id'] );

			echo '</table></div></div>';
		}
	}
}