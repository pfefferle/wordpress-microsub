<?php
/**
 * Microsub Class.
 *
 * @package Microsub
 */

namespace Microsub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Microsub Class.
 *
 * Main plugin class that handles initialization and hooks.
 */
class Microsub {

	/**
	 * Instance of the class.
	 *
	 * @var Microsub
	 */
	private static $instance;

	/**
	 * Whether the class has been initialized.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Whether adapters have been registered.
	 *
	 * @var bool
	 */
	private $adapters_registered = false;

	/**
	 * Get the instance of the class.
	 *
	 * @return Microsub
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Do not allow multiple instances of the class.
	 */
	private function __construct() {
		// Do nothing.
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		if ( $this->initialized ) {
			return;
		}

		$this->register_hooks();
		$this->register_adapters();

		$this->initialized = true;
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return MICROSUB_VERSION;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		// Discovery headers.
		\add_action( 'wp_head', array( $this, 'html_header' ) );
		\add_action( 'send_headers', array( $this, 'http_header' ) );

		// REST API.
		\add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Register built-in adapters.
		\add_action( 'plugins_loaded', array( $this, 'register_adapters' ), 20 );
	}

	/**
	 * Register built-in adapters.
	 */
	public function register_adapters() {
		if ( $this->adapters_registered ) {
			return;
		}

		$this->adapters_registered = true;

		// Register WordPress core feeds adapter.
		$adapter = new Adapters\WordPress();
		$adapter->register();

		// Register Friends adapter if Friends plugin is installed.
		if ( \defined( 'FRIENDS_VERSION' ) ) {
			$adapter = new Adapters\Friends();
			$adapter->register();
		}

		// Register ActivityPub adapter if ActivityPub plugin is installed.
		if ( \defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) {
			$adapter = new Adapters\ActivityPub();
			$adapter->register();
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		$controller = new Rest_Controller();
		$controller->register_routes();
	}

	/**
	 * Add Microsub endpoint to HTML header.
	 */
	public function html_header() {
		$endpoint = $this->get_endpoint();
		\printf( '<link rel="microsub" href="%s" />' . PHP_EOL, \esc_url( $endpoint ) );
	}

	/**
	 * Add Microsub endpoint to HTTP Link header.
	 */
	public function http_header() {
		$endpoint = $this->get_endpoint();
		\header( \sprintf( 'Link: <%s>; rel="microsub"', \esc_url( $endpoint ) ), false );
	}

	/**
	 * Get the Microsub endpoint URL.
	 *
	 * @return string The Microsub endpoint URL.
	 */
	public function get_endpoint() {
		/**
		 * Filters the Microsub endpoint URL.
		 *
		 * @param string $endpoint The Microsub endpoint URL.
		 */
		return \apply_filters( 'microsub_endpoint', \rest_url( 'microsub/1.0/endpoint' ) );
	}
}
