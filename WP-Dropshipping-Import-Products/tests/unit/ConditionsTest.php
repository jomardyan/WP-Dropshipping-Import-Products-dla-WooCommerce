<?php

declare( strict_types=1 );

namespace DIP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for conditional logic in DIP_Product_Processor::passes_conditions().
 *
 * Since passes_conditions() is a private static method, we access it via
 * ReflectionMethod — a standard PHP testing pattern for private business logic.
 */
class ConditionsTest extends TestCase {

	/** Call the private static passes_conditions() via reflection. */
	private function passes( array $product_data, array $conditions ): bool {
		require_once DIP_DIR . 'includes/processor/class-dip-product-processor.php';

		$method = new \ReflectionMethod( 'DIP_Product_Processor', 'passes_conditions' );
		return (bool) $method->invoke( null, $product_data, $conditions );
	}

	// ── Empty conditions ─────────────────────────────────────────────────────

	public function test_empty_conditions_always_pass(): void {
		$this->assertTrue( $this->passes( [ 'stock' => '0' ], [] ) );
	}

	// ── Equality operators ───────────────────────────────────────────────────

	public function test_equals_operator_passes_when_equal(): void {
		$this->assertTrue( $this->passes(
			[ 'status' => 'active' ],
			[ [ 'field' => 'status', 'operator' => '==', 'value' => 'active' ] ]
		) );
	}

	public function test_equals_operator_fails_when_not_equal(): void {
		$this->assertFalse( $this->passes(
			[ 'status' => 'inactive' ],
			[ [ 'field' => 'status', 'operator' => '==', 'value' => 'active' ] ]
		) );
	}

	public function test_not_equals_operator_passes_when_different(): void {
		$this->assertTrue( $this->passes(
			[ 'status' => 'inactive' ],
			[ [ 'field' => 'status', 'operator' => '!=', 'value' => 'active' ] ]
		) );
	}

	public function test_not_equals_operator_fails_when_same(): void {
		$this->assertFalse( $this->passes(
			[ 'status' => 'active' ],
			[ [ 'field' => 'status', 'operator' => '!=', 'value' => 'active' ] ]
		) );
	}

	// ── Numeric comparison operators ─────────────────────────────────────────

	public function test_greater_than_passes(): void {
		$this->assertTrue( $this->passes(
			[ 'stock' => '5' ],
			[ [ 'field' => 'stock', 'operator' => '>', 'value' => '0' ] ]
		) );
	}

	public function test_greater_than_fails_when_equal(): void {
		$this->assertFalse( $this->passes(
			[ 'stock' => '0' ],
			[ [ 'field' => 'stock', 'operator' => '>', 'value' => '0' ] ]
		) );
	}

	public function test_less_than_passes(): void {
		$this->assertTrue( $this->passes(
			[ 'price' => '5.99' ],
			[ [ 'field' => 'price', 'operator' => '<', 'value' => '10' ] ]
		) );
	}

	public function test_less_than_fails_when_equal(): void {
		$this->assertFalse( $this->passes(
			[ 'price' => '10' ],
			[ [ 'field' => 'price', 'operator' => '<', 'value' => '10' ] ]
		) );
	}

	public function test_greater_or_equal_passes_when_equal(): void {
		$this->assertTrue( $this->passes(
			[ 'qty' => '10' ],
			[ [ 'field' => 'qty', 'operator' => '>=', 'value' => '10' ] ]
		) );
	}

	public function test_greater_or_equal_passes_when_greater(): void {
		$this->assertTrue( $this->passes(
			[ 'qty' => '11' ],
			[ [ 'field' => 'qty', 'operator' => '>=', 'value' => '10' ] ]
		) );
	}

	public function test_greater_or_equal_fails_when_less(): void {
		$this->assertFalse( $this->passes(
			[ 'qty' => '9' ],
			[ [ 'field' => 'qty', 'operator' => '>=', 'value' => '10' ] ]
		) );
	}

	public function test_less_or_equal_passes_when_equal(): void {
		$this->assertTrue( $this->passes(
			[ 'qty' => '5' ],
			[ [ 'field' => 'qty', 'operator' => '<=', 'value' => '5' ] ]
		) );
	}

	public function test_less_or_equal_fails_when_greater(): void {
		$this->assertFalse( $this->passes(
			[ 'qty' => '6' ],
			[ [ 'field' => 'qty', 'operator' => '<=', 'value' => '5' ] ]
		) );
	}

	// ── String operators ─────────────────────────────────────────────────────

	public function test_contains_passes_when_substring_present(): void {
		$this->assertTrue( $this->passes(
			[ 'name' => 'Red T-Shirt' ],
			[ [ 'field' => 'name', 'operator' => 'contains', 'value' => 'T-Shirt' ] ]
		) );
	}

	public function test_contains_fails_when_substring_absent(): void {
		$this->assertFalse( $this->passes(
			[ 'name' => 'Blue Jeans' ],
			[ [ 'field' => 'name', 'operator' => 'contains', 'value' => 'T-Shirt' ] ]
		) );
	}

	public function test_not_contains_passes_when_substring_absent(): void {
		$this->assertTrue( $this->passes(
			[ 'name' => 'Blue Jeans' ],
			[ [ 'field' => 'name', 'operator' => 'not_contains', 'value' => 'T-Shirt' ] ]
		) );
	}

	public function test_not_contains_fails_when_substring_present(): void {
		$this->assertFalse( $this->passes(
			[ 'name' => 'Red T-Shirt' ],
			[ [ 'field' => 'name', 'operator' => 'not_contains', 'value' => 'T-Shirt' ] ]
		) );
	}

	// ── Empty / not_empty operators ──────────────────────────────────────────

	public function test_empty_passes_for_empty_string(): void {
		$this->assertTrue( $this->passes(
			[ 'field' => '' ],
			[ [ 'field' => 'field', 'operator' => 'empty', 'value' => '' ] ]
		) );
	}

	public function test_empty_fails_for_non_empty_value(): void {
		$this->assertFalse( $this->passes(
			[ 'field' => 'value' ],
			[ [ 'field' => 'field', 'operator' => 'empty', 'value' => '' ] ]
		) );
	}

	public function test_empty_passes_for_missing_field(): void {
		// Missing field → treated as empty string.
		$this->assertTrue( $this->passes(
			[],
			[ [ 'field' => 'missing', 'operator' => 'empty', 'value' => '' ] ]
		) );
	}

	public function test_not_empty_passes_for_non_empty_value(): void {
		$this->assertTrue( $this->passes(
			[ 'price' => '9.99' ],
			[ [ 'field' => 'price', 'operator' => 'not_empty', 'value' => '' ] ]
		) );
	}

	public function test_not_empty_fails_for_empty_string(): void {
		$this->assertFalse( $this->passes(
			[ 'price' => '' ],
			[ [ 'field' => 'price', 'operator' => 'not_empty', 'value' => '' ] ]
		) );
	}

	// ── Multiple conditions (AND semantics) ──────────────────────────────────

	public function test_all_conditions_must_pass(): void {
		$conditions = [
			[ 'field' => 'stock', 'operator' => '>', 'value' => '0' ],
			[ 'field' => 'status', 'operator' => '==', 'value' => 'active' ],
		];

		// Both pass
		$this->assertTrue( $this->passes( [ 'stock' => '5', 'status' => 'active' ], $conditions ) );

		// First fails
		$this->assertFalse( $this->passes( [ 'stock' => '0', 'status' => 'active' ], $conditions ) );

		// Second fails
		$this->assertFalse( $this->passes( [ 'stock' => '5', 'status' => 'inactive' ], $conditions ) );
	}

	// ── Unknown operator ─────────────────────────────────────────────────────

	public function test_unknown_operator_defaults_to_pass(): void {
		$this->assertTrue( $this->passes(
			[ 'field' => 'value' ],
			[ [ 'field' => 'field', 'operator' => 'unknown_op', 'value' => 'x' ] ]
		) );
	}
}
