<?php

declare( strict_types=1 );

namespace DIP\Tests\Unit;

use DIP_Price_Rules;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( DIP_Price_Rules::class )]
class PriceRulesTest extends TestCase {

	// ── percent_markup ───────────────────────────────────────────────────────

	public function test_percent_markup_increases_price(): void {
		$result = DIP_Price_Rules::apply( '100.00', [ [ 'type' => 'percent_markup', 'value' => 20 ] ] );
		$this->assertSame( '120.00', $result );
	}

	public function test_percent_markup_zero_leaves_price_unchanged(): void {
		$result = DIP_Price_Rules::apply( '50.00', [ [ 'type' => 'percent_markup', 'value' => 0 ] ] );
		$this->assertSame( '50.00', $result );
	}

	// ── percent_discount ─────────────────────────────────────────────────────

	public function test_percent_discount_decreases_price(): void {
		$result = DIP_Price_Rules::apply( '100.00', [ [ 'type' => 'percent_discount', 'value' => 10 ] ] );
		$this->assertSame( '90.00', $result );
	}

	public function test_percent_discount_100_results_in_zero(): void {
		$result = DIP_Price_Rules::apply( '100.00', [ [ 'type' => 'percent_discount', 'value' => 100 ] ] );
		$this->assertSame( '0.00', $result );
	}

	// ── fixed_add ────────────────────────────────────────────────────────────

	public function test_fixed_add_increases_price(): void {
		$result = DIP_Price_Rules::apply( '10.00', [ [ 'type' => 'fixed_add', 'value' => 5.5 ] ] );
		$this->assertSame( '15.50', $result );
	}

	// ── fixed_subtract ───────────────────────────────────────────────────────

	public function test_fixed_subtract_decreases_price(): void {
		$result = DIP_Price_Rules::apply( '20.00', [ [ 'type' => 'fixed_subtract', 'value' => 3 ] ] );
		$this->assertSame( '17.00', $result );
	}

	public function test_fixed_subtract_cannot_go_below_zero(): void {
		$result = DIP_Price_Rules::apply( '2.00', [ [ 'type' => 'fixed_subtract', 'value' => 10 ] ] );
		$this->assertSame( '0.00', $result );
	}

	// ── set_fixed ────────────────────────────────────────────────────────────

	public function test_set_fixed_overrides_price(): void {
		$result = DIP_Price_Rules::apply( '999.99', [ [ 'type' => 'set_fixed', 'value' => 49.99 ] ] );
		$this->assertSame( '49.99', $result );
	}

	// ── round ────────────────────────────────────────────────────────────────

	public function test_round_to_two_decimals(): void {
		$result = DIP_Price_Rules::apply( '10.555', [ [ 'type' => 'round', 'precision' => 2 ] ] );
		$this->assertSame( '10.56', $result );
	}

	public function test_round_to_zero_decimals(): void {
		$result = DIP_Price_Rules::apply( '10.4', [ [ 'type' => 'round', 'precision' => 0 ] ] );
		$this->assertSame( '10.00', $result );
	}

	// ── round_up_to ──────────────────────────────────────────────────────────

	public function test_round_up_to_99_cents(): void {
		$result = DIP_Price_Rules::apply( '10.20', [ [ 'type' => 'round_up_to', 'value' => 0.99 ] ] );
		$this->assertSame( '10.99', $result );
	}

	public function test_round_up_to_99_when_already_above(): void {
		// 10.99 is equal to target X.99, so it stays 10.99
		$result = DIP_Price_Rules::apply( '10.99', [ [ 'type' => 'round_up_to', 'value' => 0.99 ] ] );
		$this->assertSame( '10.99', $result );
	}

	// ── min_price ────────────────────────────────────────────────────────────

	public function test_min_price_lifts_cheap_price(): void {
		$result = DIP_Price_Rules::apply( '0.50', [ [ 'type' => 'min_price', 'value' => 1.00 ] ] );
		$this->assertSame( '1.00', $result );
	}

	public function test_min_price_keeps_price_when_already_above_minimum(): void {
		$result = DIP_Price_Rules::apply( '5.00', [ [ 'type' => 'min_price', 'value' => 1.00 ] ] );
		$this->assertSame( '5.00', $result );
	}

	// ── chained rules ────────────────────────────────────────────────────────

	public function test_chained_markup_then_round(): void {
		// 100 * 1.20 = 120.0
		$result = DIP_Price_Rules::apply(
			'100.00',
			[
				[ 'type' => 'percent_markup', 'value' => 20 ],
				[ 'type' => 'round', 'precision' => 2 ],
			]
		);
		$this->assertSame( '120.00', $result );
	}

	public function test_chained_markup_then_round_up_to(): void {
		// 10 * 1.15 = 11.50 → round_up_to 0.99 → 11.99
		$result = DIP_Price_Rules::apply(
			'10.00',
			[
				[ 'type' => 'percent_markup', 'value' => 15 ],
				[ 'type' => 'round_up_to', 'value' => 0.99 ],
			]
		);
		$this->assertSame( '11.99', $result );
	}

	// ── input edge cases ─────────────────────────────────────────────────────

	public function test_accepts_comma_decimal_separator(): void {
		$result = DIP_Price_Rules::apply( '10,50', [ [ 'type' => 'fixed_add', 'value' => 1 ] ] );
		$this->assertSame( '11.50', $result );
	}

	public function test_empty_rules_returns_original_price(): void {
		$result = DIP_Price_Rules::apply( '42.00', [] );
		$this->assertSame( '42.00', $result );
	}

	public function test_unknown_rule_type_is_ignored(): void {
		$result = DIP_Price_Rules::apply( '10.00', [ [ 'type' => 'nonsense_type', 'value' => 99 ] ] );
		$this->assertSame( '10.00', $result );
	}

	// ── rule_types listing ───────────────────────────────────────────────────

	public function test_rule_types_returns_all_known_types(): void {
		$types = DIP_Price_Rules::rule_types();
		$expected_keys = [
			'percent_markup',
			'percent_discount',
			'fixed_add',
			'fixed_subtract',
			'set_fixed',
			'round',
			'round_up_to',
			'min_price',
		];
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $types, "Missing rule type: {$key}" );
		}
	}
}
