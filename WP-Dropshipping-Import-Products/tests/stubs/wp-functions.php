<?php
/**
 * Minimal WordPress function stubs for unit testing without a full WP install.
 */

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $val ): int {
		return abs( (int) $val );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

if ( ! function_exists( 'sprintf' ) ) {
	// Already a PHP core function; just for IDE completeness.
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}
