<?php
defined( 'ABSPATH' ) || exit;

/**
 * Maps raw feed record fields to WooCommerce product fields.
 *
 * Mapping config format (stored as JSON in dip_feeds.mapping):
 * {
 *   "name":          { "source": "title",        "default": "" },
 *   "sku":           { "source": "id",            "default": "" },
 *   "regular_price": { "source": "price",         "default": "" },
 *   "categories":    { "source": "category",      "default": "" },
 *   "image":         { "source": "img_url",       "default": "" }
 * }
 */
class DIP_Field_Mapper {

	/**
	 * All WooCommerce product target fields, keyed by internal ID.
	 *
	 * @return array<string,string>  key => translated label
	 */
	public static function wc_target_fields(): array {
		return [
			// Core
			'name'               => __( 'Product Name', 'dip' ),
			'sku'                => __( 'SKU', 'dip' ),
			'description'        => __( 'Description', 'dip' ),
			'short_description'  => __( 'Short Description', 'dip' ),
			'regular_price'      => __( 'Regular Price', 'dip' ),
			'sale_price'         => __( 'Sale Price', 'dip' ),
			'stock_quantity'     => __( 'Stock Quantity', 'dip' ),
			'stock_status'       => __( 'Stock Status (instock/outofstock)', 'dip' ),
			'weight'             => __( 'Weight', 'dip' ),
			'length'             => __( 'Length', 'dip' ),
			'width'              => __( 'Width', 'dip' ),
			'height'             => __( 'Height', 'dip' ),
			'status'             => __( 'Product Status (publish/draft)', 'dip' ),
			'catalog_visibility' => __( 'Catalog Visibility', 'dip' ),
			'product_type'       => __( 'Product Type (simple/variable/external)', 'dip' ),
			// Affiliate
			'external_url'       => __( 'External / Affiliate URL', 'dip' ),
			'button_text'        => __( 'Button Text (Affiliate)', 'dip' ),
			// Taxonomy
			'categories'         => __( 'Categories (path, pipe-separated)', 'dip' ),
			'tags'               => __( 'Tags (comma-separated)', 'dip' ),
			'attributes'         => __( 'Attributes (Name:Val1,Val2|Name2:Val)', 'dip' ),
			// Media
			'image'              => __( 'Main Image URL', 'dip' ),
			'gallery_images'     => __( 'Gallery Image URLs (pipe-separated)', 'dip' ),
			// Matching helpers
			'meta_ean'           => __( 'EAN / GTIN', 'dip' ),
			'meta_brand'         => __( 'Brand', 'dip' ),
			'custom_id'          => __( 'Custom Unique ID (for matching)', 'dip' ),
		];
	}

	/**
	 * Extract a field value from a raw record using a dot-notation path.
	 *
	 * @param array<string,mixed> $record
	 */
	public static function extract( array $record, string $source_path ): mixed {
		if ( ! str_contains( $source_path, '.' ) ) {
			return $record[ $source_path ] ?? null;
		}

		$parts = explode( '.', $source_path );
		$value = $record;
		foreach ( $parts as $part ) {
			if ( is_array( $value ) && array_key_exists( $part, $value ) ) {
				$value = $value[ $part ];
			} else {
				return null;
			}
		}
		return $value;
	}

	/**
	 * Apply a saved mapping config to a raw record.
	 * Returns a normalised product data array ready for the processor.
	 *
	 * @param array<string,mixed> $record
	 * @param array<string,mixed> $mapping  [ target_field => [ 'source' => '...', 'default' => '...' ], ... ]
	 * @return array<string,mixed>
	 */
	public static function map( array $record, array $mapping ): array {
		$product_data = [];
		foreach ( $mapping as $target => $cfg ) {
			$source  = $cfg['source']  ?? '';
			$default = $cfg['default'] ?? '';

			$value = ( '' !== $source ) ? self::extract( $record, $source ) : null;

			if ( null === $value || '' === $value ) {
				$value = $default;
			}

			$product_data[ $target ] = $value;
		}
		return $product_data;
	}

	/**
	 * Apply a template expression to a record.
	 * Supports simple {{ field }} placeholder substitution.
	 *
	 * @param array<string,mixed> $record
	 */
	public static function apply_template( string $template, array $record ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([^}]+)\s*\}\}/',
			static function ( array $matches ) use ( $record ): string {
				$path  = trim( $matches[1] );
				$value = self::extract( $record, $path );
				return is_scalar( $value ) ? (string) $value : '';
			},
			$template
		);
	}
}
