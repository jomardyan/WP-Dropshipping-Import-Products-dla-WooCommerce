<?php
defined( 'ABSPATH' ) || exit;

/**
 * Imports images from remote URLs into the WordPress media library.
 * Deduplicates via a source-URL meta key to avoid re-downloading.
 */
class DIP_Image_Handler {

	private const SOURCE_META_KEY = '_dip_source_url';

	/**
	 * Import an image from a URL and return the attachment ID.
	 * Returns 0 on failure.
	 */
	public static function import_from_url( string $url ): int {
		$url = esc_url_raw( trim( $url ) );
		if ( empty( $url ) ) {
			return 0;
		}

		// Check if already imported
		$existing = self::find_by_source_url( $url );
		if ( $existing > 0 ) {
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		$file_array = [
			'name'     => sanitize_file_name( basename( (string) strtok( $url, '?' ) ) ),
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $tmp );
			return 0;
		}

		update_post_meta( $attachment_id, self::SOURCE_META_KEY, $url );

		return $attachment_id;
	}

	/**
	 * Process a gallery value: pipe or comma-separated URLs.
	 *
	 * @param string|list<string> $value
	 * @return list<int>  attachment IDs
	 */
	public static function process_gallery( $value ): array {
		if ( is_string( $value ) ) {
			$urls = array_filter( array_map( 'trim', (array) preg_split( '/[|,]/', $value ) ) );
		} else {
			$urls = (array) $value;
		}

		$ids = [];
		foreach ( $urls as $url ) {
			$id = self::import_from_url( (string) $url );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Find an existing attachment by its original source URL.
	 */
	private static function find_by_source_url( string $url ): int {
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 INNER JOIN {$wpdb->posts} ON ({$wpdb->postmeta}.post_id = {$wpdb->posts}.ID)
				 WHERE {$wpdb->postmeta}.meta_key = %s
				   AND {$wpdb->postmeta}.meta_value = %s
				   AND {$wpdb->posts}.post_type = 'attachment'
				 LIMIT 1",
				self::SOURCE_META_KEY,
				$url
			)
		);
		return $id ? (int) $id : 0;
	}
}
