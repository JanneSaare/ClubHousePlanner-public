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
					'loadError'   => __( 'Could not load the calendar.', 'clubhouse-planner' ),
				),
			)
		);
	}

	public function render_calendar(): string {
		wp_enqueue_style( 'chp-public' );
		wp_enqueue_script( 'chp-calendar' );

		ob_start();
		include CHP_DIR . 'templates/calendar.php';
		return (string) ob_get_clean();
	}

	public function render_form(): string {
		wp_enqueue_style( 'chp-public' );
		wp_enqueue_script( 'chp-booking-form' );

		$result = isset( $_GET['chp_result'] ) ? sanitize_key( wp_unslash( $_GET['chp_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		include CHP_DIR . 'templates/booking-form.php';
		return (string) ob_get_clean();
	}

	public static function form_messages(): array {
		return array(
			'success' => __( 'Thank you! Your booking request has been received. A confirmation has been sent to your email.', 'clubhouse-planner' ),
			'invalid' => __( 'The form has expired. Please reload the page and try again.', 'clubhouse-planner' ),
			'missing' => __( 'Please fill in all required fields with valid values.', 'clubhouse-planner' ),
			'address' => __( 'Please enter a street and a valid street number (for example 6 or 6a).', 'clubhouse-planner' ),
			'past'    => __( 'The requested time has already passed.', 'clubhouse-planner' ),
			'taken'   => __( 'The club house is already booked during the requested time.', 'clubhouse-planner' ),
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
				),
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

		$rows = CHP_Bookings::in_range( $start, $end, $statuses, CHP_Items::default_id() );

		return rest_ensure_response(
			array_map(
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
			)
		);
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
		$address = $this->build_address( $post );

		if ( CHP_Settings::house_rules_page() && empty( $post['chp_rules'] ) ) {
			$this->back( $redirect, 'rules' );
		}
		$purpose = sanitize_text_field( $post['chp_purpose'] ?? '' );
		$message = sanitize_textarea_field( $post['chp_message'] ?? '' );
		$slot    = sanitize_key( $post['chp_slot'] ?? '' );
		$period  = CHP_Bookings::slot_period( sanitize_text_field( $post['chp_date'] ?? '' ), $slot );

		if ( '' === $name || ! is_email( $email ) || ! $period || ! in_array( $purpose, CHP_Bookings::purposes(), true ) ) {
			$this->back( $redirect, 'missing' );
		}
		if ( null === $address ) {
			$this->back( $redirect, 'address' );
		}

		[ $start, $end ] = $period;
		if ( $start < new DateTimeImmutable( 'now', wp_timezone() ) ) {
			$this->back( $redirect, 'past' );
		}

		$start_at = $start->format( 'Y-m-d H:i:s' );
		$end_at   = $end->format( 'Y-m-d H:i:s' );

		$item_id = CHP_Items::default_id();
		if ( ! CHP_Bookings::free_units( $item_id, $start_at, $end_at ) ) {
			$this->back( $redirect, 'taken' );
		}

		if ( self::rate_limited( $email ) ) {
			$this->back( $redirect, 'limit' );
		}

		$id = CHP_Bookings::create(
			array(
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
			)
		);

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
