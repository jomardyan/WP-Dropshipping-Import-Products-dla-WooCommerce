<?php

declare( strict_types=1 );

namespace DIP\Tests\Unit;

use DIP_Field_Mapper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( DIP_Field_Mapper::class )]
class FieldMapperTest extends TestCase {

	// ── extract: simple flat record ──────────────────────────────────────────

	public function test_extract_simple_key(): void {
		$record = [ 'name' => 'Test Product', 'price' => '19.99' ];
		$this->assertSame( 'Test Product', DIP_Field_Mapper::extract( $record, 'name' ) );
	}

	public function test_extract_missing_key_returns_null(): void {
		$record = [ 'name' => 'Product' ];
		$this->assertNull( DIP_Field_Mapper::extract( $record, 'nonexistent' ) );
	}

	// ── extract: dot notation ────────────────────────────────────────────────

	public function test_extract_dot_notation_single_level(): void {
		$record = [ 'product' => [ 'sku' => 'SKU-001' ] ];
		$this->assertSame( 'SKU-001', DIP_Field_Mapper::extract( $record, 'product.sku' ) );
	}

	public function test_extract_dot_notation_two_levels(): void {
		$record = [ 'a' => [ 'b' => [ 'c' => 'deep_value' ] ] ];
		$this->assertSame( 'deep_value', DIP_Field_Mapper::extract( $record, 'a.b.c' ) );
	}

	public function test_extract_dot_notation_missing_intermediate(): void {
		$record = [ 'a' => [ 'x' => '1' ] ];
		$this->assertNull( DIP_Field_Mapper::extract( $record, 'a.b.c' ) );
	}

	// ── extract: list aggregation ────────────────────────────────────────────

	public function test_extract_aggregates_numeric_list_by_max(): void {
		// Feed with multiple stock entries — pick the highest.
		$record = [ 'stock' => [ '5', '12', '3' ] ];
		$this->assertSame( '12', DIP_Field_Mapper::extract( $record, 'stock' ) );
	}

	public function test_extract_aggregates_list_mid_path_returns_first_string(): void {
		// Multiple <item> siblings → extract the first children's 'sku' field.
		$record = [
			'items' => [
				[ 'sku' => 'A1', 'price' => '10' ],
				[ 'sku' => 'A2', 'price' => '20' ],
			],
		];
		$this->assertSame( 'A1', DIP_Field_Mapper::extract( $record, 'items.sku' ) );
	}

	public function test_extract_aggregates_numeric_list_mid_path_by_max(): void {
		$record = [
			'warehouses' => [
				[ 'qty' => '5' ],
				[ 'qty' => '20' ],
				[ 'qty' => '8' ],
			],
		];
		$this->assertSame( '20', DIP_Field_Mapper::extract( $record, 'warehouses.qty' ) );
	}

	// ── extract_list: multi-value fields ─────────────────────────────────────

	public function test_extract_list_flat_string_returns_single_element_array(): void {
		$record = [ 'image' => 'https://example.com/img.jpg' ];
		$result = DIP_Field_Mapper::extract_list( $record, 'image' );
		$this->assertSame( [ 'https://example.com/img.jpg' ], $result );
	}

	public function test_extract_list_newline_separated_string(): void {
		$record = [ 'images' => "https://a.com/1.jpg\nhttps://a.com/2.jpg\nhttps://a.com/3.jpg" ];
		$result = DIP_Field_Mapper::extract_list( $record, 'images' );
		$this->assertCount( 3, $result );
		$this->assertSame( 'https://a.com/1.jpg', $result[0] );
	}

	public function test_extract_list_array_value(): void {
		$record = [ 'imgs' => [ 'https://a.com/1.jpg', 'https://a.com/2.jpg' ] ];
		$result = DIP_Field_Mapper::extract_list( $record, 'imgs' );
		$this->assertSame( [ 'https://a.com/1.jpg', 'https://a.com/2.jpg' ], $result );
	}

	// ── map: full mapping ────────────────────────────────────────────────────

	public function test_map_applies_simple_mapping(): void {
		$record  = [ 'title' => 'Widget', 'cost' => '9.99', 'code' => 'W-001' ];
		$mapping = [
			'name'          => [ 'source' => 'title', 'default' => '' ],
			'regular_price' => [ 'source' => 'cost',  'default' => '' ],
			'sku'           => [ 'source' => 'code',  'default' => '' ],
		];

		$result = DIP_Field_Mapper::map( $record, $mapping );

		$this->assertSame( 'Widget', $result['name'] );
		$this->assertSame( '9.99', $result['regular_price'] );
		$this->assertSame( 'W-001', $result['sku'] );
	}

	public function test_map_uses_default_when_source_is_empty(): void {
		$record  = [ 'title' => '' ];
		$mapping = [
			'name' => [ 'source' => 'title', 'default' => 'Unnamed Product' ],
		];

		$result = DIP_Field_Mapper::map( $record, $mapping );
		$this->assertSame( 'Unnamed Product', $result['name'] );
	}

	public function test_map_uses_default_when_source_field_missing(): void {
		$record  = [];
		$mapping = [
			'name' => [ 'source' => 'title', 'default' => 'Default Name' ],
		];

		$result = DIP_Field_Mapper::map( $record, $mapping );
		$this->assertSame( 'Default Name', $result['name'] );
	}

	public function test_map_handles_empty_source_with_no_default(): void {
		$record  = [];
		$mapping = [
			'sku' => [ 'source' => 'id', 'default' => '' ],
		];

		$result = DIP_Field_Mapper::map( $record, $mapping );
		// When source missing and default empty, value should be empty string or null.
		$this->assertTrue( '' === ( $result['sku'] ?? '' ) || null === ( $result['sku'] ?? null ) );
	}

	// ── wc_target_fields ─────────────────────────────────────────────────────

	public function test_wc_target_fields_returns_non_empty_array(): void {
		$fields = DIP_Field_Mapper::wc_target_fields();
		$this->assertNotEmpty( $fields );
	}

	public function test_wc_target_fields_includes_core_fields(): void {
		$fields = DIP_Field_Mapper::wc_target_fields();
		$required = [ 'name', 'sku', 'regular_price', 'stock_quantity', 'categories', 'image' ];
		foreach ( $required as $key ) {
			$this->assertArrayHasKey( $key, $fields, "Missing target field: {$key}" );
		}
	}
}
