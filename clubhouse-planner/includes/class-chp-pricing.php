<?php
defined( 'ABSPATH' ) || exit;

/**
 * Price of a booking of a free-time item (one where the customer chooses start and end).
 *
 * Rules:
 * - A booking of 24 hours or less counts as one day, also when it crosses midnight.
 * - Longer bookings are split into consecutive 24 hour blocks (rounded up); each block is priced by the day it starts on.
 * - Per booking: the start day decides; a booking longer than 24 hours gets the weekend price if it touches any weekend day.
 * - Per day: each 24 hour block, per hour: each started hour, priced by the day it starts on.
 * - Without a weekend price the weekday price applies every day.
 * Items that use fixed slots keep their price per slot and are not priced here.
 */
class CHP_Pricing {

	const FEE_BOOKING = 'booking';
	const FEE_HOUR    = 'hour';
	const FEE_DAY     = 'day';

	/** Upper limit on blocks counted, so a very long booking cannot make the calculation slow. */
	const MAX_STEPS = 10000;

	public static function fee_units(): array {
		return array(
			self::FEE_BOOKING => __( 'Per booking', 'clubhouse-planner' ),
			self::FEE_HOUR    => __( 'Per hour', 'clubhouse-planner' ),
			self::FEE_DAY     => __( 'Per day', 'clubhouse-planner' ),
		);
	}

	/** True for items whose price is calculated from start and end. */
	public static function is_priced( ?object $item ): bool {
		return $item && CHP_Items::MODE_FREE === $item->booking_mode;
	}

	/** @return int[] ISO weekdays (1 = Monday ... 7 = Sunday) that count as weekend for the item. */
	public static function weekend_days( object $item ): array {
		$days = array_map( 'intval', array_filter( explode( ',', (string) $item->weekend_days ), 'strlen' ) );
		return array_values( array_filter( $days, static fn( int $d ): bool => $d >= 1 && $d <= 7 ) );
	}

	public static function is_weekend( object $item, DateTimeImmutable $day ): bool {
		$weekend = in_array( (int) $day->format( 'N' ), self::weekend_days( $item ), true );
		/**
		 * Filter whether a day counts as weekend, e.g. to include public holidays.
		 *
		 * @param bool              $weekend
		 * @param DateTimeImmutable $day
		 * @param object            $item
		 */
		return (bool) apply_filters( 'chp_weekend_day', $weekend, $day, $item );
	}

	/** Price (SEK) that applies on a given day. */
	public static function rate( object $item, DateTimeImmutable $day ): int {
		$weekday = (int) $item->fee_weekday;
		if ( null === $item->fee_weekend ) {
			return $weekday;
		}
		return self::is_weekend( $item, $day ) ? (int) $item->fee_weekend : $weekday;
	}

	/** Total price in SEK for the period. */
	public static function calculate( object $item, DateTimeImmutable $start, DateTimeImmutable $end ): int {
		$seconds = $end->getTimestamp() - $start->getTimestamp();
		if ( $seconds <= 0 ) {
			return 0;
		}

		switch ( $item->fee_unit ) {
			case self::FEE_HOUR:
				$steps = min( self::MAX_STEPS, (int) ceil( $seconds / HOUR_IN_SECONDS ) );
				$total = 0;
				for ( $i = 0; $i < $steps; $i++ ) {
					$total += self::rate( $item, $start->modify( '+' . $i . ' hours' ) );
				}
				return $total;

			case self::FEE_DAY:
				// Blocks are counted on the clock, so a day with a clock change is still one day.
				$total = 0;
				for ( $i = 0; $i < self::MAX_STEPS; $i++ ) {
					$block = $start->modify( '+' . $i . ' days' );
					if ( $block >= $end ) {
						break;
					}
					$total += self::rate( $item, $block );
				}
				return $total;

			default:
				if ( $end <= $start->modify( '+1 day' ) ) {
					return self::rate( $item, $start );
				}
				// Longer than a day: the weekend price applies if any day touched is a weekend day.
				$day  = $start->setTime( 0, 0 );
				$last = $end->modify( '-1 second' )->setTime( 0, 0 );
				for ( $i = 0; $day <= $last && $i < self::MAX_STEPS; $i++ ) {
					if ( self::is_weekend( $item, $day ) ) {
						return null === $item->fee_weekend ? (int) $item->fee_weekday : (int) $item->fee_weekend;
					}
					$day = $day->modify( '+1 day' );
				}
				return (int) $item->fee_weekday;
		}
	}

	/** Price of an existing booking of a priced item, or null when its item is not priced. */
	public static function for_booking( object $booking ): ?int {
		$item = CHP_Items::get( (int) $booking->item_id );
		if ( ! self::is_priced( $item ) ) {
			return null;
		}
		$tz = wp_timezone();
		return self::calculate( $item, new DateTimeImmutable( $booking->start_at, $tz ), new DateTimeImmutable( $booking->end_at, $tz ) );
	}
}
