<?php
defined( 'ABSPATH' ) || exit;

/**
 * Data access for bookings and the booking status log.
 *
 * Flow: pending -> awaiting_payment -> confirmed
 *       pending/awaiting_payment -> denied
 *       awaiting_payment/confirmed -> cancelled
 */
class CHP_Bookings {

	const PENDING          = 'pending';
	const AWAITING_PAYMENT = 'awaiting_payment';
	const CONFIRMED        = 'confirmed';
	const DENIED           = 'denied';
	const CANCELLED        = 'cancelled';

	const SLOT_DAY     = 'day';
	const SLOT_EVENING = 'evening';
	const SLOT_FULL    = 'full';

	/**
	 * All time slots with label, times (H:i) and enabled flag. An end time not after the start means the next day.
	 * Use this to display existing bookings, whose slot may have been disabled since.
	 */
	public static function slot_definitions(): array {
		$labels = array(
			self::SLOT_FULL    => __( 'Whole day', 'clubhouse-planner' ),
			self::SLOT_DAY     => __( 'Day', 'clubhouse-planner' ),
			self::SLOT_EVENING => __( 'Evening', 'clubhouse-planner' ),
		);

		$settings = CHP_Settings::slot_settings();
		// With a single bookable type the type name carries no information, so just call it "Booked".
		if ( 1 === count( array_filter( array_column( $settings, 'enabled' ) ) ) ) {
			foreach ( $settings as $key => $slot ) {
				if ( $slot['enabled'] ) {
					$labels[ $key ] = __( 'Booked', 'clubhouse-planner' );
				}
			}
		}

		$slots = array();
		foreach ( $settings as $key => $slot ) {
			// A caption set by the administrator replaces the default type name.
			$caption       = trim( (string) ( $slot['caption'] ?? '' ) );
			$slots[ $key ] = array(
				'label'   => '' !== $caption ? $caption : $labels[ $key ],
				'start'   => $slot['start'],
				'end'     => $slot['end'],
				'enabled' => (bool) $slot['enabled'],
				'fee'     => (int) $slot['fee'],
			);
		}
		return $slots;
	}

	/**
	 * Slots that can be booked now.
	 */
	public static function slots(): array {
		return array_filter( self::slot_definitions(), fn( array $slot ): bool => $slot['enabled'] );
	}

	/** Booked time span as "H:i–H:i", taken from the booking itself so later slot changes do not affect it. */
	public static function booking_times( object $booking ): string {
		return substr( $booking->start_at, 11, 5 ) . '–' . substr( $booking->end_at, 11, 5 );
	}

	/**
	 * @return DateTimeImmutable[]|null [start, end] for the slot on the given Y-m-d date, or null if invalid.
	 */
	public static function slot_period( string $date, string $slot ): ?array {
		$slots = self::slots();
		if ( ! isset( $slots[ $slot ] ) ) {
			return null;
		}

		$tz    = wp_timezone();
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $slots[ $slot ]['start'], $tz );
		if ( ! $start || $start->format( 'Y-m-d' ) !== $date ) {
			return null;
		}

		$end = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $slots[ $slot ]['end'], $tz );
		if ( $end <= $start ) {
			$end = $end->modify( '+1 day' );
		}

		return array( $start, $end );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chp_bookings';
	}

	public static function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chp_booking_log';
	}

	public static function statuses(): array {
		return array(
			self::PENDING          => __( 'Request received', 'clubhouse-planner' ),
			self::AWAITING_PAYMENT => __( 'Awaiting payment', 'clubhouse-planner' ),
			self::CONFIRMED        => __( 'Confirmed', 'clubhouse-planner' ),
			self::DENIED           => __( 'Denied', 'clubhouse-planner' ),
			self::CANCELLED        => __( 'Cancelled', 'clubhouse-planner' ),
		);
	}

	public static function status_label( string $status ): string {
		$statuses = self::statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}

	/** Statuses that wait for the housekeeper: shown in the default booking list. */
	public static function open_statuses(): array {
		return array( self::PENDING, self::AWAITING_PAYMENT );
	}

	/** Statuses that reserve the house so no other booking may overlap. */
	public static function blocking_statuses(): array {
		return array( self::AWAITING_PAYMENT, self::CONFIRMED );
	}

	/** Statuses that are still in play (not denied or cancelled). */
	public static function active_statuses(): array {
		return array( self::PENDING, self::AWAITING_PAYMENT, self::CONFIRMED );
	}

	/**
	 * Other active bookings from the day before to the day after, keyed by day offset -1, 0 and 1.
	 * Each row gets an extra bool property `overlaps`.
	 */
	public static function neighbours( object $booking ): array {
		global $wpdb;

		$table        = self::table();
		$date         = new DateTimeImmutable( substr( $booking->start_at, 0, 10 ) );
		$statuses     = self::active_statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge(
			array( (int) $booking->id, (int) $booking->item_id ),
			$statuses,
			array( $date->modify( '-1 day' )->format( 'Y-m-d 00:00:00' ), $date->modify( '+2 days' )->format( 'Y-m-d 00:00:00' ) )
		);

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, item_id, unit, status, slot, name, start_at, end_at FROM $table
				 WHERE id <> %d AND item_id = %d AND status IN ($placeholders) AND start_at >= %s AND start_at < %s
				 ORDER BY start_at ASC",
				$params
			)
		);

		$groups = array(
			-1 => array(),
			0  => array(),
			1  => array(),
		);
		foreach ( $rows as $row ) {
			$offset         = (int) $date->diff( new DateTimeImmutable( substr( $row->start_at, 0, 10 ) ) )->format( '%r%a' );
			// Overlapping times only conflict when both bookings hold the same unit.
			$row->overlaps  = $row->unit === $booking->unit && $row->start_at < $booking->end_at && $row->end_at > $booking->start_at;
			$groups[ $offset ][] = $row;
		}
		return $groups;
	}

	public static function transitions(): array {
		return array(
			self::PENDING          => array( self::AWAITING_PAYMENT, self::DENIED ),
			self::AWAITING_PAYMENT => array( self::CONFIRMED, self::DENIED, self::CANCELLED ),
			self::CONFIRMED        => array( self::CANCELLED ),
		);
	}

	public static function allowed_transitions( string $from ): array {
		$map = self::transitions();
		return isset( $map[ $from ] ) ? $map[ $from ] : array();
	}

	public static function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::allowed_transitions( $from ), true );
	}

	/** What the clubhouse is booked for; the list shown in the booking form. */
	public static function purposes(): array {
		return array(
			__( 'Private (up to 10 people)', 'clubhouse-planner' ),
			__( 'Private (up to 25 people)', 'clubhouse-planner' ),
			__( 'Private (more than 25 people)', 'clubhouse-planner' ),
			__( 'Association meeting', 'clubhouse-planner' ),
			__( 'Pub', 'clubhouse-planner' ),
			__( 'Board meeting', 'clubhouse-planner' ),
		);
	}

	/**
	 * @param array $data name, email, phone, address, slot, start_at, end_at (Y-m-d H:i:s, site time), purpose, message.
	 *                    Optional: item_id (default item), unit (first free unit), and for bookings added by a housekeeper:
	 *                    status (default pending), fee, log (history note), user_id.
	 * @return int Booking id, or 0 on failure or when a booking that blocks the period finds no free unit.
	 */
	public static function create( array $data ): int {
		global $wpdb;

		$now     = current_time( 'mysql' );
		$status  = $data['status'] ?? self::PENDING;
		$item_id = (int) ( $data['item_id'] ?? 0 );
		if ( $item_id <= 0 ) {
			$item_id = CHP_Items::default_id();
		}
		$unit = (string) ( $data['unit'] ?? '' );

		// The item row is locked so two simultaneous requests cannot be given the same unit.
		$wpdb->query( 'START TRANSACTION' );
		self::lock_item( $item_id );

		$free = self::free_units( $item_id, $data['start_at'], $data['end_at'] );
		if ( '' === $unit ) {
			// A request that does not block can share a unit; it is sorted out when it is approved.
			$unit = $free[0] ?? 'A';
		}
		if ( in_array( $status, self::blocking_statuses(), true ) && ! in_array( $unit, $free, true ) ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}

		$row     = array(
			'item_id'    => $item_id,
			'unit'       => $unit,
			'status'     => $status,
			'slot'       => $data['slot'],
			'name'       => $data['name'],
			'email'      => $data['email'],
			'phone'      => $data['phone'],
			'address'    => $data['address'],
			'start_at'   => $data['start_at'],
			'end_at'     => $data['end_at'],
			'purpose'    => $data['purpose'],
			'message'    => $data['message'],
			'created_at' => $now,
			'updated_at' => $now,
		);
		if ( isset( $data['fee'] ) ) {
			$row['fee'] = (int) $data['fee'];
		}
		$formats = array_map( static fn( string $key ): string => in_array( $key, array( 'item_id', 'fee' ), true ) ? '%d' : '%s', array_keys( $row ) );

		if ( ! $wpdb->insert( self::table(), $row, $formats ) ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}

		$id = (int) $wpdb->insert_id;
		self::log( $id, $status, $data['log'] ?? __( 'Booking request submitted.', 'clubhouse-planner' ), (int) ( $data['user_id'] ?? 0 ) );
		$wpdb->query( 'COMMIT' );
		return $id;
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * @param string $from Only bookings ending at or after this time (Y-m-d H:i:s), or '' for all.
	 */
	public static function query( string|array $status = '', int $page = 1, int $per_page = 20, string $from = '', string $upcoming_since = '' ): array {
		global $wpdb;

		$table      = self::table();
		$conditions = array();
		$params     = array();

		$wanted = array_values( array_intersect( (array) $status, array_keys( self::statuses() ) ) );
		if ( $wanted ) {
			$in = 'status IN (' . implode( ',', array_fill( 0, count( $wanted ), '%s' ) ) . ')';
			$params = array_merge( $params, $wanted );
			if ( '' !== $upcoming_since ) {
				// Also include confirmed bookings that have not ended yet.
				$in      = "($in OR (status = %s AND end_at >= %s))";
				$params[] = self::CONFIRMED;
				$params[] = $upcoming_since;
			}
			$conditions[] = $in;
		}
		if ( '' !== $from ) {
			$conditions[] = 'end_at >= %s';
			$params[]     = $from;
		}
		$where = $conditions ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		$params[] = $per_page;
		$params[] = ( max( 1, $page ) - 1 ) * $per_page;

		// The open list: requests awaiting a decision first (oldest received at top), then the rest by booked date (nearest first).
		$order = '' !== $upcoming_since
			? "CASE status WHEN '" . self::PENDING . "' THEN 0 WHEN '" . self::AWAITING_PAYMENT . "' THEN 1 ELSE 2 END, "
				. "CASE WHEN status = '" . self::PENDING . "' THEN created_at ELSE start_at END ASC, id ASC"
			: 'created_at DESC, id DESC';

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $table $where ORDER BY $order LIMIT %d OFFSET %d", $params )
		);
	}

	/**
	 * @param string $from Only count bookings ending at or after this time (Y-m-d H:i:s), or '' for all.
	 */
	public static function count_by_status( string $from = '' ): array {
		global $wpdb;
		$table = self::table();
		$rows  = '' === $from
			? (array) $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM $table GROUP BY status" )
			: (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM $table WHERE end_at >= %s GROUP BY status", $from ) );
		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->n;
		}
		return $counts;
	}

	/**
	 * Number of bookings in the "Open" list: open statuses plus confirmed bookings that have not ended.
	 *
	 * @param string $from Only count bookings ending at or after this time, or '' for all.
	 * @param string $now  Current local time (Y-m-d H:i:s).
	 */
	public static function count_open( string $from, string $now ): int {
		global $wpdb;

		$table        = self::table();
		$open         = self::open_statuses();
		$placeholders = implode( ',', array_fill( 0, count( $open ), '%s' ) );
		$params       = array_merge( $open, array( self::CONFIRMED, $now ) );
		$sql          = "SELECT COUNT(*) FROM $table WHERE (status IN ($placeholders) OR (status = %s AND end_at >= %s))";
		if ( '' !== $from ) {
			$sql     .= ' AND end_at >= %s';
			$params[] = $from;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/** True when the booking's end time has passed. */
	public static function is_past( object $booking ): bool {
		return $booking->end_at < current_time( 'mysql' );
	}

	/**
	 * Bookings overlapping [$start, $end) with the given statuses.
	 */
	public static function in_range( string $start, string $end, array $statuses, int $item_id = 0 ): array {
		global $wpdb;

		if ( empty( $statuses ) ) {
			return array();
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( $statuses, array( $end, $start ) );
		$item_sql     = '';
		if ( $item_id > 0 ) {
			$item_sql = ' AND item_id = %d';
			$params[] = $item_id;
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, item_id, unit, status, slot, start_at, end_at FROM $table
				 WHERE status IN ($placeholders) AND start_at < %s AND end_at > %s$item_sql
				 ORDER BY start_at ASC",
				$params
			)
		);
	}

	/**
	 * Units of an item that are free for [$start, $end): not held by a blocking booking.
	 *
	 * @param int $exclude_id A booking to ignore (the one being approved or moved).
	 * @return string[] Unit letters in order; empty when the item is fully booked or does not exist.
	 */
	public static function free_units( int $item_id, string $start, string $end, int $exclude_id = 0 ): array {
		global $wpdb;

		$item = CHP_Items::get( $item_id );
		if ( ! $item ) {
			return array();
		}

		$statuses     = self::blocking_statuses();
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $item_id ), $statuses, array( $end, $start, $exclude_id ) );

		$taken = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT unit FROM $table
				 WHERE item_id = %d AND status IN ($placeholders) AND start_at < %s AND end_at > %s AND id <> %d",
				$params
			)
		);

		return array_values( array_diff( CHP_Items::unit_labels( (int) $item->quantity ), $taken ) );
	}

	/**
	 * Make sure a booking holds a unit that is free for its period, moving it to another free unit if its own was taken.
	 * Used when a request is approved, because pending requests do not block and may share a unit.
	 *
	 * @return bool False when no unit is free.
	 */
	public static function claim_unit( object $booking ): bool {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );
		self::lock_item( (int) $booking->item_id );

		$free = self::free_units( (int) $booking->item_id, $booking->start_at, $booking->end_at, (int) $booking->id );
		if ( ! $free ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		if ( ! in_array( $booking->unit, $free, true ) ) {
			$wpdb->update( self::table(), array( 'unit' => $free[0] ), array( 'id' => (int) $booking->id ), array( '%s' ), array( '%d' ) );
			self::log(
				(int) $booking->id,
				$booking->status,
				/* translators: 1: previous unit letter, 2: new unit letter */
				sprintf( __( 'Unit changed from %1$s to %2$s because the first one was already taken.', 'clubhouse-planner' ), $booking->unit, $free[0] ),
				get_current_user_id()
			);
		}

		$wpdb->query( 'COMMIT' );
		return true;
	}

	/**
	 * Move a booking to another unit of its item. A booking that blocks its period can only move to a free unit.
	 *
	 * @return bool False when the unit does not exist for the item or is taken.
	 */
	public static function reassign_unit( object $booking, string $unit ): bool {
		global $wpdb;

		$item = CHP_Items::get( (int) $booking->item_id );
		if ( ! $item || ! in_array( $unit, CHP_Items::unit_labels( (int) $item->quantity ), true ) ) {
			return false;
		}
		if ( $unit === $booking->unit ) {
			return true;
		}

		$wpdb->query( 'START TRANSACTION' );
		self::lock_item( (int) $booking->item_id );

		$free = self::free_units( (int) $booking->item_id, $booking->start_at, $booking->end_at, (int) $booking->id );
		if ( in_array( $booking->status, self::blocking_statuses(), true ) && ! in_array( $unit, $free, true ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->update( self::table(), array( 'unit' => $unit ), array( 'id' => (int) $booking->id ), array( '%s' ), array( '%d' ) );
		self::log(
			(int) $booking->id,
			$booking->status,
			/* translators: 1: previous unit letter, 2: new unit letter */
			sprintf( __( 'Unit changed from %1$s to %2$s.', 'clubhouse-planner' ), $booking->unit, $unit ),
			get_current_user_id()
		);
		$wpdb->query( 'COMMIT' );
		return true;
	}

	/** Serialise unit assignment per item. Must be called inside a transaction. */
	private static function lock_item( int $item_id ): void {
		global $wpdb;
		$items = CHP_Items::table();
		$wpdb->get_var( $wpdb->prepare( "SELECT id FROM $items WHERE id = %d FOR UPDATE", $item_id ) );
	}

	public static function set_status( int $id, string $status, string $note = '' ): bool {
		global $wpdb;

		$ok = $wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return false;
		}

		self::log( $id, $status, $note, get_current_user_id() );
		return true;
	}

	/**
	 * Bookings awaiting payment that start within $days days and have not yet triggered a treasurer reminder.
	 */
	public static function due_for_treasurer_reminder( int $days ): array {
		global $wpdb;

		$table = self::table();
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table
				 WHERE status = %s AND treasurer_reminded_at IS NULL AND end_at >= %s AND start_at <= %s
				 ORDER BY start_at ASC",
				self::AWAITING_PAYMENT,
				$now->format( 'Y-m-d H:i:s' ),
				$now->modify( '+' . $days . ' days' )->format( 'Y-m-d H:i:s' )
			)
		);
	}

	public static function mark_treasurer_reminded( int $id ): void {
		global $wpdb;
		$wpdb->update( self::table(), array( 'treasurer_reminded_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	public static function set_fee( int $id, int $fee ): bool {
		global $wpdb;
		return false !== $wpdb->update( self::table(), array( 'fee' => $fee ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
	}

	public static function is_local( object $booking ): bool {
		return CHP_Settings::is_local_address( (string) ( $booking->address ?? '' ) );
	}

	public static function format_fee( int $fee ): string {
		if ( 0 === $fee ) {
			return __( 'Free of charge', 'clubhouse-planner' );
		}
		/* translators: %s: amount in Swedish kronor */
		return sprintf( __( '%s kr', 'clubhouse-planner' ), number_format_i18n( $fee ) );
	}

	public static function log( int $booking_id, string $status, string $note, int $user_id ): void {
		global $wpdb;
		$wpdb->insert(
			self::log_table(),
			array(
				'booking_id' => $booking_id,
				'status'     => $status,
				'note'       => $note,
				'user_id'    => $user_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
	}

	public static function get_log( int $booking_id ): array {
		global $wpdb;
		$log = self::log_table();
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, u.display_name FROM $log l
				 LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
				 WHERE l.booking_id = %d ORDER BY l.id ASC",
				$booking_id
			)
		);
	}

	public static function format_period( object $booking ): string {
		$slots = self::slot_definitions();
		$slot  = $booking->slot ?? '';

		if ( isset( $slots[ $slot ] ) ) {
			return sprintf(
				/* translators: 1: date, 2: booking type, 3: start time, 4: end time */
				__( '%1$s, %2$s (%3$s–%4$s)', 'clubhouse-planner' ),
				mysql2date( get_option( 'date_format' ), $booking->start_at ),
				$slots[ $slot ]['label'],
				substr( $booking->start_at, 11, 5 ),
				substr( $booking->end_at, 11, 5 )
			);
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return mysql2date( $format, $booking->start_at ) . ' – ' . mysql2date( $format, $booking->end_at );
	}
}
