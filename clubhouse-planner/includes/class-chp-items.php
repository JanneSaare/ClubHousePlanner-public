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

	/** @var array<int, object|null> Items looked up by booking_label(), per request. */
	private static array $cache = array();

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

	/** Forget cached lookups after items were changed. */
	public static function flush(): void {
		self::$default_id = null;
		self::$cache      = array();
	}

	/** @return object[] Items in display order. */
	public static function all( bool $only_active = false ): array {
		global $wpdb;
		$table = self::table();
		$where = $only_active ? 'WHERE active = 1' : '';
		return (array) $wpdb->get_results( "SELECT * FROM $table $where ORDER BY sort_order ASC, id ASC" );
	}

	public static function count_active(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE active = 1" );
	}

	/** True once there is more than one item, so item names are worth showing. */
	public static function has_multiple(): bool {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) > 1;
	}

	/** Item name, with the unit letter when the item has more than one unit, e.g. "Surfboard B". */
	public static function label( object $item, string $unit = '' ): string {
		return self::show_unit( $item ) && '' !== $unit ? $item->name . ' ' . $unit : $item->name;
	}

	/** Item and unit of a booking as text; empty when the item no longer exists. */
	public static function booking_label( object $booking ): string {
		$id = (int) ( $booking->item_id ?? 0 );
		if ( ! isset( self::$cache[ $id ] ) ) {
			self::$cache[ $id ] = self::get( $id );
		}
		return self::$cache[ $id ] ? self::label( self::$cache[ $id ], (string) ( $booking->unit ?? '' ) ) : '';
	}

	/** Zero-based index of a unit letter: A = 0, Z = 25, AA = 26. */
	public static function unit_index( string $label ): int {
		$n = 0;
		foreach ( str_split( strtoupper( $label ) ) as $char ) {
			$n = $n * 26 + ( ord( $char ) - 64 );
		}
		return $n - 1;
	}

	/**
	 * Highest unit index held by a booking that has not ended and is still in play, or -1 if there is none.
	 * The quantity cannot be lowered below this plus one.
	 */
	public static function highest_unit_in_use( int $item_id ): int {
		global $wpdb;

		$bookings     = CHP_Bookings::table();
		$statuses     = CHP_Bookings::active_statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $item_id ), $statuses, array( current_time( 'mysql' ) ) );

		$units = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT unit FROM $bookings WHERE item_id = %d AND status IN ($placeholders) AND end_at >= %s",
				$params
			)
		);
		return $units ? max( array_map( array( self::class, 'unit_index' ), $units ) ) : -1;
	}

	/**
	 * A length from the form as minutes: fields "{$key}_value" and "{$key}_unit" (hours or days). Null when empty.
	 */
	private static function parse_duration( array $input, string $key ): ?int {
		$value = str_replace( ',', '.', trim( (string) ( $input[ $key . '_value' ] ?? '' ) ) );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value <= 0 ) {
			return null;
		}
		$minutes = 'days' === ( $input[ $key . '_unit' ] ?? 'hours' ) ? 1440 : 60;
		return (int) min( 5 * 366 * 1440, round( (float) $value * $minutes ) );
	}

	/** A length in minutes as text, e.g. "2 days", "3 hours" or "90 minutes". */
	public static function format_minutes( int $minutes ): string {
		if ( 0 === $minutes % 1440 ) {
			/* translators: %d: number of days */
			return sprintf( _n( '%d day', '%d days', intdiv( $minutes, 1440 ), 'clubhouse-planner' ), intdiv( $minutes, 1440 ) );
		}
		if ( 0 === $minutes % 60 ) {
			/* translators: %d: number of hours */
			return sprintf( _n( '%d hour', '%d hours', intdiv( $minutes, 60 ), 'clubhouse-planner' ), intdiv( $minutes, 60 ) );
		}
		/* translators: %d: number of minutes */
		return sprintf( _n( '%d minute', '%d minutes', $minutes, 'clubhouse-planner' ), $minutes );
	}

	/**
	 * Why a requested period is not allowed for a free-time item, or null if it is fine.
	 * Codes are keys of CHP_Public::form_messages().
	 */
	public static function period_error( object $item, DateTimeImmutable $start, DateTimeImmutable $end ): ?string {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		if ( $end <= $start ) {
			return 'order';
		}
		if ( $start < $now ) {
			return 'past';
		}
		if ( $item->min_notice && $start < $now->modify( '+' . (int) $item->min_notice . ' hours' ) ) {
			return 'notice';
		}
		$minutes = intdiv( $end->getTimestamp() - $start->getTimestamp(), 60 );
		if ( $item->min_duration && $minutes < (int) $item->min_duration ) {
			return 'min';
		}
		if ( $item->max_duration && $minutes > (int) $item->max_duration ) {
			return 'max';
		}
		return null;
	}

	/** Purpose choices for an item that uses a list; the club house default list until one is saved. */
	public static function purpose_options( object $item ): array {
		$list = self::purposes( $item );
		return ! $list && 'list' === $item->purpose_mode ? CHP_Bookings::purposes() : $list;
	}

	/** Short price description for the item list, e.g. "100 kr per day (weekend 150 kr)". */
	public static function fee_summary( object $item ): string {
		if ( self::MODE_FREE !== $item->booking_mode ) {
			return __( 'Per booking type, see Settings', 'clubhouse-planner' );
		}
		$unit = CHP_Pricing::fee_units()[ $item->fee_unit ] ?? '';
		$text = CHP_Bookings::format_fee( (int) $item->fee_weekday ) . ', ' . mb_strtolower( $unit );
		if ( null !== $item->fee_weekend ) {
			/* translators: %s: weekend price */
			$text .= ' ' . sprintf( __( '(weekend %s)', 'clubhouse-planner' ), CHP_Bookings::format_fee( (int) $item->fee_weekend ) );
		}
		return $text;
	}

	/** Dropdown values for an item with a purpose list. */
	public static function purposes( object $item ): array {
		$list = json_decode( (string) $item->purposes, true );
		return is_array( $list ) ? array_values( array_filter( array_map( 'strval', $list ) ) ) : array();
	}

	/**
	 * Validate submitted item fields.
	 *
	 * @param int $id Id of the item being edited, 0 for a new one.
	 * @return array|WP_Error Row data to save, or an error whose code is a key of CHP_Admin::item_errors().
	 */
	public static function sanitize( array $input, int $id = 0 ): array|WP_Error {
		$name = mb_substr( sanitize_text_field( $input['name'] ?? '' ), 0, 190 );
		if ( '' === $name ) {
			return new WP_Error( 'name' );
		}

		$quantity = absint( $input['quantity'] ?? 1 );
		if ( $quantity < 1 || $quantity > self::MAX_QUANTITY ) {
			return new WP_Error( 'quantity' );
		}
		if ( $id && $quantity - 1 < self::highest_unit_in_use( $id ) ) {
			return new WP_Error( 'quantity_in_use' );
		}

		$active = empty( $input['active'] ) ? 0 : 1;
		if ( $id && ! $active ) {
			$item = self::get( $id );
			if ( $item && (int) $item->active && self::count_active() < 2 ) {
				return new WP_Error( 'last_active' );
			}
		}

		$mode = (string) ( $input['purpose_mode'] ?? 'none' );
		if ( ! in_array( $mode, array( 'none', 'list', 'text' ), true ) ) {
			$mode = 'none';
		}
		$purposes = array_slice( array_values( array_unique( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', explode( "\n", sanitize_textarea_field( $input['purposes'] ?? '' ) ) ) ) ) ) ), 0, 30 );
		if ( 'list' === $mode && ! $purposes ) {
			return new WP_Error( 'purposes' );
		}

		$key_box = mb_substr( sanitize_text_field( $input['key_box_code'] ?? '' ), 0, 100 );

		$booking_mode = self::MODE_FREE === ( $input['booking_mode'] ?? '' ) ? self::MODE_FREE : self::MODE_SLOTS;
		$fee_unit     = (string) ( $input['fee_unit'] ?? CHP_Pricing::FEE_BOOKING );
		if ( ! isset( CHP_Pricing::fee_units()[ $fee_unit ] ) ) {
			$fee_unit = CHP_Pricing::FEE_BOOKING;
		}
		$fee_weekend = trim( (string) ( $input['fee_weekend'] ?? '' ) );

		$weekend_days = array_unique( array_filter( array_map( 'absint', (array) ( $input['weekend_days'] ?? array() ) ), static fn( int $d ): bool => $d >= 1 && $d <= 7 ) );
		sort( $weekend_days );

		$min_duration = self::parse_duration( $input, 'min_duration' );
		$max_duration = self::parse_duration( $input, 'max_duration' );
		if ( null !== $min_duration && null !== $max_duration && $min_duration > $max_duration ) {
			return new WP_Error( 'duration' );
		}
		$notice = absint( $input['min_notice'] ?? 0 );

		return array(
			'booking_mode' => $booking_mode,
			'fee_unit'     => $fee_unit,
			'fee_weekday'  => absint( $input['fee_weekday'] ?? 0 ),
			'fee_weekend'  => '' === $fee_weekend ? null : absint( $fee_weekend ),
			'weekend_days' => implode( ',', $weekend_days ),
			'min_duration' => $min_duration,
			'max_duration' => $max_duration,
			'min_notice'   => $notice > 0 ? $notice : null,
			'name'         => $name,
			'description'  => sanitize_textarea_field( $input['description'] ?? '' ),
			'active'       => $active,
			'sort_order'   => (int) ( $input['sort_order'] ?? 0 ),
			'quantity'     => $quantity,
			'purpose_mode' => $mode,
			'purposes'     => $purposes ? wp_json_encode( array_map( fn( string $p ): string => mb_substr( $p, 0, 190 ), $purposes ) ) : null,
			'key_box_code' => '' === $key_box ? null : $key_box,
		);
	}

	/**
	 * Insert a new item ($id = 0) or update one.
	 *
	 * @param array $data Result of sanitize().
	 * @return int Item id, or 0 on failure.
	 */
	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );
		if ( $id ) {
			$ok = false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = $data['updated_at'];
			$ok = (bool) $wpdb->insert( self::table(), $data );
			$id = (int) $wpdb->insert_id;
		}

		self::flush();
		return $ok ? $id : 0;
	}
}
