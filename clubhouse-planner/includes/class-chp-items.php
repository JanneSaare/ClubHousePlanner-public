<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bookable items (the club house, a boule court, surfboards, ...).
 *
 * An item has a quantity; its units are lettered A, B, C ... and are derived from the quantity rather than stored.
 */
class CHP_Items {

	/** Hard cap so unit letters stay A–Z and AA–AZ. */
	const MAX_QUANTITY = 52;

	const MODE_SLOTS = 'slots';
	const MODE_FREE  = 'free';

	/** @var int|null Cached id of the default item. */
	private static ?int $default_id = null;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chp_items';
	}

	/** Unit letter for a zero-based index: 0 = A, 25 = Z, 26 = AA, 27 = AB ... */
	public static function unit_label( int $index ): string {
		$label = '';
		$n     = max( 0, $index ) + 1;
		while ( $n > 0 ) {
			$n--;
			$label = chr( 65 + ( $n % 26 ) ) . $label;
			$n     = intdiv( $n, 26 );
		}
		return $label;
	}

	/** @return string[] Unit letters for a quantity, e.g. 3 gives A, B, C. */
	public static function unit_labels( int $quantity ): array {
		$quantity = max( 1, min( self::MAX_QUANTITY, $quantity ) );
		$labels   = array();
		for ( $i = 0; $i < $quantity; $i++ ) {
			$labels[] = self::unit_label( $i );
		}
		return $labels;
	}

	/** Unit letters are only shown when an item has more than one unit. */
	public static function show_unit( object $item ): bool {
		return (int) $item->quantity > 1;
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * Item used where no item is specified: the first active item. Created on demand if none exists.
	 */
	public static function default_id(): int {
		global $wpdb;

		if ( null === self::$default_id ) {
			$table = self::table();
			$id    = (int) $wpdb->get_var( "SELECT id FROM $table WHERE active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1" );
			if ( ! $id ) {
				$id = self::install_default();
			}
			self::$default_id = $id;
		}
		return self::$default_id;
	}

	/**
	 * Create the club house as the first item if there are no items, and attach bookings made before items existed.
	 * Safe to run repeatedly.
	 *
	 * Slot times and prices stay in the global settings for now (item `slots` is NULL = use the global setup).
	 * The name is not translated: this can run before translations are loaded; the admin can rename it.
	 *
	 * @return int Id of the first item.
	 */
	public static function install_default(): int {
		global $wpdb;

		$table = self::table();
		$id    = (int) $wpdb->get_var( "SELECT id FROM $table ORDER BY id ASC LIMIT 1" );

		if ( ! $id ) {
			$now = current_time( 'mysql' );
			$wpdb->insert(
				$table,
				array(
					'name'         => 'Club House',
					'active'       => 1,
					'sort_order'   => 0,
					'quantity'     => 1,
					'booking_mode' => self::MODE_SLOTS,
					'fee_unit'     => 'booking',
					'weekend_days' => '6,7',
					'purpose_mode' => 'list',
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
			$id = (int) $wpdb->insert_id;
		}

		if ( $id ) {
			$bookings = CHP_Bookings::table();
			$wpdb->query( $wpdb->prepare( "UPDATE $bookings SET item_id = %d, unit = 'A' WHERE item_id = 0", $id ) );
		}

		self::$default_id = null;
		return $id;
	}
}
