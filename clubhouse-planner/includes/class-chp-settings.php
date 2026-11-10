<?php
defined( 'ABSPATH' ) || exit;

class CHP_Settings {

	const OPTION = 'chp_settings';

	public static function defaults(): array {
		return array(
			'notify_emails'        => get_option( 'admin_email' ),
			'treasurer_emails'     => '',
			'treasurer_days'       => 7,
			'from_email'           => '',
			'from_name'            => '',
			'payment_instructions' => __( "Please pay the rent to bank account XXXX-XXXX within 10 days.\nMark the payment with booking number {booking_id}.", 'clubhouse-planner' ),
			'swish_number'         => '',
			'key_box_code'         => '',
			'show_pending'         => 1,
			'house_rules_page'     => 0,
			'calendar_width'       => 60,
			'form_width'           => 60,
			'standard_fee'         => 0,
			'local_streets'        => '',
			'local_addresses'      => '',
			'slots'                => self::default_slots(),
			'subjects'             => array(),
			'bodies'               => array(),
		);
	}

	/** Time slot setup, keyed by slot. An end time not after the start means the next day. */
	public static function default_slots(): array {
		return array(
			CHP_Bookings::SLOT_FULL    => array(
				'enabled' => 1,
				'start'   => '10:00',
				'end'     => '00:00',
			),
			CHP_Bookings::SLOT_DAY     => array(
				'enabled' => 1,
				'start'   => '10:00',
				'end'     => '16:00',
			),
			CHP_Bookings::SLOT_EVENING => array(
				'enabled' => 1,
				'start'   => '16:00',
				'end'     => '00:00',
			),
		);
	}

	/** Normal price (SEK) for a booking type. */
	public static function standard_fee( string $slot ): int {
		$slots = self::slot_settings();
		return (int) ( $slots[ $slot ]['fee'] ?? 0 );
	}

	/** Saved slot setup with defaults for anything missing. */
	public static function slot_settings(): array {
		$saved = (array) self::get( 'slots' );
		$slots = array();
		foreach ( self::default_slots() as $key => $default ) {
			$slots[ $key ] = array_merge( $default, is_array( $saved[ $key ] ?? null ) ? $saved[ $key ] : array() );
			// Settings saved before per-type prices used one standard fee for all types.
			if ( ! isset( $saved[ $key ]['fee'] ) ) {
				$slots[ $key ]['fee'] = (int) self::get( 'standard_fee' );
			}
		}
		return $slots;
	}

	/** Whole settings array when $key is null, otherwise the single value. */
	public static function get( ?string $key = null ): mixed {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $settings;
		}
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Addresses from settings plus all users with the housekeeper role.
	 */
	public static function notify_recipients(): array {
		$emails = array_map( 'trim', explode( ',', (string) self::get( 'notify_emails' ) ) );

		$housekeepers = get_users(
			array(
				'role'   => CHP_Activator::ROLE,
				'fields' => array( 'user_email' ),
			)
		);
		foreach ( $housekeepers as $user ) {
			$emails[] = $user->user_email;
		}

		return array_values( array_unique( array_filter( $emails, 'is_email' ) ) );
	}

	/** Treasurer addresses for payment reminders. */
	public static function treasurer_recipients(): array {
		$emails = array_map( 'trim', explode( ',', (string) self::get( 'treasurer_emails' ) ) );
		return array_values( array_unique( array_filter( $emails, 'is_email' ) ) );
	}

	public static function local_streets(): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) self::get( 'local_streets' ) ) ) ) );
	}

	/** Single addresses on other streets that also get the standard fee. */
	public static function local_addresses(): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) self::get( 'local_addresses' ) ) ) ) );
	}

	/**
	 * Lower case, single spaces and no space before a trailing letter, so "storgatan 12 b" equals "Storgatan 12B".
	 */
	public static function normalize_address( string $address ): string {
		$address = mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $address ) ) );
		return (string) preg_replace( '/(\d) (\p{L})$/u', '$1$2', $address );
	}

	/**
	 * True if the address is on the standard-fee address list or starts with one of the
	 * standard-fee street names, e.g. "Storgatan 12B".
	 */
	public static function is_local_address( string $address ): bool {
		$normalized = self::normalize_address( $address );
		foreach ( self::local_addresses() as $local ) {
			if ( self::normalize_address( $local ) === $normalized ) {
				return true;
			}
		}

		foreach ( self::local_streets() as $street ) {
			if ( preg_match( '/^\s*' . preg_quote( $street, '/' ) . '(?=[\s\d,]|$)/iu', $address ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The published page with the house rules, or null when none is configured (or it is no longer public).
	 */
	public static function house_rules_page(): ?WP_Post {
		$id = (int) self::get( 'house_rules_page' );
		if ( ! $id ) {
			return null;
		}
		$page = get_post( $id );
		return $page && 'page' === $page->post_type && 'publish' === $page->post_status && '' === $page->post_password ? $page : null;
	}

	public static function sanitize( mixed $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$emails  = array_filter( array_map( 'sanitize_email', explode( ',', $input['notify_emails'] ?? '' ) ), 'is_email' );
		$streets = array_unique( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $input['local_streets'] ?? '' ) ) ) ) );

		$addresses = array();
		foreach ( explode( "\n", sanitize_textarea_field( $input['local_addresses'] ?? '' ) ) as $address ) {
			$address = trim( (string) preg_replace( '/\s+/u', ' ', $address ) );
			if ( '' !== $address ) {
				$addresses[ self::normalize_address( $address ) ] ??= $address;
			}
		}

		// Empty, or equal to the default, means "use the (translated) default".
		$subjects = array();
		foreach ( CHP_Mailer::subject_defaults() as $key => $default ) {
			$subject = sanitize_text_field( $input['subjects'][ $key ] ?? '' );
			if ( '' !== $subject && $subject !== $default['subject'] ) {
				$subjects[ $key ] = $subject;
			}
		}

		// Same rule for the HTML bodies; only safe post-content HTML is kept.
		$bodies = array();
		foreach ( CHP_Mailer::email_defaults() as $key => $default ) {
			$body = trim( wp_kses_post( (string) ( $input['bodies'][ $key ] ?? '' ) ) );
			if ( '' !== $body && $body !== $default['body'] ) {
				$bodies[ $key ] = $body;
			}
		}

		// Invalid times keep the previous value; at least one slot must stay enabled.
		$previous = self::slot_settings();
		$labels   = CHP_Bookings::slot_definitions();
		$slots    = array();
		foreach ( $previous as $key => $old ) {
			$new           = is_array( $input['slots'][ $key ] ?? null ) ? $input['slots'][ $key ] : array();
			$slots[ $key ] = array(
				'enabled' => empty( $new['enabled'] ) ? 0 : 1,
				'fee'     => absint( $new['fee'] ?? $old['fee'] ),
					'caption' => mb_substr( sanitize_text_field( $new['caption'] ?? '' ), 0, 40 ),
			);
			foreach ( array( 'start', 'end' ) as $field ) {
				$time = sanitize_text_field( $new[ $field ] ?? '' );
				if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
					$slots[ $key ][ $field ] = $time;
				} else {
					$slots[ $key ][ $field ] = $old[ $field ];
					/* translators: %s: booking type, e.g. Evening */
					add_settings_error( self::OPTION, 'chp_slot_time_' . $key, sprintf( __( 'Invalid time for %s. The previous time was kept.', 'clubhouse-planner' ), $labels[ $key ]['label'] ) );
				}
			}
		}
		if ( ! array_filter( array_column( $slots, 'enabled' ) ) ) {
			foreach ( $slots as $key => $slot ) {
				$slots[ $key ]['enabled'] = $previous[ $key ]['enabled'];
			}
			add_settings_error( self::OPTION, 'chp_slot_enabled', __( 'At least one booking type must be enabled.', 'clubhouse-planner' ) );
		}

		$treasurer = array_filter( array_map( 'sanitize_email', explode( ',', $input['treasurer_emails'] ?? '' ) ), 'is_email' );

		return array(
			'notify_emails'        => implode( ', ', $emails ),
			'treasurer_emails'     => implode( ', ', $treasurer ),
			'treasurer_days'       => min( 365, max( 1, absint( $input['treasurer_days'] ?? 7 ) ) ),
			'from_email'           => sanitize_email( $input['from_email'] ?? '' ),
			'from_name'            => trim( str_replace( array( '<', '>', '"', ',' ), '', sanitize_text_field( $input['from_name'] ?? '' ) ) ),
			'payment_instructions' => sanitize_textarea_field( $input['payment_instructions'] ?? '' ),
			'swish_number'         => preg_replace( '/\D+/', '', (string) ( $input['swish_number'] ?? '' ) ),
			'key_box_code'         => sanitize_text_field( $input['key_box_code'] ?? '' ),
			'show_pending'         => empty( $input['show_pending'] ) ? 0 : 1,
			'house_rules_page'     => absint( $input['house_rules_page'] ?? 0 ),
			'calendar_width'       => min( 100, max( 30, absint( $input['calendar_width'] ?? 60 ) ) ),
			'form_width'           => min( 100, max( 30, absint( $input['form_width'] ?? 60 ) ) ),
			'local_streets'        => implode( "\n", $streets ),
			'local_addresses'      => implode( "\n", $addresses ),
			'slots'                => $slots,
			'subjects'             => $subjects,
			'bodies'               => $bodies,
		);
	}
}
