<?php
/**
 * Plugin Name:       STC Product Enquiry
 * Plugin URI:        https://example.com/stc-product-enquiry
 * Description:       Replaces every WooCommerce "Add to Cart" / "Add to Quote" button with an "Enquire Now" button that opens an AJAX enquiry popup. Stores enquiries, emails the admin, and provides an admin dashboard with search, filter, delete and CSV export.
 * Version:           1.0.0
 * Author:            STC
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stc-product-enquiry
 * Domain Path:       /languages
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * WC requires at least: 10.0
 * WC tested up to:   10.8
 *
 * @package STC_Product_Enquiry
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/
define( 'STC_PE_VERSION', '1.0.0' );
define( 'STC_PE_FILE', __FILE__ );
define( 'STC_PE_BASENAME', plugin_basename( __FILE__ ) );
define( 'STC_PE_PATH', plugin_dir_path( __FILE__ ) );
define( 'STC_PE_URL', plugin_dir_url( __FILE__ ) );
define( 'STC_PE_INCLUDES', STC_PE_PATH . 'includes/' );

/**
 * Recipient email address for new enquiry notifications.
 * Can be overridden with the "stc_pe_notification_email" filter or
 * the "stc_pe_notification_email" option.
 */
define( 'STC_PE_DEFAULT_EMAIL', 'sagapreneur@gmail.com' );

/**
 * Main plugin bootstrap class.
 *
 * @since 1.0.0
 */
final class STC_Product_Enquiry {

	/**
	 * Singleton instance.
	 *
	 * @var STC_Product_Enquiry|null
	 */
	private static ?STC_Product_Enquiry $instance = null;

	/**
	 * Database handler.
	 *
	 * @var STC_PE_Database
	 */
	public STC_PE_Database $database;

	/**
	 * Frontend handler.
	 *
	 * @var STC_PE_Frontend
	 */
	public STC_PE_Frontend $frontend;

	/**
	 * Popup handler.
	 *
	 * @var STC_PE_Popup
	 */
	public STC_PE_Popup $popup;

	/**
	 * AJAX handler.
	 *
	 * @var STC_PE_Ajax
	 */
	public STC_PE_Ajax $ajax;

	/**
	 * Email handler.
	 *
	 * @var STC_PE_Email
	 */
	public STC_PE_Email $email;

	/**
	 * Admin handler.
	 *
	 * @var STC_PE_Admin
	 */
	public STC_PE_Admin $admin;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return STC_Product_Enquiry
	 */
	public static function instance(): STC_Product_Enquiry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function includes(): void {
		require_once STC_PE_INCLUDES . 'class-database.php';
		require_once STC_PE_INCLUDES . 'class-email.php';
		require_once STC_PE_INCLUDES . 'class-frontend.php';
		require_once STC_PE_INCLUDES . 'class-popup.php';
		require_once STC_PE_INCLUDES . 'class-ajax.php';
		require_once STC_PE_INCLUDES . 'class-admin.php';
	}

	/**
	 * Register core hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		register_activation_hook( STC_PE_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( STC_PE_FILE, array( $this, 'deactivate' ) );

		// Declare HPOS compatibility for WooCommerce.
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage.
	 *
	 * @return void
	 */
	public function declare_wc_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', STC_PE_FILE, true );
		}
	}

	/**
	 * Initialize plugin components after all plugins are loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// Bail out gracefully if WooCommerce is not active.
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		load_plugin_textdomain( 'stc-product-enquiry', false, dirname( STC_PE_BASENAME ) . '/languages' );

		$this->database = new STC_PE_Database();
		$this->email    = new STC_PE_Email();
		$this->frontend = new STC_PE_Frontend();
		$this->popup    = new STC_PE_Popup();
		$this->ajax     = new STC_PE_Ajax( $this->database, $this->email );
		$this->admin    = new STC_PE_Admin( $this->database );

		// Ensure the database table exists (covers upgrades where activation did not run).
		$this->database->maybe_upgrade();
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function woocommerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'STC Product Enquiry requires WooCommerce to be installed and active.', 'stc-product-enquiry' );
		echo '</p></div>';
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public function activate(): void {
		require_once STC_PE_INCLUDES . 'class-database.php';
		$database = new STC_PE_Database();
		$database->create_table();

		if ( false === get_option( 'stc_pe_notification_email', false ) ) {
			add_option( 'stc_pe_notification_email', STC_PE_DEFAULT_EMAIL );
		}

		add_option( 'stc_pe_db_version', STC_PE_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}
}

/**
 * Returns the main plugin instance.
 *
 * @return STC_Product_Enquiry
 */
function stc_product_enquiry(): STC_Product_Enquiry {
	return STC_Product_Enquiry::instance();
}

// Boot the plugin.
stc_product_enquiry();
