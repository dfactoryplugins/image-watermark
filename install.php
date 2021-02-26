<?php
/**
 * Class IW_Deprecation_Upgrade
 *
 * Based on https://github.com/afragen/wp-dependency-installer.
 */
class IW_Deprecation_Upgrade {

	private $current_slug;
	private static $caller;
	public $download_link = '';
	public $slug = '';
	public $status = '';
	public $transient = '';

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->slug = 'download-attachments/download-attachments.php';
		$this->short_slug = 'download-attachments';
		$this->status = 'none';
		$this->transient = 'iw-link-' . md5( 'download-attachments' );
	}

	/**
	 * Factory.
	 *
	 * @param string $caller File path to calling plugin/theme.
	 */
	public static function instance( $caller = false ) {
		static $instance = null;

		if ( null === $instance )
			$instance = new self();

		self::$caller = $caller;

		return $instance;
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'http_request_args', array( $this, 'add_basic_auth_headers' ), 15 );
	}

	/**
	 * Determine if dependency is active or installed.
	 */
	public function admin_init() {
		// do not install plugin translations
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

		$this->download_link = $this->get_dot_org_latest_download( $this->short_slug );

		if ( is_plugin_active( $this->slug ) ) {
			$this->status = 'already_active';
		} else {
			if ( $this->is_installed( $this->slug ) )
				$this->status = $this->activate( $this->slug );
			else
				$this->status = $this->install( $this->slug );
		}
	}

	/**
	 * Get lastest download link from WordPress API.
	 *
	 * @param  string $slug Plugin slug.
	 * @return string $download_link
	 */
	private function get_dot_org_latest_download( $slug ) {
		$download_link = get_site_transient( $this->transient );

		if ( ! $download_link ) {
			$url = add_query_arg(
				array(
					'action'			=> 'plugin_information',
					'request%5Bslug%5D'	=> $slug,
				),
				'https://api.wordpress.org/plugins/info/1.1/'
			);

			$response = wp_remote_get( $url );
			$response = json_decode( wp_remote_retrieve_body( $response ) );
			$download_link = empty( $response ) ? 'https://downloads.wordpress.org/plugin/' . $slug . '.zip' : $response->download_link;

			set_site_transient( $this->transient, $download_link, DAY_IN_SECONDS );
		}

		return $download_link;
	}

	/**
	 * Is dependency installed?
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return boolean
	 */
	public function is_installed( $slug ) {
		$plugins = get_plugins();

		return isset( $plugins[ $slug ] );
	}

	/**
	 * Install and activate dependency.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return bool|array false or Message.
	 */
	public function install( $slug ) {
		if ( ! current_user_can( 'update_plugins' ) )
			return 'no_capability';

		$this->current_slug = $this->slug;

		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 2 );

		$skin = new IW_Plugin_Installer_Skin(
			array(
				'type'	=> 'plugin',
				'nonce'	=> wp_nonce_url( $this->download_link )
			)
		);

		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->install( $this->download_link );

		if ( is_wp_error( $result ) || empty( $result ) )
			return 'instalation_failed';

		wp_cache_flush();

		$activation_status = $this->activate( $slug );

		if ( $activation_status === 'activation_error' )
			return 'installation_activation_failed';
		else
			return 'installation_activation_success';
	}

	/**
	 * Activate dependency.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return array Message.
	 */
	public function activate( $slug ) {
		// network activate only if on network admin pages.
		$result = is_network_admin() ? activate_plugin( $slug, null, true ) : activate_plugin( $slug );

		if ( is_wp_error( $result ) )
			return 'activation_error';

		return 'activation_success';
	}

	/**
	 * Correctly rename dependency for activation.
	 *
	 * @param string $source Path fo $source.
	 * @param string $remote_source Path of $remote_source.
	 *
	 * @return string $new_source
	 */
	public function upgrader_source_selection( $source, $remote_source ) {
		$new_source = trailingslashit( $remote_source ) . dirname( $this->current_slug );

		$this->move( $source, $new_source );

		return trailingslashit( $new_source );
	}

	/**
	 * Rename or recursive file copy and delete.
	 *
	 * This is more versatile than `$wp_filesystem->move()`.
	 * It moves/renames directories as well as files.
	 * Fix for https://github.com/afragen/github-updater/issues/826,
	 * strange failure of `rename()`.
	 *
	 * @param string $source File path of source.
	 * @param string $destination File path of destination.
	 *
	 * @return bool|void
	 */
	private function move( $source, $destination ) {
		if ( $this->filesystem_move( $source, $destination ) )
			return true;

		if ( is_dir( $destination ) && rename( $source, $destination ) )
			return true;

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
		if ( $dir = opendir( $source ) ) {
			if ( ! file_exists( $destination ) )
				mkdir( $destination );

			$source = untrailingslashit( $source );

			while ( false !== ( $file = readdir( $dir ) ) ) {
				if ( ( '.' !== $file ) && ( '..' !== $file ) && "{$source}/{$file}" !== $destination ) {
					if ( is_dir( "{$source}/{$file}" ) )
						$this->move( "{$source}/{$file}", "{$destination}/{$file}" );
					else {
						copy( "{$source}/{$file}", "{$destination}/{$file}" );
						unlink( "{$source}/{$file}" );
					}
				}
			}

			$iterator = new \FilesystemIterator( $source );

			if ( ! $iterator->valid() ) // True if directory is empty.
				rmdir( $source );

			closedir( $dir );

			return true;
		}

		return false;
	}

	/**
	 * Non-direct filesystem move.
	 *
	 * @uses $wp_filesystem->move() when FS_METHOD is not 'direct'
	 *
	 * @param string $source      File path of source.
	 * @param string $destination File path of destination.
	 *
	 * @return bool|void True on success, false on failure.
	 */
	public function filesystem_move( $source, $destination ) {
		global $wp_filesystem;

		if ( 'direct' !== $wp_filesystem->method )
			return $wp_filesystem->move( $source, $destination );

		return false;
	}

	/**
	 * Add Basic Auth headers for authentication.
	 *
	 * @param array $args HTTP header args
	 *
	 * @return array $args
	 */
	public function add_basic_auth_headers( $args ) {
		if ( $this->current_slug === null )
			return $args;

		unset( $args['headers']['Authorization'] );

		remove_filter( 'http_request_args', [ $this, 'add_basic_auth_headers' ] );

		return $args;
	}
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Class IW_Plugin_Installer_Skin
 */
class IW_Plugin_Installer_Skin extends Plugin_Installer_Skin {
	public function header() {}

	public function footer() {}

	public function error( $errors ) {}

	public function feedback( $string, ...$args ) {}
}