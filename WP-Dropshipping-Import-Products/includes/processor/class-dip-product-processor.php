<?php
defined( 'ABSPATH' ) || exit;

/**
 * Creates and updates WooCommerce products using WC CRUD objects.
 * Never uses update_post_meta() directly for WC product fields.
 */
class DIP_Product_Processor {

	/**
	 * Process a single normalised product data record.
	 *
	 * @param array<string,mixed> $product_data  Mapped product data from DIP_Field_Mapper.
	 * @param array<string,mixed> $settings      Feed settings.
	 * @param DIP_Logger          $logger
	 * @param int                 $record_index
	 * @return string  'created' | 'updated' | 'skipped' | 'error'
	 */
	public static function process(
		array $product_data,
		array $settings,
		DIP_Logger $logger,
		int $record_index
	): string {
		// ── Conditional logic ────────────────────────────────────────────────
		if ( ! self::passes_conditions( $product_data, $settings['conditions'] ?? [] ) ) {
			$logger->log( 'skipped', __( 'Skipped by conditional logic.', 'dip' ), $record_index );
			return 'skipped';
		}

		// ── Apply price rules ────────────────────────────────────────────────
		if ( ! empty( $settings['price_rules'] ) ) {
			if ( isset( $product_data['regular_price'] ) && '' !== $product_data['regular_price'] ) {
				$product_data['regular_price'] = DIP_Price_Rules::apply(
					$product_data['regular_price'],
					$settings['price_rules']
				);
			}
			if ( isset( $product_data['sale_price'] ) && '' !== $product_data['sale_price'] ) {
				$product_data['sale_price'] = DIP_Price_Rules::apply(
					$product_data['sale_price'],
					$settings['price_rules']
				);
			}
		}

		// ── Match existing product ───────────────────────────────────────────
		$match_config = $settings['match'] ?? [ 'method' => 'sku' ];
		$product_id   = DIP_Matcher::find( $product_data, $match_config );
		$is_update    = $product_id > 0;
		$product_type = sanitize_key( $product_data['product_type'] ?? 'simple' );

		try {
			if ( $is_update ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					throw new \RuntimeException(
						/* translators: %d: product ID */
						sprintf( __( 'Could not load product ID %d.', 'dip' ), $product_id )
					);
				}
				self::update_product( $product, $product_data, $settings );
				$logger->log(
					'updated',
					/* translators: %d: product ID */
					sprintf( __( 'Updated product ID %d.', 'dip' ), $product_id ),
					$record_index,
					$product_id
				);
				return 'updated';
			}

			$create_as_draft = ! empty( $settings['create_as_draft'] );
			$product_id      = self::create_product( $product_type, $product_data, $settings, $create_as_draft );
			$logger->log(
				'created',
				/* translators: %d: product ID */
				sprintf( __( 'Created product ID %d.', 'dip' ), $product_id ),
				$record_index,
				$product_id
			);
			return 'created';

		} catch ( \Throwable $e ) {
			$logger->log( 'error', $e->getMessage(), $record_index );
			return 'error';
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/** @param array<string,mixed> $product_data */
	private static function create_product(
		string $product_type,
		array $product_data,
		array $settings,
		bool $draft
	): int {
		$product = self::make_product_object( $product_type );
		self::apply_data( $product, $product_data, $settings, true );
		if ( $draft ) {
			$product->set_status( 'draft' );
		}
		$product->save();
		return $product->get_id();
	}

	/** @param array<string,mixed> $product_data */
	private static function update_product( \WC_Product $product, array $product_data, array $settings ): void {
		self::apply_data( $product, $product_data, $settings, false );
		$product->save();
	}

	private static function make_product_object( string $product_type ): \WC_Product {
		return match ( $product_type ) {
			'variable' => new \WC_Product_Variable(),
			'external' => new \WC_Product_External(),
			default    => new \WC_Product_Simple(),
		};
	}

	/**
	 * Apply normalised product data to a WC_Product object.
	 *
	 * @param array<string,mixed> $product_data
	 * @param array<string,mixed> $settings
	 */
	private static function apply_data(
		\WC_Product $product,
		array $product_data,
		array $settings,
		bool $is_new
	): void {
		// Selective field update: null = update all; array = update only listed fields
		$update_fields = $settings['update_fields'] ?? null;
		$can_update    = static function ( string $field ) use ( $update_fields, $is_new ): bool {
			if ( $is_new ) {
				return true;
			}
			if ( null === $update_fields ) {
				return true;
			}
			return in_array( $field, (array) $update_fields, true );
		};

		// ── Scalar fields ────────────────────────────────────────────────────
		$scalar_map = [
			'name'               => 'set_name',
			'description'        => 'set_description',
			'short_description'  => 'set_short_description',
			'regular_price'      => 'set_regular_price',
			'sale_price'         => 'set_sale_price',
			'weight'             => 'set_weight',
			'length'             => 'set_length',
			'width'              => 'set_width',
			'height'             => 'set_height',
			'catalog_visibility' => 'set_catalog_visibility',
			'status'             => 'set_status',
		];

		foreach ( $scalar_map as $field => $setter ) {
			if ( $can_update( $field ) && isset( $product_data[ $field ] ) && '' !== (string) $product_data[ $field ] ) {
				$product->$setter( $product_data[ $field ] );
			}
		}

		// SKU — separate handling to sanitise
		if ( $can_update( 'sku' ) && ! empty( $product_data['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $product_data['sku'] ) );
		}

		// ── Stock ────────────────────────────────────────────────────────────
		if ( $can_update( 'stock_quantity' ) && isset( $product_data['stock_quantity'] ) && '' !== (string) $product_data['stock_quantity'] ) {
			$qty = (int) $product_data['stock_quantity'];
			if ( -1 === $qty ) {
				// IOF sentinel: -1 means unlimited / not tracked by this warehouse.
				$product->set_manage_stock( false );
				$product->set_stock_status( 'instock' );
			} else {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( max( 0, $qty ) );
			}
		}
		if ( $can_update( 'stock_status' ) && ! empty( $product_data['stock_status'] ) ) {
			$product->set_stock_status( sanitize_key( $product_data['stock_status'] ) );
		}

		// ── Affiliate fields ─────────────────────────────────────────────────
		if ( $product instanceof \WC_Product_External ) {
			if ( $can_update( 'external_url' ) && ! empty( $product_data['external_url'] ) ) {
				$product->set_product_url( esc_url_raw( $product_data['external_url'] ) );
			}
			if ( $can_update( 'button_text' ) && ! empty( $product_data['button_text'] ) ) {
				$product->set_button_text( sanitize_text_field( $product_data['button_text'] ) );
			}
		}

		// ── Categories ───────────────────────────────────────────────────────
		if ( $can_update( 'categories' ) && ! empty( $product_data['categories'] ) ) {
			$term_ids = DIP_Category_Handler::process( $product_data['categories'] );
			if ( ! empty( $term_ids ) ) {
				$product->set_category_ids( $term_ids );
			}
		}

		// ── Tags ─────────────────────────────────────────────────────────────
		if ( $can_update( 'tags' ) && ! empty( $product_data['tags'] ) ) {
			$tag_names = is_string( $product_data['tags'] )
				? array_filter( array_map( 'trim', explode( ',', $product_data['tags'] ) ) )
				: (array) $product_data['tags'];

			$tag_ids = [];
			foreach ( $tag_names as $tag_name ) {
				$term = get_term_by( 'name', $tag_name, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$tag_ids[] = (int) $term->term_id;
				} else {
					$result = wp_insert_term( $tag_name, 'product_tag' );
					if ( ! is_wp_error( $result ) ) {
						$tag_ids[] = (int) $result['term_id'];
					}
				}
			}
			$product->set_tag_ids( $tag_ids );
		}

		// ── Attributes ───────────────────────────────────────────────────────
		if ( $can_update( 'attributes' ) && ! empty( $product_data['attributes'] ) ) {
			self::apply_attributes( $product, $product_data['attributes'] );
		}

		// ── Images ───────────────────────────────────────────────────────────
		if ( $can_update( 'image' ) && ! empty( $product_data['image'] ) ) {
			$img_id = DIP_Image_Handler::import_from_url( (string) $product_data['image'] );
			if ( $img_id > 0 ) {
				$product->set_image_id( $img_id );
			}
		}
		if ( $can_update( 'gallery_images' ) && ! empty( $product_data['gallery_images'] ) ) {
			$gallery_ids = DIP_Image_Handler::process_gallery( $product_data['gallery_images'] );
			$product->set_gallery_image_ids( $gallery_ids );
		}

		// ── Meta: EAN ────────────────────────────────────────────────────────
		if ( ! empty( $product_data['meta_ean'] ) ) {
			$product->update_meta_data( '_dip_ean', sanitize_text_field( $product_data['meta_ean'] ) );
		}
		if ( ! empty( $product_data['meta_brand'] ) ) {
			$product->update_meta_data( '_dip_brand', sanitize_text_field( $product_data['meta_brand'] ) );
		}
		if ( ! empty( $product_data['custom_id'] ) ) {
			$product->update_meta_data( '_dip_custom_id', sanitize_text_field( $product_data['custom_id'] ) );
		}

		// ── SRP / compare-at price ───────────────────────────────────────────
		if ( $can_update( 'srp_price' ) && isset( $product_data['srp_price'] ) && '' !== (string) $product_data['srp_price'] ) {
			$product->update_meta_data( '_dip_srp_price', wc_format_decimal( $product_data['srp_price'] ) );
		}

		// ── Custom meta fields ───────────────────────────────────────────────
		if ( ! empty( $product_data['custom_meta'] ) && is_array( $product_data['custom_meta'] ) ) {
			foreach ( $product_data['custom_meta'] as $meta_key => $meta_value ) {
				$product->update_meta_data( sanitize_key( $meta_key ), $meta_value );
			}
		}
	}

	/**
	 * Parse and apply attributes to a product.
	 * Format: "Color:Red,Blue|Size:S,M,L"
	 *
	 * @param string|list<string>|array<string,mixed> $attributes_value
	 */
	private static function apply_attributes( \WC_Product $product, $attributes_value ): void {
		if ( is_string( $attributes_value ) ) {
			$attribute_strings = explode( '|', $attributes_value );
		} else {
			$attribute_strings = (array) $attributes_value;
		}

		$wc_attributes = [];
		foreach ( $attribute_strings as $attr_string ) {
			$attr_string = trim( (string) $attr_string );
			if ( str_contains( $attr_string, ':' ) ) {
				[ $name, $values_str ] = explode( ':', $attr_string, 2 );
				$values = array_map( 'trim', explode( ',', $values_str ) );
			} else {
				$name   = $attr_string;
				$values = [];
			}

			$name = trim( $name );
			if ( empty( $name ) ) {
				continue;
			}

			$attribute = new \WC_Product_Attribute();
			$attribute->set_name( $name );
			$attribute->set_options( $values );
			$attribute->set_visible( true );
			$attribute->set_variation( false );
			$wc_attributes[] = $attribute;
		}

		if ( ! empty( $wc_attributes ) ) {
			$product->set_attributes( $wc_attributes );
		}
	}

	/**
	 * Evaluate ALL conditions — returns false on the first failing condition.
	 *
	 * Condition format: [ 'field' => '...', 'operator' => '==|!=|>|<|>=|<=|contains|not_contains|empty|not_empty', 'value' => '...' ]
	 *
	 * @param array<string,mixed>            $product_data
	 * @param list<array<string,mixed>>      $conditions
	 */
	private static function passes_conditions( array $product_data, array $conditions ): bool {
		foreach ( $conditions as $cond ) {
			$field    = $cond['field']    ?? '';
			$operator = $cond['operator'] ?? '==';
			$expected = (string) ( $cond['value'] ?? '' );
			$actual   = (string) ( $product_data[ $field ] ?? '' );

			$passes = match ( $operator ) {
				'=='           => $actual === $expected,
				'!='           => $actual !== $expected,
				'>'            => (float) $actual >  (float) $expected,
				'<'            => (float) $actual <  (float) $expected,
				'>='           => (float) $actual >= (float) $expected,
				'<='           => (float) $actual <= (float) $expected,
				'contains'     =>  str_contains( $actual, $expected ),
				'not_contains' => ! str_contains( $actual, $expected ),
				'empty'        => '' === $actual,
				'not_empty'    => '' !== $actual,
				default        => true,
			};

			if ( ! $passes ) {
				return false;
			}
		}
		return true;
	}
}
