<?php
defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates a complete import/sync run for a feed.
 * Fetches the feed, parses it, applies mapping, and processes each record
 * through DIP_Product_Processor. All results are logged to dip_logs.
 */
class DIP_Sync_Runner {

	/** Fallback number of records per chunk when no setting is configured. */
	private const DEFAULT_CHUNK_SIZE = 50;

	/**
	 * Run an import for a feed.
	 *
	 * @param int                 $feed_id
	 * @param array<string,mixed> $override_settings  Partial settings to merge (e.g. sync_type).
	 */
	public static function run( int $feed_id, array $override_settings = [] ): void {
		$feed = DIP_DB::get_feed( $feed_id );
		if ( ! $feed ) {
			return;
		}

		$settings = array_merge(
			json_decode( $feed['settings'] ?? '{}', true ) ?? [],
			$override_settings
		);
		$mapping = json_decode( $feed['mapping'] ?? '{}', true ) ?? [];

		$run_id = DIP_DB::create_run( $feed_id );
		$logger = new DIP_Logger( $run_id, $feed_id );

		$counts = [
			'created_count' => 0,
			'updated_count' => 0,
			'skipped_count' => 0,
			'error_count'   => 0,
		];

		$tmp_file = null;

		try {
			// ── Fetch feed ───────────────────────────────────────────────────
			$tmp_file = DIP_Feed_Manager::fetch( $feed['source_url'] );
			if ( ! $tmp_file ) {
				throw new \RuntimeException(
					sprintf(
						/* translators: %s: feed source URL */
						__( 'Failed to fetch feed from: %s', 'dip' ),
						esc_url( $feed['source_url'] )
					)
				);
			}

			// ── Build generator ──────────────────────────────────────────────
			$source_type = $feed['source_type'] ?? 'xml';
			$generator   = self::build_generator( $source_type, $tmp_file, $settings );

			// ── Clear caches before run ──────────────────────────────────────
			DIP_Category_Handler::clear_cache();

			$global   = (array) get_option( 'dip_global_settings', [] );
			$chunk    = isset( $global['batch_size'] ) ? (int) $global['batch_size'] : self::DEFAULT_CHUNK_SIZE;
			$chunk    = max( 1, $chunk );

			// ── Process records ──────────────────────────────────────────────
			foreach ( $generator as $index => $record ) {
				$product_data = DIP_Field_Mapper::map( $record, $mapping );
				$result       = DIP_Product_Processor::process( $product_data, $settings, $logger, $index );
				$counts[ $result . '_count' ]++;

				// Reset time limit every $chunk records
				if ( 0 === $index % $chunk ) {
					if ( function_exists( 'wc_set_time_limit' ) ) {
						wc_set_time_limit( 0 );
					}
				}
			}

			// ── Update feed last_run_at ──────────────────────────────────────
			DIP_DB::save_feed( [
				'id'          => $feed_id,
				'last_run_at' => current_time( 'mysql' ),
			] );

			DIP_DB::finish_run( $run_id, $counts, 'done' );

			$logger->log(
				'info',
				sprintf(
					/* translators: 1: created 2: updated 3: skipped 4: errors */
					__( 'Run complete. Created: %1$d, Updated: %2$d, Skipped: %3$d, Errors: %4$d', 'dip' ),
					$counts['created_count'],
					$counts['updated_count'],
					$counts['skipped_count'],
					$counts['error_count']
				)
			);

		} catch ( \Throwable $e ) {
			$logger->log( 'error', $e->getMessage() );
			DIP_DB::finish_run( $run_id, $counts, 'error' );
		}

		$logger->flush();

		if ( $tmp_file ) {
			DIP_Feed_Manager::cleanup( $tmp_file );
		}
	}

	/**
	 * Build the appropriate parser generator for the given source type.
	 *
	 * @param array<string,mixed> $settings
	 * @return \Generator<int, array<string,mixed>>
	 */
	private static function build_generator( string $type, string $file_path, array $settings ): \Generator {
		if ( 'csv' === $type ) {
			$delimiter = $settings['csv_delimiter'] ?? DIP_CSV_Parser::detect_delimiter( $file_path );
			return ( new DIP_CSV_Parser( $file_path, $delimiter ) )->parse();
		}

		$item_node = $settings['xml_item_node'] ?? DIP_XML_Parser::detect_item_node( $file_path );
		return ( new DIP_XML_Parser( $file_path, $item_node ) )->parse();
	}
}
