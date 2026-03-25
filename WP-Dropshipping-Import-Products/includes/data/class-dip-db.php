<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages custom database tables for feeds, import runs, and log entries.
 * Schema changes are applied via dbDelta() and are idempotent.
 */
class DIP_DB {

	public static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sqls = [];

		$sqls[] = "CREATE TABLE {$wpdb->prefix}dip_feeds (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name          VARCHAR(255)    NOT NULL DEFAULT '',
			source_url    TEXT            NOT NULL,
			source_type   VARCHAR(10)     NOT NULL DEFAULT 'xml',
			status        VARCHAR(20)     NOT NULL DEFAULT 'active',
			mapping       LONGTEXT        NOT NULL,
			settings      LONGTEXT        NOT NULL,
			last_run_at   DATETIME        NULL,
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset;";

		$sqls[] = "CREATE TABLE {$wpdb->prefix}dip_runs (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			feed_id       BIGINT UNSIGNED NOT NULL,
			status        VARCHAR(20)     NOT NULL DEFAULT 'pending',
			created_count INT UNSIGNED    NOT NULL DEFAULT 0,
			updated_count INT UNSIGNED    NOT NULL DEFAULT 0,
			skipped_count INT UNSIGNED    NOT NULL DEFAULT 0,
			error_count   INT UNSIGNED    NOT NULL DEFAULT 0,
			started_at    DATETIME        NULL,
			finished_at   DATETIME        NULL,
			PRIMARY KEY  (id),
			KEY idx_feed_id (feed_id)
		) $charset;";

		$sqls[] = "CREATE TABLE {$wpdb->prefix}dip_logs (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id        BIGINT UNSIGNED NOT NULL,
			feed_id       BIGINT UNSIGNED NOT NULL,
			product_id    BIGINT UNSIGNED NULL,
			record_index  INT UNSIGNED    NOT NULL DEFAULT 0,
			status        VARCHAR(20)     NOT NULL DEFAULT 'info',
			message       TEXT            NOT NULL DEFAULT '',
			created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_run_id  (run_id),
			KEY idx_feed_id (feed_id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sqls as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'dip_db_version', DIP_VERSION, false );
	}

	// ── Feeds ────────────────────────────────────────────────────────────────

	/** @return array<string,mixed>|null */
	public static function get_feed( int $feed_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dip_feeds WHERE id = %d", $feed_id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/** @return list<array<string,mixed>> */
	public static function get_feeds(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}dip_feeds ORDER BY created_at DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Insert or update a feed row. Returns the feed ID.
	 * @param array<string,mixed> $data
	 */
	public static function save_feed( array $data ): int {
		global $wpdb;
		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$wpdb->update( $wpdb->prefix . 'dip_feeds', $data, [ 'id' => $id ] );
			return $id;
		}
		$wpdb->insert( $wpdb->prefix . 'dip_feeds', $data );
		return (int) $wpdb->insert_id;
	}

	public static function delete_feed( int $feed_id ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'dip_feeds', [ 'id' => $feed_id ], [ '%d' ] );
	}

	// ── Runs ─────────────────────────────────────────────────────────────────

	public static function create_run( int $feed_id ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'dip_runs',
			[
				'feed_id'    => $feed_id,
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
			]
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $counts */
	public static function finish_run( int $run_id, array $counts, string $status = 'done' ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'dip_runs',
			array_merge( $counts, [ 'status' => $status, 'finished_at' => current_time( 'mysql' ) ] ),
			[ 'id' => $run_id ]
		);
	}

	/** @return list<array<string,mixed>> */
	public static function get_runs( int $feed_id, int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dip_runs WHERE feed_id = %d ORDER BY started_at DESC LIMIT %d",
				$feed_id,
				$limit
			),
			ARRAY_A
		) ?: [];
	}

	// ── Logs ─────────────────────────────────────────────────────────────────

	/** @return list<array<string,mixed>> */
	public static function get_logs( int $run_id, int $limit = 500, int $offset = 0 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dip_logs WHERE run_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				$run_id,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: [];
	}

	public static function get_log_count( int $run_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}dip_logs WHERE run_id = %d", $run_id )
		);
	}

	/**
	 * Delete log entries older than $days days.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function delete_old_logs( int $days ): int {
		if ( $days <= 0 ) {
			return 0;
		}
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}dip_logs WHERE created_at < %s",
				$cutoff
			)
		);
		return (int) $wpdb->rows_affected;
	}
}
