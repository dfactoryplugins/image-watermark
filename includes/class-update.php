<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

new Image_Watermark_Update( );

/**
 * Image Watermark update class.
 *
 * @class Image_Watermark_Update
 */
class Image_Watermark_Update {

	/**
	 * Class constructor.
	 */
	public function __construct( )	{
		// actions
		add_action( 'admin_init', array( $this, 'check_update' ) );
	}

	/**
	 * Check if update is required.
	 * 
	 * @return void
	 */
	public function check_update() {
		if( ! current_user_can( 'manage_options' ) || ! current_user_can( 'install_plugins' ) )
			return;

		// gets current database version
		$current_db_version = get_option( 'image_watermark_version', '1.0.0' );

		// new version?
		if ( version_compare( $current_db_version, Image_Watermark()->defaults['version'], '<' ) ) {
			// update plugin version
			update_option( 'image_watermark_version', Image_Watermark()->defaults['version'], false );
		}
	}
}