<?php
defined( 'ABSPATH' ) || exit;

/**
 * Finds an existing WooCommerce product ID by various matching strategies:
 * SKU, EAN/GTIN meta, product name, or a custom meta key.
 */
class DIP_Matcher {

	/**
	 * Find an existing product ID for the given product data.
	 *
	 * @param array<string,mixed> $product_data  Normalised product data.
	 * @param array<string,mixed> $match_config  [ 'method' => 'sku|ean|name|custom', 'meta_key' => '...' ]
	 * @return int  Product ID, or 0 if not found.
	 */
	public static function find( array $product_data, array $match_config ): int {
		$method = $match_config['method'] ?? 'sku';

		return match ( $method ) {
			'sku'    => self::by_sku( (string) ( $product_data['sku']      ?? '' ) ),
			'ean'    => self::by_meta( '_dip_ean',       (string) ( $product_data['meta_ean']  ?? '' ) ),
			'name'   => self::by_name( (string) ( $product_data['name']    ?? '' ) ),
			'custom' => self::by_meta(
				sanitize_key( $match_config['meta_key'] ?? '_dip_custom_id' ),
				(string) ( $product_data['custom_id'] ?? '' )
			),
			default  => 0,
		};
	}

	// ── Private lookup methods ────────────────────────────────────────────────

	private static function by_sku( string $sku ): int {
		if ( '' === $sku ) {
			return 0;
		}
		return absint( wc_get_product_id_by_sku( $sku ) );
	}

	private static function by_meta( string $meta_key, string $value ): int {
		if ( '' === $value || '' === $meta_key ) {
			return 0;
		}
		global $wpdb;
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				 WHERE pm.meta_key = %s
				   AND pm.meta_value = %s
				   AND p.post_type = 'product'
				   AND p.post_status != 'trash'
				 LIMIT 1",
				$meta_key,
				$value
			)
		);
		return $product_id ? absint( $product_id ) : 0;
	}

	private static function by_name( string $name ): int {
		if ( '' === $name ) {
			return 0;
		}
		global $wpdb;
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_title = %s
				   AND post_type = 'product'
				   AND post_status != 'trash'
				 LIMIT 1",
				$name
			)
		);
		return $product_id ? absint( $product_id ) : 0;
	}
}
