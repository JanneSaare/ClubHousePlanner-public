<?php
defined( 'ABSPATH' ) || exit;

/**
 * Blocked periods: intervals where an item (or one unit of it, or every item) cannot be booked.
 *
 * A period can repeat every year (seasonal closure). The stored start and end are the first occurrence;
 * a period that runs over New Year, e.g. 1 Nov – 31 Mar, is simply a start and an end in different years.
 */
class CHP_Blackouts {

	/** Public calendar text when the administrator has not entered one is added by the calendar script. */
	const MAX_CALENDAR_DAYS = 100;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chp_blackouts';
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/** @return object[] All blocked periods, latest start first. */
	public static function all(): array {
		global $wpdb;
		$table = self::table();
		return (array) $wpdb->get_results( "SELECT * FROM $table ORDER BY start_at DESC, id DESC" );
	}

	/**
	 * Occurrences of a period that overlap [$from, $to).
	 *
	 * @return DateTimeImmutable[][] List of [start, end].
	 */
	public static function occurrences( object $row, DateTimeImmutable $from, DateTimeImmutable $to ): array {
		$tz    = wp_timezone();
		$start = new DateTimeImmutable( $row->start_at, $tz );
		$end   = new DateTimeImmutable( $row->end_at, $tz );

		if ( ! (int) $row->yearly ) {
			return $start < $to && $end > $from ? array( array( $start, $end ) ) : array();
		}

		$first_year = (int) $start->format( 'Y' );
		$span       = (int) $end->format( 'Y' ) - $first_year;
		$found      = array();
		for ( $year = (int) $from->format( 'Y' ) - $span; $year <= (int) $to->format( 'Y' ); $year++ ) {
			$shift = $year - $first_year;
			if ( $shift < 0 ) {
				continue;
			}
			$s = $start->modify( '+' . $shift . ' years' );
			$e = $end->modify( '+' . $shift . ' years' );
			if ( $s < $to && $e > $from ) {
				$found[] = array( $s, $e );
			}
		}
		return $found;
	}

	/**
	 * Periods that apply to an item and overlap [$start, $end). Yearly periods are expanded to the occurrence that overlaps.
	 *
	 * @param string $start Y-m-d H:i:s, site time.
	 * @return object[] Rows with extra properties occ_start and occ_end (Y-m-d H:i:s).
	 */
	public static function overlapping( int $item_id, string $start, string $end ): array {
		global $wpdb;

		$table = self::table();
		$rows  = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE (item_id IS NULL OR item_id = %d) AND (yearly = 1 OR (start_at < %s AND end_at > %s))",
				$item_id,
				$end,
				$start
			)
		);

		$tz   = wp_timezone();
		$from = new DateTimeImmutable( $start, $tz );
		$to   = new DateTimeImmutable( $end, $tz );
		$out  = array();
		foreach ( $rows as $row ) {
			foreach ( self::occurrences( $row, $from, $to ) as [ $s, $e ] ) {
				$hit            = clone $row;
				$hit->occ_start = $s->format( 'Y-m-d H:i:s' );
				$hit->occ_end   = $e->format( 'Y-m-d H:i:s' );
				$out[]          = $hit;
			}
		}
		return $out;
	}

	/** @return string[] Unit letters of the item that are blocked during [$start, $end). */
	public static function blocked_units( int $item_id, string $start, string $end ): array {
		$rows = self::overlapping( $item_id, $start, $end );
		if ( ! $rows ) {
			return array();
		}

		$item = CHP_Items::get( $item_id );
		if ( ! $item ) {
			return array();
		}
		$all = CHP_Items::unit_labels( (int) $item->quantity );

		$units = array();
		foreach ( $rows as $row ) {
			if ( null === $row->unit || '' === $row->unit ) {
				return $all;
			}
			$units[] = $row->unit;
		}
		return array_values( array_intersect( $all, $units ) );
	}

	/** True when every unit of the item is blocked during the period. */
	public static function blocks_item( int $item_id, string $start, string $end ): bool {
		$item = CHP_Items::get( $item_id );
		return $item && count( self::blocked_units( $item_id, $start, $end ) ) >= (int) $item->quantity;
	}

	/**
	 * Days in [$start, $end] on which the whole item is blocked, for the public calendar.
	 * Periods that block a single unit are not shown, because the item can still be booked.
	 *
	 * @param string $start Y-m-d H:i:s.
	 * @param string $end   Y-m-d H:i:s.
	 * @return array[] Each with date (Y-m-d) and text (public text, may be empty).
	 */
	public static function calendar_days( int $item_id, string $start, string $end ): array {
		$tz     = wp_timezone();
		$limit  = new DateTimeImmutable( $end, $tz );
		$days   = array();
		foreach ( self::overlapping( $item_id, $start, $end ) as $row ) {
			if ( null !== $row->unit && '' !== $row->unit ) {
				continue;
			}
			$day = new DateTimeImmutable( max( $row->occ_start, $start ), $tz );
			$day = $day->setTime( 0, 0 );
			$stop = new DateTimeImmutable( $row->occ_end, $tz );
			for ( $i = 0; $i < self::MAX_CALENDAR_DAYS && $day < $stop && $day <= $limit; $i++ ) {
				$key = $day->format( 'Y-m-d' );
				if ( ! isset( $days[ $key ] ) || ( '' === $days[ $key ]['text'] && '' !== (string) $row->public_text ) ) {
					$days[ $key ] = array(
						'date' => $key,
						'text' => (string) $row->public_text,
					);
				}
				$day = $day->modify( '+1 day' );
			}
		}
		ksort( $days );
		return array_values( $days );
	}

	/**
	 * Bookings still in play that would fall inside a period. Yearly periods are checked for the next three years.
	 *
	 * @param object $row A period (item_id, unit, start_at, end_at, yearly).
	 * @return object[] Bookings with id, item_id, unit, name, status, start_at, end_at.
	 */
	public static function conflicting_bookings( object $row ): array {
		global $wpdb;

		$tz    = wp_timezone();
		$now   = new DateTimeImmutable( 'now', $tz );
		$start = new DateTimeImmutable( $row->start_at, $tz );
		$end   = new DateTimeImmutable( $row->end_at, $tz );
		$from  = max( $start, $now );
		$to    = (int) $row->yearly ? max( $end, $now->modify( '+3 years' ) ) : $end;

		$bookings     = CHP_Bookings::table();
		$statuses     = CHP_Bookings::active_statuses();
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$found = array();
		foreach ( self::occurrences( $row, $from, $to ) as [ $s, $e ] ) {
			$where  = "status IN ($placeholders) AND start_at < %s AND end_at > %s";
			$params = array_merge( $statuses, array( $e->format( 'Y-m-d H:i:s' ), $s->format( 'Y-m-d H:i:s' ) ) );
			if ( ! empty( $row->item_id ) ) {
				$where   .= ' AND item_id = %d';
				$params[] = (int) $row->item_id;
				if ( ! empty( $row->unit ) ) {
					$where   .= ' AND unit = %s';
					$params[] = $row->unit;
				}
			}
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare( "SELECT id, item_id, unit, name, status, start_at, end_at FROM $bookings WHERE $where ORDER BY start_at ASC", $params )
			);
			foreach ( $rows as $booking ) {
				$found[ (int) $booking->id ] = $booking;
			}
		}
		return array_values( $found );
	}

	/**
	 * Validate submitted fields.
	 *
	 * @return array|WP_Error Row data, or an error whose code is a key of CHP_Admin::blackout_notices().
	 */
	public static function sanitize( array $input ): array|WP_Error {
		$item_id = absint( $input['item_id'] ?? 0 );
		$unit    = strtoupper( sanitize_text_field( $input['unit'] ?? '' ) );

		if ( $item_id ) {
			$item = CHP_Items::get( $item_id );
			if ( ! $item ) {
				return new WP_Error( 'item' );
			}
			if ( '' !== $unit && ! in_array( $unit, CHP_Items::unit_labels( (int) $item->quantity ), true ) ) {
				return new WP_Error( 'unit' );
			}
		} else {
			$unit = '';
		}

		$tz     = wp_timezone();
		$parse  = static function ( string $value ) use ( $tz ): ?DateTimeImmutable {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, $tz );
			return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $date : null;
		};
		$start = $parse( sanitize_text_field( $input['start_at'] ?? '' ) );
		$end   = $parse( sanitize_text_field( $input['end_at'] ?? '' ) );
		if ( ! $start || ! $end ) {
			return new WP_Error( 'dates' );
		}
		if ( $end <= $start ) {
			return new WP_Error( 'range' );
		}

		$yearly = empty( $input['yearly'] ) ? 0 : 1;
		if ( $yearly && $end >= $start->modify( '+1 year' ) ) {
			return new WP_Error( 'span' );
		}

		$public = mb_substr( sanitize_text_field( $input['public_text'] ?? '' ), 0, 190 );

		return array(
			'item_id'     => $item_id ? $item_id : null,
			'unit'        => '' === $unit ? null : $unit,
			'start_at'    => $start->format( 'Y-m-d H:i:s' ),
			'end_at'      => $end->format( 'Y-m-d H:i:s' ),
			'yearly'      => $yearly,
			'reason'      => mb_substr( sanitize_text_field( $input['reason'] ?? '' ), 0, 190 ),
			'public_text' => '' === $public ? null : $public,
		);
	}

	/**
	 * Insert ($id = 0) or update a period.
	 *
	 * @return int Id, or 0 on failure.
	 */
	public static function save( array $data, int $id = 0 ): int {
		global $wpdb;

		if ( $id ) {
			return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) ) ? $id : 0;
		}
		$data['created_at'] = current_time( 'mysql' );
		return $wpdb->insert( self::table(), $data ) ? (int) $wpdb->insert_id : 0;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}
}
