<?php
defined( 'ABSPATH' ) || exit;

/**
 * Central plugin orchestrator. Loads all dependencies and bootstraps modules.
 */
class DIP_Plugin {

	private static ?DIP_Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		// Data layer
		require_once DIP_DIR . 'includes/data/class-dip-db.php';
		require_once DIP_DIR . 'includes/class-dip-logger.php';

		// Importer
		require_once DIP_DIR . 'includes/importer/class-dip-feed-manager.php';
		require_once DIP_DIR . 'includes/importer/class-dip-xml-parser.php';
		require_once DIP_DIR . 'includes/importer/class-dip-csv-parser.php';
		require_once DIP_DIR . 'includes/importer/class-dip-field-mapper.php';

		// Processor
		require_once DIP_DIR . 'includes/processor/class-dip-price-rules.php';
		require_once DIP_DIR . 'includes/processor/class-dip-category-handler.php';
		require_once DIP_DIR . 'includes/processor/class-dip-image-handler.php';
		require_once DIP_DIR . 'includes/processor/class-dip-product-processor.php';

		// Sync
		require_once DIP_DIR . 'includes/sync/class-dip-matcher.php';
		require_once DIP_DIR . 'includes/sync/class-dip-sync-runner.php';

		// Scheduler
		require_once DIP_DIR . 'includes/scheduler/class-dip-scheduler.php';

		// REST API
		require_once DIP_DIR . 'includes/api/class-dip-rest-api.php';

		// Admin — only in admin context
		if ( is_admin() ) {
			require_once DIP_DIR . 'includes/admin/class-dip-admin-settings.php';
			require_once DIP_DIR . 'includes/admin/class-dip-admin-feeds.php';
			require_once DIP_DIR . 'includes/admin/class-dip-admin-logs.php';
			require_once DIP_DIR . 'includes/admin/class-dip-admin.php';
		}
	}

	private function init_hooks(): void {
		DIP_Scheduler::init();
		DIP_REST_API::init();

		if ( is_admin() ) {
			DIP_Admin::init();
		}
	}
}
