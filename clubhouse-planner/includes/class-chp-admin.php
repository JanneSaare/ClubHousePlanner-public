<?php
defined( 'ABSPATH' ) || exit;

class CHP_Admin {

	const CAP = CHP_Activator::CAP;

	/** The bookings list shows bookings that ended at most this many days ago, unless all dates are requested. */
	const RECENT_DAYS = 14;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_chp_booking_action', array( $this, 'handle_action' ) );
		add_action( 'admin_post_chp_add_booking', array( $this, 'handle_add_booking' ) );
		add_action( 'admin_post_chp_resend_payment', array( $this, 'handle_resend_payment' ) );
		add_action( 'admin_post_chp_reassign_unit', array( $this, 'handle_reassign_unit' ) );
		add_action( 'admin_post_chp_save_item', array( $this, 'handle_save_item' ) );
		add_action( 'admin_post_chp_save_blackout', array( $this, 'handle_save_blackout' ) );
		add_action( 'admin_post_chp_delete_blackout', array( $this, 'handle_delete_blackout' ) );
		add_filter( 'gettext_with_context_default', array( $this, 'translate_role_name' ), 10, 3 );
	}

	/**
	 * WordPress translates role names in the 'default' domain only, so map ours to the plugin domain.
	 */
	public function translate_role_name( string $translation, string $text, string $context ): string {
		if ( 'User role' === $context && CHP_Activator::ROLE_NAME === $text ) {
			return __( 'Club House Housekeeper', 'clubhouse-planner' );
		}
		return $translation;
	}

	public function menu(): void {
		$counts  = CHP_Bookings::count_by_status();
		$pending = isset( $counts[ CHP_Bookings::PENDING ] ) ? $counts[ CHP_Bookings::PENDING ] : 0;
		$bubble  = $pending ? sprintf( ' <span class="awaiting-mod">%d</span>', $pending ) : '';

		add_menu_page(
			__( 'Club House', 'clubhouse-planner' ),
			__( 'Club House', 'clubhouse-planner' ) . $bubble,
			self::CAP,
			'chp-bookings',
			array( $this, 'render_bookings' ),
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page( 'chp-bookings', __( 'Bookings', 'clubhouse-planner' ), __( 'Bookings', 'clubhouse-planner' ), self::CAP, 'chp-bookings', array( $this, 'render_bookings' ) );
		add_submenu_page( 'chp-bookings', __( 'Add booking', 'clubhouse-planner' ), __( 'Add booking', 'clubhouse-planner' ), self::CAP, 'chp-add-booking', array( $this, 'render_add_booking' ) );
		add_submenu_page( 'chp-bookings', __( 'Blocked periods', 'clubhouse-planner' ), __( 'Blocked periods', 'clubhouse-planner' ), self::CAP, 'chp-blackouts', array( $this, 'render_blackouts' ) );
		add_submenu_page( 'chp-bookings', __( 'Items', 'clubhouse-planner' ), __( 'Items', 'clubhouse-planner' ), 'manage_options', 'chp-items', array( $this, 'render_items' ) );
		add_submenu_page( 'chp-bookings', __( 'Instructions', 'clubhouse-planner' ), __( 'Instructions', 'clubhouse-planner' ), self::CAP, 'chp-help', array( $this, 'render_help' ) );
		add_submenu_page( 'chp-bookings', __( 'Settings', 'clubhouse-planner' ), __( 'Settings', 'clubhouse-planner' ), 'manage_options', 'chp-settings', array( $this, 'render_settings' ) );
	}

	public function enqueue( string $hook ): void {
		if ( str_contains( $hook, 'chp-' ) ) {
			wp_enqueue_style( 'chp-admin', CHP_URL . 'assets/css/admin.css', array(), CHP_VERSION );
		}
		if ( str_contains( $hook, 'chp-settings' ) ) {
			wp_enqueue_script( 'chp-mail-editor', CHP_URL . 'assets/js/mail-editor.js', array(), CHP_VERSION, true );
			$defaults = array();
			foreach ( CHP_Mailer::email_defaults() as $key => $default ) {
				$defaults[ $key ] = array(
					'subject' => $default['subject'],
					'body'    => $default['body'],
				);
			}
			wp_localize_script( 'chp-mail-editor', 'chpMailDefaults', $defaults );
		}
	}

	public function register_settings(): void {
		register_setting(
			'chp_settings_group',
			CHP_Settings::OPTION,
			array( 'sanitize_callback' => array( 'CHP_Settings', 'sanitize' ) )
		);
	}

	/**
	 * Action buttons shown on the booking detail page, keyed by target status.
	 */
	public static function actions(): array {
		return array(
			CHP_Bookings::AWAITING_PAYMENT => array(
				'label'  => __( 'Approve – send payment information', 'clubhouse-planner' ),
				'note'   => __( 'Additional message to the requester (optional)', 'clubhouse-planner' ),
				'button' => 'button-primary',
			),
			CHP_Bookings::CONFIRMED        => array(
				'label'  => __( 'Payment received – confirm booking', 'clubhouse-planner' ),
				'note'   => __( 'Additional message to the requester (optional)', 'clubhouse-planner' ),
				'button' => 'button-primary',
			),
			CHP_Bookings::DENIED           => array(
				'label'  => __( 'Deny request', 'clubhouse-planner' ),
				'note'   => __( 'Reason (sent to the requester)', 'clubhouse-planner' ),
				'button' => 'button-secondary',
			),
			CHP_Bookings::CANCELLED        => array(
				'label'  => __( 'Cancel booking', 'clubhouse-planner' ),
				'note'   => __( 'Reason (sent to the requester)', 'clubhouse-planner' ),
				'button' => 'button-secondary',
			),
		);
	}

	/**
	 * Colored booking type badge (escaped HTML). $size: 'small' (no times), 'normal' or 'large'.
	 */
	public static function slot_badge( object $booking, string $size = 'normal' ): string {
		$slots = CHP_Bookings::slot_definitions();
		$slot  = $booking->slot ?? '';
		if ( ! isset( $slots[ $slot ] ) ) {
			return '';
		}

		$time = 'small' === $size ? '' : ' <small>' . esc_html( CHP_Bookings::booking_times( $booking ) ) . '</small>';
		return sprintf(
			'<span class="chp-slot-badge chp-slot-badge--%1$s chp-slot-badge--%2$s">%3$s%4$s</span>',
			esc_attr( $slot ),
			esc_attr( $size ),
			esc_html( $slots[ $slot ]['label'] ),
			$time
		);
	}

	public static function booking_date( object $booking ): string {
		$format = get_option( 'date_format' );
		return mysql2date( str_contains( $format, 'l' ) ? $format : 'l ' . $format, $booking->start_at );
	}

	public static function neighbour_labels(): array {
		return array(
			-1 => __( 'Day before', 'clubhouse-planner' ),
			0  => __( 'Same day', 'clubhouse-planner' ),
			1  => __( 'Day after', 'clubhouse-planner' ),
		);
	}

	public static function notices(): array {
		return array(
			'updated' => array( 'success', __( 'Booking updated and email sent to the requester.', 'clubhouse-planner' ) ),
			'resent'  => array( 'success', __( 'Payment information sent again.', 'clubhouse-planner' ) ),
			'silent'  => array( 'success', __( 'Booking updated. No email was sent.', 'clubhouse-planner' ) ),
			'nomail'  => array( 'warning', __( 'Booking updated, but the email could not be sent.', 'clubhouse-planner' ) ),
			'invalid' => array( 'error', __( 'That action is not allowed for the current status.', 'clubhouse-planner' ) ),
			'overlap' => array( 'error', __( 'Another approved booking overlaps this period. Deny this request or cancel the other booking first.', 'clubhouse-planner' ) ),
			'failed'  => array( 'error', __( 'The booking could not be updated.', 'clubhouse-planner' ) ),
			'added'   => array( 'success', __( 'Booking added. No email was sent.', 'clubhouse-planner' ) ),
			'fee'     => array( 'error', __( 'Enter the fee before approving a booking from an address that is not on the standard-fee list.', 'clubhouse-planner' ) ),
			'blocked' => array( 'error', __( 'This period is in a blocked period, so the booking cannot be approved. Deny the request or remove the blocked period first.', 'clubhouse-planner' ) ),
			'unit'    => array( 'success', __( 'The unit was changed.', 'clubhouse-planner' ) ),
			'unit_taken' => array( 'error', __( 'That unit is already booked during this period.', 'clubhouse-planner' ) ),
		);
	}

	/** Messages for the blocked periods page, keyed by notice or error code. */
	public static function blackout_notices(): array {
		return array(
			'saved'   => array( 'success', __( 'The blocked period was saved.', 'clubhouse-planner' ) ),
			'deleted' => array( 'success', __( 'The blocked period was removed.', 'clubhouse-planner' ) ),
			'item'    => array( 'error', __( 'Choose an item from the list.', 'clubhouse-planner' ) ),
			'unit'    => array( 'error', __( 'That unit does not exist for the chosen item.', 'clubhouse-planner' ) ),
			'dates'   => array( 'error', __( 'Enter a valid start and end date and time.', 'clubhouse-planner' ) ),
			'range'   => array( 'error', __( 'The end must be after the start.', 'clubhouse-planner' ) ),
			'span'    => array( 'error', __( 'A period that repeats every year must be shorter than one year.', 'clubhouse-planner' ) ),
			'failed'  => array( 'error', __( 'The blocked period could not be saved.', 'clubhouse-planner' ) ),
		);
	}

	public function render_blackouts(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'clubhouse-planner' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
		$notice  = isset( $_GET['chp_notice'] ) ? sanitize_key( wp_unslash( $_GET['chp_notice'] ) ) : '';
		$edit    = isset( $_GET['blackout'] ) ? sanitize_key( wp_unslash( $_GET['blackout'] ) ) : '';
		$checked = isset( $_GET['checked'] ) ? absint( $_GET['checked'] ) : 0;
		// phpcs:enable

		$blackout = null;
		if ( 'new' === $edit ) {
			$blackout = (object) array(
				'id'          => 0,
				'item_id'     => null,
				'unit'        => null,
				'start_at'    => '',
				'end_at'      => '',
				'yearly'      => 0,
				'reason'      => '',
				'public_text' => null,
			);
		} elseif ( '' !== $edit ) {
			$blackout = CHP_Blackouts::get( absint( $edit ) );
			if ( ! $blackout ) {
				wp_die( esc_html__( 'Blocked period not found.', 'clubhouse-planner' ), 404 );
			}
		}

		// Right after saving: bookings that fall inside the period are listed so the housekeeper can handle them.
		$conflicts = array();
		if ( $checked ) {
			$saved = CHP_Blackouts::get( $checked );
			if ( $saved ) {
				$conflicts = CHP_Blackouts::conflicting_bookings( $saved );
			}
		}

		$blackouts = CHP_Blackouts::all();
		$items     = CHP_Items::all();
		include CHP_DIR . 'templates/admin/blackouts.php';
	}

	public function handle_save_blackout(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}

		$id = isset( $_POST['blackout_id'] ) ? absint( $_POST['blackout_id'] ) : 0;
		check_admin_referer( 'chp_save_blackout_' . $id );

		$base = admin_url( 'admin.php?page=chp-blackouts' );
		$data = CHP_Blackouts::sanitize( wp_unslash( $_POST ) );
		if ( is_wp_error( $data ) ) {
			wp_safe_redirect( add_query_arg( array( 'blackout' => $id ? $id : 'new', 'chp_notice' => $data->get_error_code() ), $base ) );
			exit;
		}

		$saved = CHP_Blackouts::save( $data, $id );
		if ( ! $saved ) {
			wp_safe_redirect( add_query_arg( array( 'blackout' => $id ? $id : 'new', 'chp_notice' => 'failed' ), $base ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'chp_notice' => 'saved', 'checked' => $saved ), $base ) );
		exit;
	}

	public function handle_delete_blackout(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}

		$id = isset( $_POST['blackout_id'] ) ? absint( $_POST['blackout_id'] ) : 0;
		check_admin_referer( 'chp_delete_blackout_' . $id );

		CHP_Blackouts::delete( $id );
		wp_safe_redirect( add_query_arg( 'chp_notice', 'deleted', admin_url( 'admin.php?page=chp-blackouts' ) ) );
		exit;
	}

	/** Messages for the items page, keyed by notice or error code. */
	public static function item_notices(): array {
		return array(
			'saved'           => array( 'success', __( 'The item was saved.', 'clubhouse-planner' ) ),
			'name'            => array( 'error', __( 'Enter a name for the item.', 'clubhouse-planner' ) ),
			/* translators: %d: highest number of units */
			'quantity'        => array( 'error', sprintf( __( 'The quantity must be a whole number from 1 to %d.', 'clubhouse-planner' ), CHP_Items::MAX_QUANTITY ) ),
			'quantity_in_use' => array( 'error', __( 'The quantity cannot be lowered that far: a unit that would be removed has a booking that has not ended. Move or cancel the booking first.', 'clubhouse-planner' ) ),
			'last_active'     => array( 'error', __( 'At least one item must stay active.', 'clubhouse-planner' ) ),
			'purposes'        => array( 'error', __( 'Enter at least one purpose, one per line, or choose another purpose setting.', 'clubhouse-planner' ) ),
			'duration'        => array( 'error', __( 'The shortest booking cannot be longer than the longest booking.', 'clubhouse-planner' ) ),
			'failed'          => array( 'error', __( 'The item could not be saved.', 'clubhouse-planner' ) ),
		);
	}

	public function render_items(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'clubhouse-planner' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
		$notice   = isset( $_GET['chp_notice'] ) ? sanitize_key( wp_unslash( $_GET['chp_notice'] ) ) : '';
		$edit     = isset( $_GET['item'] ) ? sanitize_key( wp_unslash( $_GET['item'] ) ) : '';
		// phpcs:enable

		$item = null;
		if ( 'new' === $edit ) {
			$item = (object) array(
				'id'           => 0,
				'name'         => '',
				'description'  => '',
				'active'       => 1,
				'sort_order'   => 0,
				'quantity'     => 1,
				'purpose_mode' => 'none',
				'purposes'     => null,
				'key_box_code' => null,
				'booking_mode' => CHP_Items::MODE_FREE,
				'fee_unit'     => CHP_Pricing::FEE_DAY,
				'fee_weekday'  => 0,
				'fee_weekend'  => null,
				'weekend_days' => '6,7',
				'min_duration' => null,
				'max_duration' => null,
				'min_notice'   => null,
			);
		} elseif ( '' !== $edit ) {
			$item = CHP_Items::get( absint( $edit ) );
			if ( ! $item ) {
				wp_die( esc_html__( 'Item not found.', 'clubhouse-planner' ), 404 );
			}
		}

		$items = CHP_Items::all();
		include CHP_DIR . 'templates/admin/items.php';
	}

	public function handle_save_item(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}

		$id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		check_admin_referer( 'chp_save_item_' . $id );

		$base = admin_url( 'admin.php?page=chp-items' );
		$data = CHP_Items::sanitize( wp_unslash( $_POST ), $id );
		if ( is_wp_error( $data ) ) {
			wp_safe_redirect( add_query_arg( array( 'item' => $id ? $id : 'new', 'chp_notice' => $data->get_error_code() ), $base ) );
			exit;
		}

		$saved = CHP_Items::save( $data, $id );
		wp_safe_redirect( $saved ? add_query_arg( 'chp_notice', 'saved', $base ) : add_query_arg( array( 'item' => $id ? $id : 'new', 'chp_notice' => 'failed' ), $base ) );
		exit;
	}

	/** Move a booking to another unit of its item. */
	public function handle_reassign_unit(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}

		$id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		check_admin_referer( 'chp_reassign_unit_' . $id );

		$booking = CHP_Bookings::get( $id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Booking not found.', 'clubhouse-planner' ), 404 );
		}

		$unit = isset( $_POST['unit'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['unit'] ) ) ) : '';
		$url  = admin_url( 'admin.php?page=chp-bookings&booking=' . $id );
		$this->redirect( $url, CHP_Bookings::reassign_unit( $booking, $unit ) ? 'unit' : 'unit_taken' );
	}

	public function render_bookings(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'clubhouse-planner' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
		$booking_id = isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0;
		$notice     = isset( $_GET['chp_notice'] ) ? sanitize_key( wp_unslash( $_GET['chp_notice'] ) ) : '';

		if ( $booking_id ) {
			$booking = CHP_Bookings::get( $booking_id );
			if ( ! $booking ) {
				wp_die( esc_html__( 'Booking not found.', 'clubhouse-planner' ), 404 );
			}
			$log = CHP_Bookings::get_log( $booking_id );
			include CHP_DIR . 'templates/admin/booking-detail.php';
			return;
		}

		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$all      = isset( $_GET['period'] ) && 'all' === sanitize_key( wp_unslash( $_GET['period'] ) );
		// phpcs:enable
		$per_page = 20;
		// By default: upcoming bookings and those that ended at most RECENT_DAYS ago.
		$recent_from  = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-' . self::RECENT_DAYS . ' days' )->format( 'Y-m-d H:i:s' );
		$from         = $all ? '' : $recent_from;
		$counts       = CHP_Bookings::count_by_status( $from );
		$recent_total = array_sum( CHP_Bookings::count_by_status( $recent_from ) );
		$all_total    = array_sum( CHP_Bookings::count_by_status() );
		// No status in the URL means the open ones; 'all' shows everything.
		$wanted       = '' === $status ? CHP_Bookings::open_statuses() : ( 'all' === $status ? array() : array( $status ) );
		$now          = current_time( 'mysql' );
		$open_total   = CHP_Bookings::count_open( $from, $now );
		$total        = '' === $status ? $open_total : ( $wanted ? array_sum( array_intersect_key( $counts, array_flip( $wanted ) ) ) : array_sum( $counts ) );
		$bookings     = CHP_Bookings::query( $wanted, $paged, $per_page, $from, '' === $status ? $now : '' );
		$pages    = (int) ceil( $total / $per_page );

		include CHP_DIR . 'templates/admin/bookings-list.php';
	}

	public static function add_errors(): array {
		return array(
			'missing' => __( 'Enter a name and choose a valid date and booking type.', 'clubhouse-planner' ),
			'email'   => __( 'The email address is not valid.', 'clubhouse-planner' ),
			'taken'   => __( 'The club house is already booked during that time.', 'clubhouse-planner' ),
			'blocked' => __( 'That time is in a blocked period. Remove or change the blocked period first.', 'clubhouse-planner' ),
			'failed'  => __( 'The booking could not be saved.', 'clubhouse-planner' ),
		);
	}

	public function render_add_booking(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'clubhouse-planner' ), 403 );
		}
		$error = isset( $_GET['chp_error'] ) ? sanitize_key( wp_unslash( $_GET['chp_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		include CHP_DIR . 'templates/admin/booking-add.php';
	}

	public function render_help(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'clubhouse-planner' ), 403 );
		}
		include CHP_DIR . 'templates/admin/help.php';
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'clubhouse-planner' ), 403 );
		}
		$settings = CHP_Settings::get();
		include CHP_DIR . 'templates/admin/settings.php';
	}

	public function handle_action(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}

		$id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		check_admin_referer( 'chp_booking_action_' . $id );

		$to   = isset( $_POST['transition'] ) ? sanitize_key( wp_unslash( $_POST['transition'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		$booking = CHP_Bookings::get( $id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Booking not found.', 'clubhouse-planner' ), 404 );
		}

		$url = admin_url( 'admin.php?page=chp-bookings&booking=' . $id );

		if ( ! CHP_Bookings::can_transition( $booking->status, $to ) ) {
			$this->redirect( $url, 'invalid' );
		}

		if ( CHP_Bookings::AWAITING_PAYMENT === $to && ! CHP_Bookings::claim_unit( $booking ) ) {
			$blocked = CHP_Blackouts::blocks_item( (int) $booking->item_id, $booking->start_at, $booking->end_at );
			$this->redirect( $url, $blocked ? 'blocked' : 'overlap' );
		}

		// Free of charge: no payment step, so the booking is confirmed straight away.
		$free = CHP_Bookings::AWAITING_PAYMENT === $to && ! empty( $_POST['free'] );
		if ( $free ) {
			if ( ! CHP_Bookings::set_fee( $id, 0 ) ) {
				$this->redirect( $url, 'failed' );
			}
			$to = CHP_Bookings::CONFIRMED;
			if ( '' === $note ) {
				$note = __( 'Free of charge – booking confirmed without payment.', 'clubhouse-planner' );
			}
		} elseif ( CHP_Bookings::AWAITING_PAYMENT === $to ) {
			$fee_input = isset( $_POST['fee'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['fee'] ) ) ) : '';
			if ( '' === $fee_input ) {
				$calculated = CHP_Pricing::for_booking( $booking );
				if ( null !== $calculated ) {
					// Free-time items are priced from their price list, whatever the address.
					$fee_input = (string) ( null !== $booking->fee ? (int) $booking->fee : $calculated );
				} elseif ( ! CHP_Bookings::is_local( $booking ) ) {
					$this->redirect( $url, 'fee' );
				} else {
					$fee_input = (string) CHP_Settings::standard_fee( (string) $booking->slot );
				}
			}
			if ( ! CHP_Bookings::set_fee( $id, absint( $fee_input ) ) ) {
				$this->redirect( $url, 'failed' );
			}
		}

		if ( ! CHP_Bookings::set_status( $id, $to, $note ) ) {
			$this->redirect( $url, 'failed' );
		}

		$booking = CHP_Bookings::get( $id );

		$no_email = ( ! empty( $_POST['no_email'] ) || '' === (string) $booking->email )
			&& in_array( $to, array( CHP_Bookings::DENIED, CHP_Bookings::CANCELLED ), true );
		if ( $no_email ) {
			$this->redirect( $url, 'silent' );
		}

		switch ( $to ) {
			case CHP_Bookings::AWAITING_PAYMENT:
				$sent = CHP_Mailer::approved( $booking, $note );
				break;
			case CHP_Bookings::CONFIRMED:
				$sent = CHP_Mailer::confirmed( $booking, $note );
				break;
			case CHP_Bookings::DENIED:
				$sent = CHP_Mailer::denied( $booking, $note );
				break;
			case CHP_Bookings::CANCELLED:
				$sent = CHP_Mailer::cancelled( $booking, $note );
				break;
			default:
				$sent = false;
		}

		$this->redirect( $url, $sent ? 'updated' : 'nomail' );
	}

	/**
	 * Add a booking on behalf of someone: it is confirmed straight away and no email is sent.
	 */
	public function handle_add_booking(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}
		check_admin_referer( 'chp_add_booking' );

		$back = static function ( string $error ): never {
			wp_safe_redirect( add_query_arg( 'chp_error', $error, admin_url( 'admin.php?page=chp-add-booking' ) ) );
			exit;
		};

		$post    = wp_unslash( $_POST );
		$name    = sanitize_text_field( $post['name'] ?? '' );
		$email   = sanitize_email( $post['email'] ?? '' );
		$slot    = sanitize_key( $post['slot'] ?? '' );
		$period  = CHP_Bookings::slot_period( sanitize_text_field( $post['date'] ?? '' ), $slot );
		$fee_in  = trim( sanitize_text_field( $post['fee'] ?? '' ) );
		$purpose = sanitize_text_field( $post['purpose'] ?? '' );

		if ( '' === $name || ! $period ) {
			$back( 'missing' );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			$back( 'email' );
		}

		[ $start, $end ] = $period;
		$start_at        = $start->format( 'Y-m-d H:i:s' );
		$end_at          = $end->format( 'Y-m-d H:i:s' );
		$item_id = CHP_Items::default_id();
		if ( CHP_Blackouts::blocks_item( $item_id, $start_at, $end_at ) ) {
			$back( 'blocked' );
		}
		if ( ! CHP_Bookings::free_units( $item_id, $start_at, $end_at ) ) {
			$back( 'taken' );
		}

		$id = CHP_Bookings::create(
			array(
				'item_id'  => $item_id,
				'status'   => CHP_Bookings::CONFIRMED,
				'name'     => mb_substr( $name, 0, 190 ),
				'email'    => $email,
				'phone'    => mb_substr( sanitize_text_field( $post['phone'] ?? '' ), 0, 50 ),
				'address'  => mb_substr( sanitize_text_field( $post['address'] ?? '' ), 0, 190 ),
				'slot'     => $slot,
				'start_at' => $start_at,
				'end_at'   => $end_at,
				'purpose'  => mb_substr( $purpose, 0, 190 ),
				'message'  => sanitize_textarea_field( $post['message'] ?? '' ),
				'fee'      => '' === $fee_in ? 0 : absint( $fee_in ),
				'log'      => __( 'Booking added by a housekeeper. No email was sent.', 'clubhouse-planner' ),
				'user_id'  => get_current_user_id(),
			)
		);
		if ( ! $id ) {
			$back( 'failed' );
		}

		$this->redirect( admin_url( 'admin.php?page=chp-bookings&booking=' . $id ), 'added' );
	}

	/**
	 * Send the payment instructions again for a booking that is awaiting payment. Logged in the history.
	 */
	public function handle_resend_payment(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clubhouse-planner' ), 403 );
		}

		$id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		check_admin_referer( 'chp_resend_payment_' . $id );

		$booking = CHP_Bookings::get( $id );
		if ( ! $booking ) {
			wp_die( esc_html__( 'Booking not found.', 'clubhouse-planner' ), 404 );
		}

		$url = admin_url( 'admin.php?page=chp-bookings&booking=' . $id );
		if ( CHP_Bookings::AWAITING_PAYMENT !== $booking->status ) {
			$this->redirect( $url, 'invalid' );
		}

		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$sent = CHP_Mailer::approved( $booking, $note );
		$log  = $sent
			? __( 'Payment information sent again.', 'clubhouse-planner' )
			: __( 'Payment information resend failed – the email could not be sent.', 'clubhouse-planner' );
		CHP_Bookings::log(
			$id,
			CHP_Bookings::AWAITING_PAYMENT,
			'' !== $note ? $log . "\n" . $note : $log,
			get_current_user_id()
		);

		$this->redirect( $url, $sent ? 'resent' : 'nomail' );
	}

	private function redirect( string $url, string $notice ): never {
		wp_safe_redirect( add_query_arg( 'chp_notice', $notice, $url ) );
		exit;
	}
}
