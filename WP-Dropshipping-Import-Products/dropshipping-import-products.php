<?php
/**
 * Plugin Name:       Dropshipping Import Products dla WooCommerce
 * Plugin URI:        https://example.com/dropshipping-import-products
 * Description:       Bulk import and scheduled synchronization of products from XML and CSV feeds into WooCommerce. Supports drag-and-drop field mapping, price rules, conditional logic, and Action Scheduler-based sync.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dip
 * Domain Path:       /languages
 * WC requires at least: 8.2
 * WC tested up to:      9.5
 */

defined( 'ABSPATH' ) || exit;

define( 'DIP_VERSION',  '1.0.0' );
define( 'DIP_FILE',     __FILE__ );
define( 'DIP_DIR',      plugin_dir_path( __FILE__ ) );
define( 'DIP_URL',      plugin_dir_url( __FILE__ ) );
define( 'DIP_BASENAME', plugin_basename( __FILE__ ) );

// ── HPOS compatibility declaration ──────────────────────────────────────────
add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DIP_FILE, true );
	}
} );

// ── Bootstrap after all plugins loaded ──────────────────────────────────────
add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'dip_notice_woocommerce_missing' );
		return;
	}

	load_plugin_textdomain( 'dip', false, dirname( DIP_BASENAME ) . '/languages' );

	require_once DIP_DIR . 'includes/class-dip-plugin.php';
	DIP_Plugin::instance()->init();
} );

/**
 * Admin notice when WooCommerce is not active.
 */
function dip_notice_woocommerce_missing(): void {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Dropshipping Import Products requires WooCommerce to be installed and active.', 'dip' ) .
		'</p></div>';
}

// ── Activation / Deactivation hooks ─────────────────────────────────────────
register_activation_hook( DIP_FILE, 'dip_on_activate' );
register_deactivation_hook( DIP_FILE, 'dip_on_deactivate' );

function dip_on_activate(): void {
	require_once DIP_DIR . 'includes/data/class-dip-db.php';
	DIP_DB::create_tables();
	add_option( 'dip_version', DIP_VERSION, '', false );
}

function dip_on_deactivate(): void {
	require_once DIP_DIR . 'includes/scheduler/class-dip-scheduler.php';
	DIP_Scheduler::unschedule_all();
}
