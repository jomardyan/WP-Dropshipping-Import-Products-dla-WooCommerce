<?php
defined( 'ABSPATH' ) || exit;

/**
 * Applies configurable price modification rules to a raw price.
 *
 * Rule format (each element of the rules array):
 *   [ 'type' => 'percent_markup',  'value' => 20 ]
 *   [ 'type' => 'percent_discount','value' => 10 ]
 *   [ 'type' => 'fixed_add',       'value' => 5.00 ]
 *   [ 'type' => 'fixed_subtract',  'value' => 2.00 ]
 *   [ 'type' => 'set_fixed',       'value' => 49.99 ]
 *   [ 'type' => 'round',           'precision' => 2 ]
 *   [ 'type' => 'round_up_to',     'value' => 0.99 ]   // e.g. ceil to X.99
 *   [ 'type' => 'min_price',       'value' => 1.00 ]
 */
class DIP_Price_Rules {

	/**
	 * Apply a list of rules to a price value and return a formatted price string.
	 *
	 * @param string|float|int        $price
	 * @param list<array<string,mixed>> $rules
	 */
	public static function apply( $price, array $rules ): string {
		$price = (float) str_replace( ',', '.', (string) $price );

		foreach ( $rules as $rule ) {
			$type  = $rule['type']  ?? '';
			$value = (float) ( $rule['value'] ?? 0 );

			switch ( $type ) {
				case 'percent_markup':
					$price = $price * ( 1 + $value / 100 );
					break;

				case 'percent_discount':
					$price = $price * ( 1 - $value / 100 );
					break;

				case 'fixed_add':
					$price += $value;
					break;

				case 'fixed_subtract':
					$price -= $value;
					break;

				case 'set_fixed':
					$price = $value;
					break;

				case 'round':
					$precision = (int) ( $rule['precision'] ?? 2 );
					$price     = round( $price, $precision );
					break;

				case 'round_up_to':
					// Rounds up so the fractional part equals $value (e.g. X.99)
					$base = floor( $price );
					$frac = $price - $base;
					$price = ( $frac <= $value ) ? $base + $value : $base + 1 + $value;
					break;

				case 'min_price':
					$price = max( $price, $value );
					break;
			}
		}

		if ( $price < 0 ) {
			$price = 0.0;
		}

		return number_format( $price, 2, '.', '' );
	}

	/**
	 * Available rule types with labels.
	 *
	 * @return array<string,string>
	 */
	public static function rule_types(): array {
		return [
			'percent_markup'   => __( 'Add % markup', 'dip' ),
			'percent_discount' => __( 'Subtract % discount', 'dip' ),
			'fixed_add'        => __( 'Add fixed amount', 'dip' ),
			'fixed_subtract'   => __( 'Subtract fixed amount', 'dip' ),
			'set_fixed'        => __( 'Set fixed price', 'dip' ),
			'round'            => __( 'Round to decimal places', 'dip' ),
			'round_up_to'      => __( 'Round up to ending (e.g. .99)', 'dip' ),
			'min_price'        => __( 'Minimum price', 'dip' ),
		];
	}
}
