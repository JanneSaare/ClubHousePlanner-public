<?php
defined( 'ABSPATH' ) || exit;

class CHP_Public {

	const REST_NS      = 'clubhouse/v1';
	const OTHER_STREET = '__other';

	const MIN_FORM_SECONDS = 4;
	const MAX_PER_IP       = 5;
	const MAX_PER_EMAIL    = 3;

	public function __construct() {
		add_shortcode( 'clubhouse_calendar', array( $this, 'render_calendar' ) );
		add_shortcode( 'clubhouse_calendars', array( $this, 'render_calendars' ) );
		add_shortcode( 'clubhouse_booking_form', array( $this, 'render_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_nopriv_chp_submit_booking', array( $this, 'handle_submit' ) );
		add_action( 'admin_post_chp_submit_booking', array( $this, 'handle_submit' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'chp-public', CHP_URL . 'assets/css/public.css', array(), CHP_VERSION );
		wp_register_script( 'chp-booking-form', CHP_URL . 'assets/js/booking-form.js', array(), CHP_VERSION, true );
		wp_register_script( 'chp-calendar', CHP_URL . 'assets/js/calendar.js', array(), CHP_VERSION, true );
		wp_localize_script(
			'chp-booking-form',
			'chpForm',
			array(
				'otherStreet' => self::OTHER_STREET,
				'streets'     => CHP_Settings::local_streets(),
				'addresses'   => CHP_Settings::local_addresses(),
				'contact'     => __( 'Contact the housekeeper', 'clubhouse-planner' ),
				'rulesUrl'    => esc_url_raw( rest_url( self::REST_NS . '/house-rules' ) ),
				'priceUrl'    => esc_url_raw( rest_url( self::REST_NS . '/price' ) ),
				'i18n'        => array(
					'loading'   => __( 'Loading…', 'clubhouse-planner' ),
					'close'     => __( 'Close', 'clubhouse-planner' ),
					'loadError' => __( 'Could not load the house rules.', 'clubhouse-planner' ),
					'summary'        => __( 'Please correct the following before sending:', 'clubhouse-planner' ),
					'required'       => __( 'Please fill in this field.', 'clubhouse-planner' ),
					'selectRequired' => __( 'Please choose an option.', 'clubhouse-planner' ),
					'chooseOption'   => __( 'Please choose a booking type.', 'clubhouse-planner' ),
					'mustAccept'     => __( 'You must accept the house rules to continue.', 'clubhouse-planner' ),
					'invalidEmail'   => __( 'Please enter a valid email address, for example name@example.com.', 'clubhouse-planner' ),
					'invalidFormat'  => __( 'Please check the format.', 'clubhouse-planner' ),
					'pastDate'       => __( 'Please choose a date that is today or later.', 'clubhouse-planner' ),
				),
			)
		);
		wp_localize_script(
			'chp-calendar',
			'chpCalendar',
			array(
				'restUrl'     => esc_url_raw( rest_url( self::REST_NS . '/calendar' ) ),
				'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
				'locale'      => str_replace( '_', '-', get_locale() ),
				'slots'       => CHP_Bookings::slot_definitions(),
				'i18n'        => array(
					'prev'        => __( 'Previous month', 'clubhouse-planner' ),
					'next'        => __( 'Next month', 'clubhouse-planner' ),
					'booked'      => __( 'Booked', 'clubhouse-planner' ),
					'preliminary' => __( 'Preliminary', 'clubhouse-planner' ),
					'unavailable' => __( 'Not available', 'clubhouse-planner' ),
									/* translators: {free} and {total} are replaced by numbers, e.g. "2 of 3 free" */
									'free'        => __( '{free} of {total} free', 'clubhouse-planner' ),
					'loadError'   => __( 'Could not load the calendar.', 'clubhouse-planner' ),
				),
			)
		);
	}

	/**
	 * The item a shortcode shows: the one given by item="ID", or the default item when no item is given.
	 * Null when an item is given but does not exist or is not active, so a wrong number never shows another item's form.
	 */
	private function shortcode_item( mixed $atts, string $tag ): ?object {
		$atts = shortcode_atts( array( 'item' => 0 ), is_array( $atts ) ? $atts : array(), $tag );
		$id   = absint( $atts['item'] );
		if ( ! $id ) {
			return CHP_Items::get( CHP_Items::default_id() );
		}
		$item = CHP_Items::get( $id );
		return $item && (int) $item->active ? $item : null;
	}

	/** Shown to administrators only when a shortcode names an item that does not exist or is inactive. */
	private function missing_item_notice(): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		wp_enqueue_style( 'chp-public' );
		return '<p class="chp-notice chp-notice--error">' . esc_html__( 'ClubHouse Planner: the item in this shortcode does not exist or is not active. Check the item number under Club House → Items. Only administrators see this message.', 'clubhouse-planner' ) . '</p>';
	}

	public function render_calendar( mixed $atts = array() ): string {
		wp_enqueue_style( 'chp-public' );
		wp_enqueue_script( 'chp-calendar' );

		$item = $this->shortcode_item( $atts, 'clubhouse_calendar' );
		if ( ! $item ) {
			return $this->missing_item_notice();
		}

		ob_start();
		include CHP_DIR . 'templates/calendar.php';
		return (string) ob_get_clean();
	}

	/**
	 * Calendars of several items side by side: [clubhouse_calendars] for all active items or items="1,2" for chosen ones.
	 */
	public function render_calendars( mixed $atts = array() ): string {
		$atts = shortcode_atts( array( 'items' => '' ), is_array( $atts ) ? $atts : array(), 'clubhouse_calendars' );
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $atts['items'] ) ) ) ) );

		$items   = array();
		$missing = false;
		if ( $ids ) {
			foreach ( $ids as $id ) {
				$found = CHP_Items::get( $id );
				if ( $found && (int) $found->active ) {
					$items[] = $found;
				} else {
					$missing = true;
				}
			}
		} else {
			$items = CHP_Items::all( true );
		}
		if ( ! $items ) {
			return $this->missing_item_notice();
		}

		wp_enqueue_style( 'chp-public' );
		wp_enqueue_script( 'chp-calendar' );

		$grouped = true;
		ob_start();
		echo '<div class="chp-calendars">';
		foreach ( $items as $item ) {
			include CHP_DIR . 'templates/calendar.php';
		}
		echo '</div>';
		return ( $missing ? $this->missing_item_notice() : '' ) . (string) ob_get_clean();
	}

	public function render_form( mixed $atts = array() ): string {
		wp_enqueue_style( 'chp-public' );
		wp_enqueue_script( 'chp-booking-form' );

		$item = $this->shortcode_item( $atts, 'clubhouse_booking_form' );
		if ( ! $item ) {
			return $this->missing_item_notice();
		}
		$result = isset( $_GET['chp_result'] ) ? sanitize_key( wp_unslash( $_GET['chp_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		include CHP_DIR . 'templates/booking-form.php';
		return (string) ob_get_clean();
	}

	/**
	 * Messages for the booking form result codes. Pass the item to get texts that fit a free-time item.
	 */
	public static function form_messages( ?object $item = null ): array {
		$messages = self::base_messages();
		if ( ! CHP_Pricing::is_priced( $item ) ) {
			return $messages;
		}

		/* translators: %s: item name */
		$messages['taken'] = sprintf( __( '%s is already booked during the requested time.', 'clubhouse-planner' ), $item->name );
		/* translators: %s: item name */
		$messages['blocked'] = sprintf( __( '%s is not available during the requested time.', 'clubhouse-planner' ), $item->name );
		$messages['order']   = __( 'The end must be after the start.', 'clubhouse-planner' );
		/* translators: %d: number of hours */
		$messages['notice'] = sprintf( __( 'Bookings must be requested at least %s before the start.', 'clubhouse-planner' ), CHP_Items::format_minutes( (int) $item->min_notice * 60 ) );
		/* translators: %s: length, e.g. 2 hours */
		$messages['min'] = sprintf( __( 'The shortest booking is %s.', 'clubhouse-planner' ), CHP_Items::format_minutes( (int) $item->min_duration ) );
		/* translators: %s: length, e.g. 2 days */
		$messages['max'] = sprintf( __( 'The longest booking is %s.', 'clubhouse-planner' ), CHP_Items::format_minutes( (int) $item->max_duration ) );
		return $messages;
	}

	private static function base_messages(): array {
		return array(
			'success' => __( 'Thank you! Your booking request has been received. A confirmation has been sent to your email.', 'clubhouse-planner' ),
			'invalid' => __( 'The form has expired. Please reload the page and try again.', 'clubhouse-planner' ),
			'missing' => __( 'Please fill in all required fields with valid values.', 'clubhouse-planner' ),
			'address' => __( 'Please enter a street and a valid street number (for example 6 or 6a).', 'clubhouse-planner' ),
			'past'    => __( 'The requested time has already passed.', 'clubhouse-planner' ),
			'taken'   => __( 'The club house is already booked during the requested time.', 'clubhouse-planner' ),
			'blocked' => __( 'The club house is not available during the requested time.', 'clubhouse-planner' ),
			'rules'   => __( 'Please confirm that you have read and understood the house rules.', 'clubhouse-planner' ),
			'limit'   => __( 'Too many booking requests. Please try again later.', 'clubhouse-planner' ),
			'error'   => __( 'Something went wrong. Please try again later.', 'clubhouse-planner' ),
		);
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NS,
			'/house-rules',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'rest_house_rules' ),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'rest_calendar' ),
				'args'                => array(
					'start' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_date' ),
					),
					'end'   => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_date' ),
					),
					'item'  => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/price',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'rest_price' ),
				'args'                => array(
					'item'  => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'start' => array( 'required' => true ),
					'end'   => array( 'required' => true ),
				),
			)
		);
	}

	/** Parses a datetime-local value (Y-m-dTH:i) as site time. */
	public static function parse_local( string $value ): ?DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $date : null;
	}

	/**
	 * Price and availability message for the booking form of a free-time item. The same checks are repeated on submit.
	 */
	public function rest_price( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$item = CHP_Items::get( (int) $request['item'] );
		if ( ! CHP_Pricing::is_priced( $item ) || ! (int) $item->active ) {
			return new WP_Error( 'chp_item', __( 'Unknown item.', 'clubhouse-planner' ), array( 'status' => 404 ) );
		}

		$start = self::parse_local( sanitize_text_field( (string) $request['start'] ) );
		$end   = self::parse_local( sanitize_text_field( (string) $request['end'] ) );
		if ( ! $start || ! $end ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => '',
				)
			);
		}

		$error = CHP_Items::period_error( $item, $start, $end );
		if ( ! $error ) {
			$start_at = $start->format( 'Y-m-d H:i:s' );
			$end_at   = $end->format( 'Y-m-d H:i:s' );
			if ( CHP_Blackouts::blocks_item( (int) $item->id, $start_at, $end_at ) ) {
				$error = 'blocked';
			} elseif ( ! CHP_Bookings::free_units( (int) $item->id, $start_at, $end_at ) ) {
				$error = 'taken';
			}
		}
		if ( $error ) {
			$messages = self::form_messages( $item );
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => $messages[ $error ] ?? '',
				)
			);
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				/* translators: %s: price, e.g. 150 kr */
				'message' => sprintf( __( 'Price: %s', 'clubhouse-planner' ), CHP_Bookings::format_fee( CHP_Pricing::calculate( $item, $start, $end ) ) ),
			)
		);
	}

	/**
	 * Title and content of the configured house rules page, for the pop-up in the booking form.
	 */
	public function rest_house_rules(): WP_REST_Response|WP_Error {
		$page = CHP_Settings::house_rules_page();
		if ( ! $page ) {
			return new WP_Error( 'chp_no_rules', __( 'Could not load the house rules.', 'clubhouse-planner' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'title'   => get_the_title( $page ),
				'content' => apply_filters( 'the_content', $page->post_content ),
			)
		);
	}

	public function is_date( mixed $value ): bool {
		return is_string( $value ) && (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}

	/**
	 * Public calendar data. Only periods and a coarse status are exposed, never personal data.
	 */
	public function rest_calendar( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$start = $request['start'] . ' 00:00:00';
		$end   = $request['end'] . ' 23:59:59';

		if ( strtotime( $end ) - strtotime( $start ) > 100 * DAY_IN_SECONDS || $end < $start ) {
			return new WP_Error( 'chp_range', __( 'Invalid date range.', 'clubhouse-planner' ), array( 'status' => 400 ) );
		}

		$statuses = CHP_Bookings::blocking_statuses();
		if ( CHP_Settings::get( 'show_pending' ) ) {
			$statuses[] = CHP_Bookings::PENDING;
		}

		$item = absint( $request['item'] ) ? CHP_Items::get( absint( $request['item'] ) ) : null;
		if ( ! $item ) {
			$item = CHP_Items::get( CHP_Items::default_id() );
		}
		$item_id = (int) $item->id;
		$rows    = CHP_Bookings::in_range( $start, $end, $statuses, $item_id );

		// Blocked days come first; the script shows them as "Not available" or the administrator's text.
		$blocked = array_map(
			static fn( array $day ): array => array(
				'date'   => $day['date'],
				'slot'   => 'unavailable',
				'start'  => '',
				'end'    => '',
				'status' => 'unavailable',
				'text'   => $day['text'],
			),
			CHP_Blackouts::calendar_days( $item_id, $start, $end )
		);

		// An item with several units also shows how many units are still free on each day.
		$availability = (int) $item->quantity > 1 ? $this->availability_entries( $rows, $start, $end, (int) $item->quantity ) : array();
		$blocked      = array_merge( $blocked, $availability );

		// Free-time bookings can span several days, so they are listed on every day they cover.
		if ( CHP_Pricing::is_priced( $item ) ) {
			return rest_ensure_response( array_merge( $blocked, $this->free_entries( $rows, $start, $end ) ) );
		}

		return rest_ensure_response(
			array_merge( $blocked, array_map(
				function ( object $b ): array {
					return array(
						'date'   => substr( $b->start_at, 0, 10 ),
						'slot'   => '' !== $b->slot ? $b->slot : CHP_Bookings::SLOT_FULL,
						'start'  => substr( $b->start_at, 11, 5 ),
						'end'    => substr( $b->end_at, 11, 5 ),
						'status' => CHP_Bookings::CONFIRMED === $b->status ? 'booked' : 'preliminary',
					);
				},
				$rows
			) )
		);
	}

	/**
	 * For an item with several units: per day with bookings, how many units are free at the busiest moment of the day
	 * ("at least this many are free all day"). Days without bookings get no entry.
	 *
	 * @param object[] $rows  Bookings overlapping the range.
	 * @param string   $start Range start (Y-m-d H:i:s).
	 * @param string   $end   Range end (Y-m-d H:i:s).
	 * @param int      $total Number of units.
	 */
	private function availability_entries( array $rows, string $start, string $end, int $total ): array {
		$tz      = wp_timezone();
		$day     = ( new DateTimeImmutable( $start, $tz ) )->setTime( 0, 0 );
		$last    = new DateTimeImmutable( $end, $tz );
		$entries = array();

		for ( $i = 0; $i < CHP_Blackouts::MAX_CALENDAR_DAYS && $day <= $last; $i++ ) {
			$next   = $day->modify( '+1 day' );
			$events = array();
			foreach ( $rows as $b ) {
				$from = max( new DateTimeImmutable( $b->start_at, $tz ), $day );
				$to   = min( new DateTimeImmutable( $b->end_at, $tz ), $next );
				if ( $from < $to ) {
					$events[] = array( $from->getTimestamp(), 1 );
					$events[] = array( $to->getTimestamp(), -1 );
				}
			}
			if ( $events ) {
				// Ends sort before starts at the same moment, so back-to-back bookings do not count as overlapping.
				usort( $events, static fn( array $a, array $b ): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1] );
				$now  = 0;
				$peak = 0;
				foreach ( $events as $event ) {
					$now += $event[1];
					$peak = max( $peak, $now );
				}
				$entries[] = array(
					'date'   => $day->format( 'Y-m-d' ),
					'slot'   => 'availability',
					'start'  => '',
					'end'    => '',
					'status' => 'availability',
					'free'   => max( 0, $total - $peak ),
					'total'  => $total,
				);
			}
			$day = $next;
		}
		return $entries;
	}

	/**
	 * Calendar entries for free-time bookings: one per day covered, with the times falling on that day.
	 *
	 * @param object[] $rows  Bookings overlapping the range.
	 * @param string   $start Range start (Y-m-d H:i:s).
	 * @param string   $end   Range end (Y-m-d H:i:s).
	 */
	private function free_entries( array $rows, string $start, string $end ): array {
		$tz         = wp_timezone();
		$range_from = new DateTimeImmutable( $start, $tz );
		$range_to   = new DateTimeImmutable( $end, $tz );
		$entries    = array();

		foreach ( $rows as $b ) {
			$from   = new DateTimeImmutable( $b->start_at, $tz );
			$to     = new DateTimeImmutable( $b->end_at, $tz );
			$day    = max( $from->setTime( 0, 0 ), $range_from->setTime( 0, 0 ) );
			$status = CHP_Bookings::CONFIRMED === $b->status ? 'booked' : 'preliminary';

			for ( $i = 0; $i < CHP_Blackouts::MAX_CALENDAR_DAYS && $day < $to && $day <= $range_to; $i++ ) {
				$next      = $day->modify( '+1 day' );
				$day_start = max( $from, $day );
				$day_end   = min( $to, $next );
				$entries[] = array(
					'date'   => $day->format( 'Y-m-d' ),
					'slot'   => 'free',
					'start'  => $day_start->format( 'H:i' ),
					'end'    => $day_end >= $next ? '24:00' : $day_end->format( 'H:i' ),
					'status' => $status,
				);
				$day = $next;
			}
		}
		return $entries;
	}

	public function handle_submit(): void {
		$redirect = remove_query_arg( 'chp_result', wp_get_referer() ? wp_get_referer() : home_url( '/' ) );

		$nonce = isset( $_POST['chp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['chp_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'chp_submit_booking' ) ) {
			$this->back( $redirect, 'invalid' );
		}

		// Honeypot: bots fill hidden fields. Pretend success.
		if ( ! empty( $_POST['chp_website'] ) ) {
			$this->back( $redirect, 'success' );
		}

		// Bots submit instantly; humans need a few seconds. Pretend success.
		if ( ! self::form_age_ok( sanitize_text_field( wp_unslash( $_POST['chp_ts'] ?? '' ) ) ) ) {
			$this->back( $redirect, 'success' );
		}

		$post    = wp_unslash( $_POST );
		$name    = sanitize_text_field( $post['chp_name'] ?? '' );
		$email   = sanitize_email( $post['chp_email'] ?? '' );
		$phone   = sanitize_text_field( $post['chp_phone'] ?? '' );

		// The item comes from the shortcode's hidden field; without one the default item is used.
		$wanted = absint( $post['chp_item'] ?? 0 );
		$item   = CHP_Items::get( $wanted ? $wanted : CHP_Items::default_id() );
		if ( ! $item || ! (int) $item->active ) {
			$this->back( $redirect, 'error' );
		}
		$item_id = (int) $item->id;
		$free    = CHP_Pricing::is_priced( $item );

		// The address (street list and standard fee) is asked per item.
		$address = CHP_Items::asks_address( $item ) ? $this->build_address( $post ) : '';

		if ( CHP_Settings::house_rules_page() && empty( $post['chp_rules'] ) ) {
			$this->back( $redirect, 'rules' );
		}
		$message = sanitize_textarea_field( $post['chp_message'] ?? '' );

		// The purpose field depends on the item: a list, free text, or not asked (then the item name is used).
		$purpose = sanitize_text_field( $post['chp_purpose'] ?? '' );
		$purpose_ok = true;
		if ( 'list' === $item->purpose_mode ) {
			$purpose_ok = in_array( $purpose, CHP_Items::purpose_options( $item ), true );
		} elseif ( 'text' !== $item->purpose_mode ) {
			$purpose = $item->name;
		}

		$slot = '';
		if ( $free ) {
			$start = self::parse_local( sanitize_text_field( $post['chp_start'] ?? '' ) );
			$end   = self::parse_local( sanitize_text_field( $post['chp_end'] ?? '' ) );
			if ( ! $start || ! $end ) {
				$this->back( $redirect, 'missing' );
			}
			$period = array( $start, $end );
		} else {
			$slot   = sanitize_key( $post['chp_slot'] ?? '' );
			$period = CHP_Bookings::slot_period( sanitize_text_field( $post['chp_date'] ?? '' ), $slot );
		}

		if ( '' === $name || ! is_email( $email ) || ! $period || ! $purpose_ok ) {
			$this->back( $redirect, 'missing' );
		}
		if ( null === $address ) {
			$this->back( $redirect, 'address' );
		}

		[ $start, $end ] = $period;
		if ( $free ) {
			$error = CHP_Items::period_error( $item, $start, $end );
			if ( $error ) {
				$this->back( $redirect, $error );
			}
		} elseif ( $start < new DateTimeImmutable( 'now', wp_timezone() ) ) {
			$this->back( $redirect, 'past' );
		}

		$start_at = $start->format( 'Y-m-d H:i:s' );
		$end_at   = $end->format( 'Y-m-d H:i:s' );

		if ( CHP_Blackouts::blocks_item( $item_id, $start_at, $end_at ) ) {
			$this->back( $redirect, 'blocked' );
		}
		if ( ! CHP_Bookings::free_units( $item_id, $start_at, $end_at ) ) {
			$this->back( $redirect, 'taken' );
		}

		if ( self::rate_limited( $email ) ) {
			$this->back( $redirect, 'limit' );
		}

		$booking = array(
			'item_id'  => $item_id,
			'name'     => mb_substr( $name, 0, 190 ),
			'email'    => $email,
			'phone'    => mb_substr( $phone, 0, 50 ),
			'address'  => mb_substr( $address, 0, 190 ),
			'slot'     => $slot,
			'start_at' => $start_at,
			'end_at'   => $end_at,
			'purpose'  => mb_substr( $purpose, 0, 190 ),
			'message'  => $message,
		);
		if ( $free ) {
			// The price is known from the start, so the requester sees it in the first email.
			$booking['fee'] = CHP_Pricing::calculate( $item, $start, $end );
		}
		$id = CHP_Bookings::create( $booking );

		if ( ! $id ) {
			$this->back( $redirect, 'error' );
		}

		$booking = CHP_Bookings::get( $id );
		CHP_Mailer::request_received( $booking );
		CHP_Mailer::notify_housekeepers( $booking );

		$this->back( $redirect, 'success' );
	}

	/**
	 * Signed render timestamp for the form, checked on submit by form_age_ok().
	 */
	public static function form_timestamp(): string {
		$now = time();
		return $now . '.' . wp_hash( 'chp_ts|' . $now );
	}

	private static function form_age_ok( string $value ): bool {
		$parts = explode( '.', $value, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! hash_equals( wp_hash( 'chp_ts|' . $parts[0] ), $parts[1] ) ) {
			return false;
		}
		return time() - (int) $parts[0] >= self::MIN_FORM_SECONDS;
	}

	/**
	 * Counts this attempt and reports whether the IP or email has exceeded its hourly quota.
	 */
	private static function rate_limited( string $email ): bool {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$keys = array(
			'chp_rl_ip_' . md5( $ip )                  => self::MAX_PER_IP,
			'chp_rl_em_' . md5( strtolower( $email ) ) => self::MAX_PER_EMAIL,
		);

		$limited = false;
		foreach ( $keys as $key => $max ) {
			$count = (int) get_transient( $key );
			if ( $count >= $max ) {
				$limited = true;
				continue;
			}
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}
		return $limited;
	}

	/**
	 * Street (listed or typed) + street number, e.g. "Storgatan 6A". Null if invalid.
	 */
	private function build_address( array $post ): ?string {
		$choice  = sanitize_text_field( $post['chp_street'] ?? self::OTHER_STREET );
		$streets = CHP_Settings::local_streets();

		if ( self::OTHER_STREET === $choice || ! $streets ) {
			$street = sanitize_text_field( $post['chp_street_other'] ?? '' );
		} elseif ( in_array( $choice, $streets, true ) ) {
			$street = $choice;
		} else {
			$street = '';
		}

		$number = sanitize_text_field( $post['chp_street_no'] ?? '' );
		if ( '' === $street || ! preg_match( '/^(\d{1,5})\s?(\p{L}?)$/u', $number, $m ) ) {
			return null;
		}

		return mb_substr( $street, 0, 150 ) . ' ' . $m[1] . mb_strtoupper( $m[2] );
	}

	private function back( string $url, string $result ): never {
		wp_safe_redirect( add_query_arg( 'chp_result', $result, $url ) . '#chp-booking-form' );
		exit;
	}
}
