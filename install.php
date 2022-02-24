<?php
/**
 * Class IW_Deprecation_Upgrade
 *
 * Based on https://github.com/afragen/wp-dependency-installer.
 */
class IW_Deprecation_Upgrade {

	public $current_slug = null;
	public $download_link = '';
	public $slug = 'imagein/imagein.php';
	public $status = '';

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {}

	/**
	 * Instance.
	 *
	 * @return object
	 */
	public static function instance() {
		static $instance = null;

		if ( $instance === null )
			$instance = new self();

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
	 * Determine if the plugin is active or installed.
	 *
	 * @return void
	 */
	public function admin_init() {
		// do not install plugin translations
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

		// get transient
		$transient = 'iw_link_' . md5( __DIR__ );

		// get download link
		$download_link = get_site_transient( $transient );

		if ( ! $download_link ) {
			// get short slug
			$slug = dirname( $this->slug );

			$response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.1/?action=plugin_information&request%5Bslug%5D=' . $slug );
			$response = json_decode( wp_remote_retrieve_body( $response ) );
			$download_link = empty( $response ) ? 'https://downloads.wordpress.org/plugin/' . $slug . '.zip' : $response->download_link;

			set_site_transient( $transient, $download_link, DAY_IN_SECONDS );
		}

		$this->download_link = $download_link;

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
	 * Is plugin installed?
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function is_installed( $slug ) {
		$plugins = get_plugins();

		return isset( $plugins[ $slug ] );
	}

	/**
	 * Install and activate the plugin.
	 *
	 * @param string $slug
	 * @return string
	 */
	public function install( $slug ) {
		if ( ! current_user_can( 'update_plugins' ) )
			return 'no_capability';

		$this->current_slug = $this->slug;

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
	 * Activate the plugin.
	 *
	 * @param string $slug
	 * @return string
	 */
	public function activate( $slug ) {
		// network activate only if on network admin pages.
		$result = is_network_admin() ? activate_plugin( $slug, null, true ) : activate_plugin( $slug );

		if ( is_wp_error( $result ) )
			return 'activation_error';

		return 'activation_success';
	}

	/**
	 * Add Basic Auth headers for authentication.
	 *
	 * @param array $args
	 * @return array
	 */
	public function add_basic_auth_headers( $args ) {
		if ( $this->current_slug === null )
			return $args;

		if ( isset( $args['headers']['Authorization'] ) )
			unset( $args['headers']['Authorization'] );

		remove_filter( 'http_request_args', array( $this, 'add_basic_auth_headers' ) );

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