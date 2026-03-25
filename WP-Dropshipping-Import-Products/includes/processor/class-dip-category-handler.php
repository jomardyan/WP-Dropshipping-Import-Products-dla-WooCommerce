<?php
defined( 'ABSPATH' ) || exit;

/**
 * Creates and resolves WooCommerce product categories from feed paths.
 * Supports hierarchical paths like "Electronics > Phones > Smartphones".
 */
class DIP_Category_Handler {

	/** @var array<string,int> In-memory cache: "{parent_id}_{name}" => term_id */
	private static array $cache = [];

	/**
	 * Ensure a category path exists and return the leaf term ID.
	 *
	 * Supported separators (auto-detected):
	 *  - " > " or ">"  — default/configured format
	 *  - "/"           — IOF / Polish shop XML format (e.g. "Modele RC/Ładowanie/Ładowarki")
	 *
	 * Single-level categories (no separator) are also valid.
	 */
	public static function get_or_create( string $category_path ): int {
		// Auto-detect separator: '>' takes priority; fall back to '/' for IOF feeds.
		$separator = str_contains( $category_path, '>' ) ? '>' : '/';
		$parts     = array_filter( array_map( 'trim', explode( $separator, $category_path ) ) );
		$parent_id = 0;
		$term_id   = 0;

		foreach ( $parts as $name ) {
			$cache_key = "{$parent_id}_{$name}";

			if ( isset( self::$cache[ $cache_key ] ) ) {
				$term_id   = self::$cache[ $cache_key ];
				$parent_id = $term_id;
				continue;
			}

			// Search for existing term under this parent
			$term = get_term_by( 'name', $name, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && (int) $term->parent === $parent_id ) {
				$term_id = (int) $term->term_id;
			} else {
				$result = wp_insert_term( $name, 'product_cat', [ 'parent' => $parent_id ] );
				if ( is_wp_error( $result ) ) {
					// Term already exists under a different parent — retrieve it
					$existing = get_term_by( 'name', $name, 'product_cat' );
					$term_id  = $existing ? (int) $existing->term_id : 0;
				} else {
					$term_id = (int) $result['term_id'];
				}
			}

			self::$cache[ $cache_key ] = $term_id;
			$parent_id = $term_id;
		}

		return $term_id;
	}

	/**
	 * Process a categories value from mapped product data.
	 * Accepts pipe-separated paths: "Electronics > Phones | Accessories".
	 *
	 * @param string|list<string> $categories_value
	 * @return list<int>  WC term IDs
	 */
	public static function process( $categories_value ): array {
		if ( is_string( $categories_value ) ) {
			$items = array_filter( array_map( 'trim', explode( '|', $categories_value ) ) );
		} else {
			$items = (array) $categories_value;
		}

		$term_ids = [];
		foreach ( $items as $item ) {
			$tid = self::get_or_create( (string) $item );
			if ( $tid > 0 ) {
				$term_ids[] = $tid;
			}
		}

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Clear in-memory term cache. Call between import runs.
	 */
	public static function clear_cache(): void {
		self::$cache = [];
	}
}
