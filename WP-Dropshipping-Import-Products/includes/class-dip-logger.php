<?php
defined( 'ABSPATH' ) || exit;

/**
 * Buffers and persists import log entries to {prefix}dip_logs.
 * Auto-flushes when the buffer reaches $flush_size, and on destruct.
 */
class DIP_Logger {

	private int   $run_id;
	private int   $feed_id;
	/** @var list<array<string,mixed>> */
	private array $buffer     = [];
	private int   $flush_size = 50;

	public function __construct( int $run_id, int $feed_id ) {
		$this->run_id  = $run_id;
		$this->feed_id = $feed_id;
	}

	/**
	 * @param string   $status        'created' | 'updated' | 'skipped' | 'error' | 'info'
	 * @param string   $message
	 * @param int      $record_index  Zero-based record position in the feed.
	 * @param int|null $product_id    WooCommerce product ID if available.
	 */
	public function log( string $status, string $message, int $record_index = 0, ?int $product_id = null ): void {
		$this->buffer[] = [
			'run_id'       => $this->run_id,
			'feed_id'      => $this->feed_id,
			'product_id'   => $product_id,
			'record_index' => $record_index,
			'status'       => $status,
			'message'      => $message,
			'created_at'   => current_time( 'mysql' ),
		];

		if ( count( $this->buffer ) >= $this->flush_size ) {
			$this->flush();
		}
	}

	public function flush(): void {
		if ( empty( $this->buffer ) ) {
			return;
		}
		global $wpdb;
		foreach ( $this->buffer as $row ) {
			$wpdb->insert( $wpdb->prefix . 'dip_logs', $row );
		}
		$this->buffer = [];
	}

	public function __destruct() {
		$this->flush();
	}
}
