<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main admin controller: registers menus, enqueues assets, handles AJAX.
 */
class DIP_Admin {

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_post_dip_save_feed',   [ 'DIP_Admin_Feeds', 'handle_save' ] );
		add_action( 'admin_post_dip_delete_feed', [ 'DIP_Admin_Feeds', 'handle_delete' ] );
		add_action( 'admin_post_dip_save_settings', [ 'DIP_Admin_Settings', 'handle_save' ] );
		add_action( 'wp_ajax_dip_run_import',      [ __CLASS__, 'ajax_run_import' ] );
		add_action( 'wp_ajax_dip_preview_feed',    [ __CLASS__, 'ajax_preview_feed' ] );
		add_action( 'wp_ajax_dip_detect_fields',   [ __CLASS__, 'ajax_detect_fields' ] );
	}

	public static function register_menus(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Import Products', 'dip' ),
			__( 'Import Products', 'dip' ),
			'manage_woocommerce',
			'dip-feeds',
			[ 'DIP_Admin_Feeds', 'render' ]
		);
		add_submenu_page(
			'woocommerce',
			__( 'Import Logs', 'dip' ),
			__( 'Import Logs', 'dip' ),
			'manage_woocommerce',
			'dip-logs',
			[ 'DIP_Admin_Logs', 'render' ]
		);
		add_submenu_page(
			'woocommerce',
			__( 'Import Settings', 'dip' ),
			__( 'Import Settings', 'dip' ),
			'manage_woocommerce',
			'dip-settings',
			[ 'DIP_Admin_Settings', 'render' ]
		);
	}

	public static function enqueue_assets( string $hook ): void {
		$dip_pages = [
			'woocommerce_page_dip-feeds',
			'woocommerce_page_dip-logs',
			'woocommerce_page_dip-settings',
		];
		if ( ! in_array( $hook, $dip_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'dip-admin',
			DIP_URL . 'assets/css/admin.css',
			[],
			DIP_VERSION
		);

		wp_enqueue_script(
			'dip-admin',
			DIP_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable', 'wp-i18n' ],
			DIP_VERSION,
			true
		);

		wp_set_script_translations( 'dip-admin', 'dip', DIP_DIR . 'languages' );

		wp_localize_script(
			'dip-admin',
			'dipAdmin',
			[
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'dip_admin_nonce' ),
				'targetFields'      => DIP_Field_Mapper::wc_target_fields(),
				'priceRuleTypes'    => DIP_Price_Rules::rule_types(),
				'intervalOptions'   => DIP_Scheduler::interval_options(),
				'i18n'              => [
					'running'          => __( 'Running…', 'dip' ),
					'done'             => __( 'Done', 'dip' ),
					'error'            => __( 'Error', 'dip' ),
					'previewLoading'   => __( 'Loading preview…', 'dip' ),
					'fieldsLoading'    => __( 'Detecting fields…', 'dip' ),
					'confirmDelete'    => __( 'Delete this feed? This cannot be undone.', 'dip' ),
					'addMapping'       => __( 'Add field mapping', 'dip' ),
					'addRule'          => __( 'Add price rule', 'dip' ),
					'addCondition'     => __( 'Add condition', 'dip' ),
					'sourceField'      => __( 'Source field', 'dip' ),
					'targetField'      => __( 'WooCommerce field', 'dip' ),
					'defaultValue'     => __( 'Default value', 'dip' ),
					'remove'           => __( 'Remove', 'dip' ),
					'noFields'         => __( 'No fields detected. Enter a feed URL and click "Detect fields" first.', 'dip' ),
				],
			]
		);
	}

	// ── AJAX handlers ─────────────────────────────────────────────────────────

	public static function ajax_run_import(): void {
		check_ajax_referer( 'dip_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'dip' ) ], 403 );
		}

		$feed_id = absint( $_POST['feed_id'] ?? 0 );
		if ( ! $feed_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid feed ID.', 'dip' ) ] );
		}

		DIP_Sync_Runner::run( $feed_id );

		wp_send_json_success( [ 'message' => __( 'Import completed successfully.', 'dip' ) ] );
	}

	public static function ajax_preview_feed(): void {
		check_ajax_referer( 'dip_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'dip' ) ], 403 );
		}

		$source_url  = esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) );
		$source_type = sanitize_key( $_POST['source_type'] ?? 'xml' );

		if ( empty( $source_url ) ) {
			wp_send_json_error( [ 'message' => __( 'Source URL is required.', 'dip' ) ] );
		}

		$file_path = DIP_Feed_Manager::fetch( $source_url );
		if ( ! $file_path ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch the feed. Check the URL and try again.', 'dip' ) ] );
		}

		if ( 'csv' === $source_type ) {
			$delimiter = DIP_CSV_Parser::detect_delimiter( $file_path );
			$records   = DIP_CSV_Parser::preview( $file_path, 5, $delimiter );
			$fields    = DIP_CSV_Parser::get_field_names( $file_path, $delimiter );
			DIP_Feed_Manager::cleanup( $file_path );
			wp_send_json_success( [ 'records' => $records, 'fields' => $fields, 'delimiter' => $delimiter ] );
		} else {
			$item_node = DIP_XML_Parser::detect_item_node( $file_path );
			$records   = DIP_XML_Parser::preview( $file_path, $item_node, 5 );
			$fields    = DIP_XML_Parser::get_field_names( $file_path, $item_node );
			DIP_Feed_Manager::cleanup( $file_path );
			wp_send_json_success( [ 'records' => $records, 'fields' => $fields, 'item_node' => $item_node ] );
		}
	}

	public static function ajax_detect_fields(): void {
		check_ajax_referer( 'dip_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'dip' ) ], 403 );
		}

		$source_url  = esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) );
		$source_type = sanitize_key( $_POST['source_type'] ?? 'xml' );

		if ( empty( $source_url ) ) {
			wp_send_json_error( [ 'message' => __( 'Source URL is required.', 'dip' ) ] );
		}

		$file_path = DIP_Feed_Manager::fetch( $source_url );
		if ( ! $file_path ) {
			wp_send_json_error( [ 'message' => __( 'Failed to fetch the feed.', 'dip' ) ] );
		}

		if ( 'csv' === $source_type ) {
			$delimiter = DIP_CSV_Parser::detect_delimiter( $file_path );
			$fields    = DIP_CSV_Parser::get_field_names( $file_path, $delimiter );
			DIP_Feed_Manager::cleanup( $file_path );
			wp_send_json_success( [ 'fields' => $fields, 'delimiter' => $delimiter ] );
		} else {
			$item_node = DIP_XML_Parser::detect_item_node( $file_path );
			$fields    = DIP_XML_Parser::get_field_names( $file_path, $item_node );
			DIP_Feed_Manager::cleanup( $file_path );
			wp_send_json_success( [ 'fields' => $fields, 'item_node' => $item_node ] );
		}
	}
}
