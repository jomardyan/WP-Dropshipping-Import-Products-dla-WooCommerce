<?php
/**
 * Minimal WooCommerce function stubs for unit testing without WooCommerce.
 */

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( $value, int $dp = 2 ): string {
		return number_format( (float) $value, $dp, '.', '' );
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( float $price ): string {
		return '$' . number_format( $price, 2 );
	}
}

if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
	function wc_get_product_id_by_sku( string $sku ): int {
		return 0;
	}
}

if ( ! function_exists( 'wc_set_time_limit' ) ) {
	function wc_set_time_limit( int $limit ): void {
		// no-op stub
	}
}
