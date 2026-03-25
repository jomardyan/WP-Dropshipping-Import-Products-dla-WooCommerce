<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles fetching feed files from remote URLs or local paths.
 */
class DIP_Feed_Manager {

	/**
	 * Fetch the feed and return a local file path to its contents.
	 * For remote URLs the file is downloaded to a temp location.
	 * Returns null on failure.
	 */
	public static function fetch( string $source_url ): ?string {
		$source_url = trim( $source_url );

		// Local file path
		if ( file_exists( $source_url ) ) {
			return $source_url;
		}

		$url = esc_url_raw( $source_url );
		if ( empty( $url ) ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 60,
				'sslverify'  => true,
				'user-agent' => 'DIP-Importer/' . DIP_VERSION . '; ' . get_bloginfo( 'url' ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return null;
		}

		$tmp = wp_tempnam( 'dip_feed_' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp, $body );

		return $tmp;
	}

	/**
	 * Detect feed type. Returns 'csv' or 'xml'.
	 */
	public static function detect_type( string $source_url, string $override = '' ): string {
		if ( $override ) {
			return strtolower( $override );
		}
		$ext = strtolower( pathinfo( (string) strtok( $source_url, '?' ), PATHINFO_EXTENSION ) );
		return 'csv' === $ext ? 'csv' : 'xml';
	}

	/**
	 * Clean up a temp feed file created by fetch().
	 */
	public static function cleanup( string $file_path ): void {
		if ( str_contains( basename( $file_path ), 'dip_feed_' ) && file_exists( $file_path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $file_path );
		}
	}
}
