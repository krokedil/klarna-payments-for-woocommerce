<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

/**
 * The arithmetic Klarna validates an order body against, checked all at once so a
 * failure lists every broken rule rather than the first one.
 *
 * Tolerances follow Klarna's API reference: a line total may be off by up to its
 * quantity, a line tax by one minor unit, and the order tax by the item quantity.
 */
trait CanAssertKlarnaAmounts {

	/**
	 * Asserts a session or order body adds up and charges what WooCommerce charges.
	 *
	 * @param array $body     The decoded Klarna body.
	 * @param float $wc_total The WooCommerce total, in major units.
	 * @param float $wc_tax   The WooCommerce total tax, in major units.
	 */
	protected function assertKlarnaAmountsAreConsistent( array $body, float $wc_total, float $wc_tax ): void {
		$violations = $this->orderLineViolations( $body['order_lines'], $body['order_amount'], 'order_amount', $body['order_tax_amount'] );

		if ( $body['order_amount'] !== $this->toMinor( $wc_total ) ) {
			$violations[] = sprintf( 'R5 order_amount is %s, but WooCommerce charges %d.', var_export( $body['order_amount'], true ), $this->toMinor( $wc_total ) );
		}

		if ( $body['order_tax_amount'] !== $this->toMinor( $wc_tax ) ) {
			$violations[] = sprintf( 'R5 order_tax_amount is %s, but WooCommerce charges %d tax.', var_export( $body['order_tax_amount'], true ), $this->toMinor( $wc_tax ) );
		}

		$this->assertNoAmountViolations( $violations, $body['order_lines'] );
	}

	/**
	 * Asserts a set of lines adds up to the amount sent alongside them, as captures
	 * and refunds do. Klarna allows refund lines no slack on quantity x unit_price.
	 */
	protected function assertOrderLinesAddUpTo( array $lines, $amount, string $amount_key, bool $exact_line_totals = false ): void {
		$this->assertNoAmountViolations( $this->orderLineViolations( $lines, $amount, $amount_key, null, $exact_line_totals ), $lines );
	}

	/**
	 * Every way the lines break Klarna's arithmetic, as readable sentences.
	 *
	 * @param array    $lines      Klarna order lines.
	 * @param mixed    $amount     The amount the lines must add up to.
	 * @param string   $amount_key What the amount is called in the body.
	 * @param int|null $tax_amount The order tax amount, when the body has one.
	 * @param bool     $exact_line_totals Whether a line total must equal quantity x unit_price exactly.
	 * @return array<int, string>
	 */
	private function orderLineViolations( array $lines, $amount, string $amount_key, $tax_amount, bool $exact_line_totals = false ): array {
		$violations = [];
		$sales_tax  = in_array( 'sales_tax', array_column( $lines, 'type' ), true );

		foreach ( [ 'quantity', 'unit_price', 'tax_rate', 'total_amount', 'total_discount_amount', 'total_tax_amount' ] as $field ) {
			foreach ( $lines as $index => $line ) {
				if ( isset( $line[ $field ] ) && ! is_int( $line[ $field ] ) ) {
					$violations[] = sprintf( 'R6 line %d (%s) sends %s as %s, not an integer.', $index, $line['name'] ?? '?', $field, var_export( $line[ $field ], true ) );
				}
			}
		}

		$line_total = array_sum( array_column( $lines, 'total_amount' ) );

		if ( $amount !== $line_total ) {
			$violations[] = sprintf( 'R1 %s is %s, but the lines add up to %s.', $amount_key, var_export( $amount, true ), var_export( $line_total, true ) );
		}

		if ( null !== $tax_amount ) {
			// With US sales tax the tax travels as its own line, and every other line is tax free.
			$line_tax  = $sales_tax ? array_sum( array_column( array_filter( $lines, static fn( $l ) => 'sales_tax' === $l['type'] ), 'total_amount' ) ) : array_sum( array_column( $lines, 'total_tax_amount' ) );
			$tolerance = $sales_tax ? 0 : array_sum( array_column( $lines, 'quantity' ) );

			if ( abs( $tax_amount - $line_tax ) > $tolerance ) {
				$violations[] = sprintf( 'R2 order_tax_amount is %s, but the line taxes add up to %s (tolerance %d).', var_export( $tax_amount, true ), var_export( $line_tax, true ), $tolerance );
			}
		}

		foreach ( $lines as $index => $line ) {
			$label    = sprintf( 'line %d (%s)', $index, $line['name'] ?? '?' );
			$quantity = $line['quantity'] ?? 1;
			$expected = $quantity * ( $line['unit_price'] ?? 0 ) - ( $line['total_discount_amount'] ?? 0 );

			if ( abs( ( $line['total_amount'] ?? 0 ) - $expected ) > ( $exact_line_totals ? 0 : abs( $quantity ) ) ) {
				$violations[] = sprintf(
					'R3 %s has total_amount %s, but quantity x unit_price - discount is %d x %s - %s = %s.',
					$label,
					var_export( $line['total_amount'] ?? null, true ),
					$quantity,
					var_export( $line['unit_price'] ?? null, true ),
					var_export( $line['total_discount_amount'] ?? 0, true ),
					var_export( $expected, true )
				);
			}

			if ( $sales_tax ) {
				if ( 'sales_tax' !== $line['type'] && ( 0 !== ( $line['tax_rate'] ?? 0 ) || 0 !== ( $line['total_tax_amount'] ?? 0 ) ) ) {
					$violations[] = sprintf( 'R4 %s carries tax although sales tax is sent as its own line.', $label );
				}
				continue;
			}

			$total        = $line['total_amount'] ?? 0;
			$rate         = $line['tax_rate'] ?? 0;
			$expected_tax = $total - $total * 10000 / ( 10000 + $rate );

			if ( abs( ( $line['total_tax_amount'] ?? 0 ) - $expected_tax ) > 1 ) {
				$violations[] = sprintf(
					'R4 %s has total_tax_amount %s, but %s at tax_rate %s holds %.2f tax.',
					$label,
					var_export( $line['total_tax_amount'] ?? null, true ),
					var_export( $total, true ),
					var_export( $rate, true ),
					$expected_tax
				);
			}
		}

		return $violations;
	}

	/** Fails with every violation and the lines they came from. */
	private function assertNoAmountViolations( array $violations, array $lines ): void {
		$this->assertSame(
			[],
			$violations,
			"Klarna would reject or mis-charge this body.\nLines sent: " . wp_json_encode( $lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);
	}

	/** A major-unit WooCommerce amount in the minor units Klarna expects. */
	protected function toMinor( $amount ): int {
		return (int) round( (float) $amount * 100 );
	}
}
