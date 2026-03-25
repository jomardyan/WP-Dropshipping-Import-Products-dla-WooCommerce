<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API module for the DIP plugin.
 *
 * Registers read/write endpoints under /wp-json/dip/v1/ for feeds, runs, and logs.
 * All state-changing endpoints verify a nonce via the X-WP-Nonce header or the
 * standard WP REST nonce mechanism (wp_rest capability + nonce).
 *
 * Endpoints:
 *   GET  /dip/v1/feeds              — list all feeds
 *   POST /dip/v1/feeds              — create a feed
 *   GET  /dip/v1/feeds/{id}         — read one feed
 *   PUT  /dip/v1/feeds/{id}         — update a feed
 *   DELETE /dip/v1/feeds/{id}       — delete a feed
 *   POST /dip/v1/feeds/{id}/run     — trigger an import run
 *   GET  /dip/v1/feeds/{id}/runs    — list runs for a feed
 *   GET  /dip/v1/runs/{run_id}/logs — list log entries for a run
 *   GET  /dip/v1/settings           — read global settings
 *   POST /dip/v1/settings           — update global settings
 */
class DIP_REST_API {

	public const NAMESPACE = 'dip/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		// ── Feeds Collection ─────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/feeds',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'get_feeds' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'create_feed' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => self::feed_args( false ),
				],
			]
		);

		// ── Single Feed ───────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/feeds/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'get_feed' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [ 'id' => [ 'validate_callback' => 'is_numeric' ] ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ __CLASS__, 'update_feed' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => self::feed_args( true ),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'delete_feed' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
			]
		);

		// ── Trigger Import ────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/feeds/(?P<id>\d+)/run',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'run_import' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		// ── Feed Runs ─────────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/feeds/(?P<id>\d+)/runs',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_runs' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'limit' => [
						'default'           => 20,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// ── Run Log Entries ───────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/runs/(?P<run_id>\d+)/logs',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_logs' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
				'args'                => [
					'page'  => [ 'default' => 1,   'sanitize_callback' => 'absint' ],
					'limit' => [ 'default' => 100, 'sanitize_callback' => 'absint' ],
				],
			]
		);

		// ── Settings ──────────────────────────────────────────────────────────
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'get_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ __CLASS__, 'update_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => self::settings_args(),
				],
			]
		);
	}

	// ── Permission callback ───────────────────────────────────────────────────

	public static function check_permission(): bool|\WP_Error {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to manage imports.', 'dip' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}
		return true;
	}

	// ── Feeds endpoints ───────────────────────────────────────────────────────

	public static function get_feeds( \WP_REST_Request $request ): \WP_REST_Response {
		$feeds = DIP_DB::get_feeds();
		$data  = array_map( [ __CLASS__, 'prepare_feed' ], $feeds );
		return rest_ensure_response( $data );
	}

	public static function get_feed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$feed = DIP_DB::get_feed( (int) $request->get_param( 'id' ) );
		if ( ! $feed ) {
			return new \WP_Error(
				'dip_feed_not_found',
				esc_html__( 'Feed not found.', 'dip' ),
				[ 'status' => 404 ]
			);
		}
		return rest_ensure_response( self::prepare_feed( $feed ) );
	}

	public static function create_feed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data     = self::extract_feed_data( $request );
		$saved_id = DIP_DB::save_feed( $data );

		if ( ! empty( $data['settings']['schedule_interval'] ) ) {
			DIP_Scheduler::schedule( $saved_id, (int) $data['settings']['schedule_interval'] );
		}

		$feed = DIP_DB::get_feed( $saved_id );
		return rest_ensure_response( self::prepare_feed( $feed ) );
	}

	public static function update_feed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );
		if ( ! DIP_DB::get_feed( $id ) ) {
			return new \WP_Error(
				'dip_feed_not_found',
				esc_html__( 'Feed not found.', 'dip' ),
				[ 'status' => 404 ]
			);
		}

		$data       = self::extract_feed_data( $request );
		$data['id'] = $id;
		DIP_DB::save_feed( $data );

		$interval = (int) ( $data['settings']['schedule_interval'] ?? 0 );
		if ( $interval > 0 ) {
			DIP_Scheduler::schedule( $id, $interval );
		} else {
			DIP_Scheduler::unschedule( $id );
		}

		$feed = DIP_DB::get_feed( $id );
		return rest_ensure_response( self::prepare_feed( $feed ) );
	}

	public static function delete_feed( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (int) $request->get_param( 'id' );
		if ( ! DIP_DB::get_feed( $id ) ) {
			return new \WP_Error(
				'dip_feed_not_found',
				esc_html__( 'Feed not found.', 'dip' ),
				[ 'status' => 404 ]
			);
		}
		DIP_Scheduler::unschedule( $id );
		DIP_DB::delete_feed( $id );
		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}

	// ── Import trigger ────────────────────────────────────────────────────────

	public static function run_import( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$feed_id = (int) $request->get_param( 'id' );
		if ( ! DIP_DB::get_feed( $feed_id ) ) {
			return new \WP_Error(
				'dip_feed_not_found',
				esc_html__( 'Feed not found.', 'dip' ),
				[ 'status' => 404 ]
			);
		}
		DIP_Sync_Runner::run( $feed_id );
		return rest_ensure_response( [
			'triggered' => true,
			'feed_id'   => $feed_id,
			'message'   => esc_html__( 'Import completed successfully.', 'dip' ),
		] );
	}

	// ── Runs ──────────────────────────────────────────────────────────────────

	public static function get_runs( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$feed_id = (int) $request->get_param( 'id' );
		if ( ! DIP_DB::get_feed( $feed_id ) ) {
			return new \WP_Error(
				'dip_feed_not_found',
				esc_html__( 'Feed not found.', 'dip' ),
				[ 'status' => 404 ]
			);
		}
		$limit = min( (int) $request->get_param( 'limit' ), 100 );
		$runs  = DIP_DB::get_runs( $feed_id, $limit );
		return rest_ensure_response( $runs );
	}

	// ── Logs ──────────────────────────────────────────────────────────────────

	public static function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$run_id = (int) $request->get_param( 'run_id' );
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$limit  = min( (int) $request->get_param( 'limit' ), 500 );
		$offset = ( $page - 1 ) * $limit;

		$logs  = DIP_DB::get_logs( $run_id, $limit, $offset );
		$total = DIP_DB::get_log_count( $run_id );

		$response = rest_ensure_response( $logs );
		$response->header( 'X-DIP-Total-Logs', (string) $total );
		$response->header( 'X-DIP-Total-Pages', (string) (int) ceil( $total / $limit ) );
		return $response;
	}

	// ── Settings ──────────────────────────────────────────────────────────────

	public static function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( DIP_Admin_Settings::get() );
	}

	public static function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$current = DIP_Admin_Settings::get();
		$params  = $request->get_json_params() ?? [];

		$updated = [
			'timeout'             => isset( $params['timeout'] )             ? absint( $params['timeout'] )           : ( $current['timeout']             ?? 60 ),
			'log_retention'       => isset( $params['log_retention'] )       ? absint( $params['log_retention'] )     : ( $current['log_retention']       ?? 30 ),
			'batch_size'          => isset( $params['batch_size'] )          ? min( 500, max( 10, absint( $params['batch_size'] ) ) ) : ( $current['batch_size']    ?? 50 ),
			'image_timeout'       => isset( $params['image_timeout'] )       ? min( 300, max( 5,  absint( $params['image_timeout'] ) ) ) : ( $current['image_timeout'] ?? 30 ),
			'debug_mode'          => isset( $params['debug_mode'] )          ? (bool)  $params['debug_mode']          : ( $current['debug_mode']          ?? false ),
			'delete_on_uninstall' => isset( $params['delete_on_uninstall'] ) ? (bool)  $params['delete_on_uninstall'] : ( $current['delete_on_uninstall'] ?? false ),
		];

		update_option( 'dip_global_settings', $updated, false );
		return rest_ensure_response( $updated );
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Decode JSON fields (settings, mapping) in a feed row for API output.
	 *
	 * @param array<string,mixed> $feed
	 * @return array<string,mixed>
	 */
	private static function prepare_feed( array $feed ): array {
		$feed['settings'] = json_decode( $feed['settings'] ?? '{}', true ) ?? [];
		$feed['mapping']  = json_decode( $feed['mapping']  ?? '{}', true ) ?? [];
		return $feed;
	}

	/**
	 * Extract and sanitise feed fields from a REST request.
	 *
	 * @return array<string,mixed>
	 */
	private static function extract_feed_data( \WP_REST_Request $request ): array {
		$json = $request->get_json_params() ?? [];

		$name        = sanitize_text_field( (string) ( $json['name']        ?? '' ) );
		$source_url  = esc_url_raw( (string) ( $json['source_url']  ?? '' ) );
		$source_type = sanitize_key( (string) ( $json['source_type'] ?? 'xml' ) );
		$status      = sanitize_key( (string) ( $json['status']      ?? 'active' ) );
		$mapping     = $json['mapping']  ?? [];
		$settings    = $json['settings'] ?? [];

		// Sanitise settings keys.
		$clean_settings = [
			'match'             => [
				'method'   => sanitize_key( (string) ( $settings['match']['method']   ?? 'sku' ) ),
				'meta_key' => sanitize_key( (string) ( $settings['match']['meta_key'] ?? '_dip_custom_id' ) ),
			],
			'create_as_draft'   => (bool) ( $settings['create_as_draft'] ?? false ),
			'update_fields'     => array_filter( array_map( 'sanitize_key', (array) ( $settings['update_fields'] ?? [] ) ) ),
			'price_rules'       => (array) ( $settings['price_rules'] ?? [] ),
			'conditions'        => (array) ( $settings['conditions']  ?? [] ),
			'schedule_interval' => absint( $settings['schedule_interval'] ?? 0 ),
			'xml_item_node'     => sanitize_text_field( (string) ( $settings['xml_item_node'] ?? '' ) ),
			'csv_delimiter'     => (string) ( $settings['csv_delimiter'] ?? ',' ),
		];

		return [
			'name'        => $name,
			'source_url'  => $source_url,
			'source_type' => $source_type,
			'status'      => $status,
			'mapping'     => wp_json_encode( $mapping ),
			'settings'    => wp_json_encode( $clean_settings ),
		];
	}

	/**
	 * REST schema args for feed create/update endpoints.
	 *
	 * @return array<string,mixed>
	 */
	private static function feed_args( bool $all_optional ): array {
		$req = ! $all_optional;
		return [
			'name'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => $req,
			],
			'source_url'  => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'required'          => $req,
			],
			'source_type' => [
				'type'              => 'string',
				'enum'              => [ 'xml', 'csv' ],
				'sanitize_callback' => 'sanitize_key',
				'required'          => false,
			],
			'status'      => [
				'type'              => 'string',
				'enum'              => [ 'active', 'paused' ],
				'sanitize_callback' => 'sanitize_key',
				'required'          => false,
			],
		];
	}

	/**
	 * REST schema args for settings update.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings_args(): array {
		return [
			'timeout' => [
				'type'    => 'integer',
				'minimum' => 10,
				'maximum' => 600,
			],
			'log_retention' => [
				'type'    => 'integer',
				'minimum' => 0,
				'maximum' => 365,
			],
			'batch_size' => [
				'type'    => 'integer',
				'minimum' => 10,
				'maximum' => 500,
			],
			'image_timeout' => [
				'type'    => 'integer',
				'minimum' => 5,
				'maximum' => 300,
			],
			'debug_mode'          => [ 'type' => 'boolean' ],
			'delete_on_uninstall' => [ 'type' => 'boolean' ],
		];
	}
}
