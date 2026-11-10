<?php
defined( 'ABSPATH' ) || exit;

class CHP_Mailer {

	/** Step 1a: confirmation to the requester that the request was received. */
	public static function request_received( object $booking ): bool {
		return self::deliver( 'request_received', $booking, $booking->email );
	}

	/** Step 1b: notify housekeepers about a new request. */
	public static function notify_housekeepers( object $booking ): bool {
		$recipients = CHP_Settings::notify_recipients();
		if ( empty( $recipients ) ) {
			return false;
		}

		return self::deliver( 'new_request', $booking, $recipients, '', $booking->email );
	}

	/** Step 2: request approved, payment instructions sent. */
	public static function approved( object $booking, string $note = '' ): bool {
		return self::deliver( 'approved', $booking, $booking->email, $note );
	}

	/** Step 3: payment received, booking fully confirmed. */
	public static function confirmed( object $booking, string $note = '' ): bool {
		return self::deliver( 'confirmed', $booking, $booking->email, $note );
	}

	/** Heads-up to the treasurer: a booking is close and still unpaid. */
	public static function treasurer_reminder( object $booking, array $to ): bool {
		return self::deliver( 'treasurer_reminder', $booking, $to );
	}

	public static function denied( object $booking, string $note = '' ): bool {
		return self::deliver( 'denied', $booking, $booking->email, $note );
	}

	public static function cancelled( object $booking, string $note = '' ): bool {
		return self::deliver( 'cancelled', $booking, $booking->email, $note );
	}

	/**
	 * Email types with their settings label, default subject, default body (HTML) and the
	 * placeholders available in the body. Subject placeholders: {site}, {booking_id}, {name}, {date}.
	 */
	public static function email_defaults(): array {
		$common = array( '{name}', '{booking_id}', '{site}', '{date}', '{summary}' );
		$greet  = '<p>' . __( 'Hi {name},', 'clubhouse-planner' ) . '</p>';

		return array(
			'request_received' => array(
				'label'        => __( 'Request received', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan Booking request received (#{booking_id})', 'clubhouse-planner' ),
				'body'         => $greet
					. '<p>' . __( 'Thank you for your booking request. We have received it and a housekeeper will review it shortly. You will get another email when it has been reviewed.', 'clubhouse-planner' ) . '</p>'
					. '{summary}',
				'placeholders' => $common,
			),
			'new_request'      => array(
				'label'        => __( 'New request to housekeepers', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan New booking request (#{booking_id})', 'clubhouse-planner' ),
				'body'         => '<p>' . __( 'A new booking request has been submitted.', 'clubhouse-planner' ) . '</p>'
					. '{summary}'
					. '<p>' . __( 'Email', 'clubhouse-planner' ) . ': {email}<br>' . __( 'Phone', 'clubhouse-planner' ) . ': {phone}<br>{fee_info}</p>'
					. '{message}'
					. '<p>' . __( 'Review the request here:', 'clubhouse-planner' ) . '<br><a href="{review_url}">{review_url}</a></p>',
				'placeholders' => array_merge( $common, array( '{email}', '{phone}', '{fee_info}', '{message}', '{review_url}' ) ),
			),
			'approved'         => array(
				'label'        => __( 'Approved – payment information', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan Booking approved – payment information (#{booking_id})', 'clubhouse-planner' ),
				'body'         => $greet
					. '<p>' . __( 'Your booking request can be accepted. The booking is preliminary until payment has been received.', 'clubhouse-planner' ) . '</p>'
					. '<p>' . __( 'Fee to pay: {fee}', 'clubhouse-planner' ) . '</p>'
					. '<p><strong>' . __( 'How to pay', 'clubhouse-planner' ) . '</strong><br>{payment_instructions}</p>'
					. '{note}{summary}',
				'placeholders' => array_merge( $common, array( '{fee}', '{payment_instructions}', '{note}' ) ),
			),
			'treasurer_reminder' => array(
				'label'        => __( 'Payment reminder to treasurer', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan Payment not received (#{booking_id}, {date})', 'clubhouse-planner' ),
				'body'         => '<p>' . __( 'Heads up: the booking below is coming up soon and payment has not been registered yet.', 'clubhouse-planner' ) . '</p>'
					. '{summary}'
					. '<p>' . __( 'Open the booking here:', 'clubhouse-planner' ) . '<br><a href="{review_url}">{review_url}</a></p>',
				'placeholders' => array_merge( $common, array( '{fee}', '{email}', '{phone}', '{review_url}' ) ),
			),
			'confirmed'        => array(
				'label'        => __( 'Confirmed', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan Booking confirmed (#{booking_id})', 'clubhouse-planner' ),
				'body'         => $greet
					. '<p>' . __( 'We have received your payment and your booking is now confirmed. Welcome!', 'clubhouse-planner' ) . '</p>'
					. '{key_box}'
					. '{note}{summary}',
				'placeholders' => array_merge( $common, array( '{key_box}', '{note}' ) ),
			),
			'denied'           => array(
				'label'        => __( 'Denied', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan Booking request denied (#{booking_id})', 'clubhouse-planner' ),
				'body'         => $greet
					. '<p>' . __( 'Unfortunately we cannot accept your booking request.', 'clubhouse-planner' ) . '</p>'
					. '{note}{summary}',
				'placeholders' => array_merge( $common, array( '{note}' ) ),
			),
			'cancelled'        => array(
				'label'        => __( 'Cancelled', 'clubhouse-planner' ),
				'subject'      => __( 'Stugan Booking cancelled (#{booking_id})', 'clubhouse-planner' ),
				'body'         => $greet
					. '<p>' . __( 'Your booking has been cancelled.', 'clubhouse-planner' ) . '</p>'
					. '{note}{summary}',
				'placeholders' => array_merge( $common, array( '{note}' ) ),
			),
		);
	}

	/** Email body from settings, or the default when none is set. */
	public static function body_template( string $type ): string {
		$custom   = (array) CHP_Settings::get( 'bodies' );
		$template = trim( (string) ( $custom[ $type ] ?? '' ) );
		return '' === $template ? self::email_defaults()[ $type ]['body'] : $template;
	}

	/** Builds subject and HTML body for an email type and sends it. */
	private static function deliver( string $type, object $booking, string|array $to, string $note = '', string $reply_to = '' ): bool {
		$values = self::body_values( $booking, $note, 'approved' === $type );

		// TinyMCE may URL-encode braces inside link targets, e.g. href="%7Breview_url%7D".
		$replace = array();
		foreach ( $values as $placeholder => $value ) {
			$replace[ $placeholder ]                                                              = $value;
			$replace[ str_replace( array( '{', '}' ), array( '%7B', '%7D' ), $placeholder ) ] = $value;
		}
		$body = strtr( self::body_template( $type ), $replace );

		return self::send( $booking, $to, self::subject( $type, $booking ), $body, $reply_to );
	}

	/** HTML-safe values for all body placeholders. */
	private static function body_values( object $booking, string $note, bool $with_swish = false ): array {
		$fee     = CHP_Bookings::format_fee( (int) $booking->fee );
		$key_box = trim( (string) CHP_Settings::get( 'key_box_code' ) );

		if ( CHP_Bookings::is_local( $booking ) ) {
			/* translators: %s: formatted fee */
			$fee_info = sprintf( __( 'Fee: standard fee (%s).', 'clubhouse-planner' ), CHP_Bookings::format_fee( CHP_Settings::standard_fee( (string) $booking->slot ) ) );
		} else {
			$fee_info = __( 'Fee: the address is not on the standard-fee lists – agree a fee with the requester.', 'clubhouse-planner' );
		}

		$instructions = strtr(
			(string) CHP_Settings::get( 'payment_instructions' ),
			array(
				'{booking_id}' => $booking->id,
				'{name}'       => $booking->name,
				'{fee}'        => $fee,
				'{date}'       => mysql2date( get_option( 'date_format' ), $booking->start_at ),
			)
		);

		return array(
			'{name}'                 => esc_html( $booking->name ),
			'{booking_id}'           => (int) $booking->id,
			'{site}'                 => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'{date}'                 => esc_html( mysql2date( get_option( 'date_format' ), $booking->start_at ) ),
			'{fee}'                  => esc_html( $fee ),
			'{email}'                => esc_html( $booking->email ),
			'{phone}'                => esc_html( $booking->phone ),
			'{fee_info}'             => esc_html( $fee_info ),
			'{review_url}'           => esc_url( wp_login_url( admin_url( 'admin.php?page=chp-bookings&booking=' . (int) $booking->id ) ) ),
			'{payment_instructions}' => nl2br( esc_html( $instructions ) ) . ( $with_swish ? self::swish_link( $booking ) : '' ),
			'{key_box}'              => $key_box
				? '<p><strong>' . esc_html__( 'Key box code', 'clubhouse-planner' ) . '</strong><br>' . esc_html( $key_box ) . '</p>'
				: '',
			'{note}'                 => self::note_block( $note ),
			'{message}'              => $booking->message
				? '<p><strong>' . esc_html__( 'Message', 'clubhouse-planner' ) . '</strong><br>' . nl2br( esc_html( $booking->message ) ) . '</p>'
				: '',
			'{summary}'              => self::summary( $booking ),
		);
	}

	/** Label and default subject per email type. */
	public static function subject_defaults(): array {
		return array_map(
			static fn( array $d ): array => array(
				'label'   => $d['label'],
				'subject' => $d['subject'],
			),
			self::email_defaults()
		);
	}

	/** Subject from settings, or the default when none is set, with placeholders filled in. */
	private static function subject( string $type, object $booking ): string {
		$custom   = (array) CHP_Settings::get( 'subjects' );
		$template = trim( (string) ( $custom[ $type ] ?? '' ) );
		if ( '' === $template ) {
			$template = self::subject_defaults()[ $type ]['subject'];
		}

		return strtr(
			$template,
			array(
				'{site}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{booking_id}' => $booking->id,
				'{name}'       => $booking->name,
				'{date}'       => mysql2date( get_option( 'date_format' ), $booking->start_at ),
			)
		);
	}

	private static function summary( object $booking ): string {
		$lines = array(
			__( 'Booking number', 'clubhouse-planner' ) => '#' . $booking->id,
			__( 'Name', 'clubhouse-planner' )           => $booking->name,
			__( 'Address', 'clubhouse-planner' )        => $booking->address ?? '',
			__( 'Period', 'clubhouse-planner' )         => CHP_Bookings::format_period( $booking ),
			__( 'Purpose', 'clubhouse-planner' )        => $booking->purpose,
			__( 'Status', 'clubhouse-planner' )         => CHP_Bookings::status_label( $booking->status ),
		);
		if ( null !== $booking->fee ) {
			$lines[ __( 'Fee', 'clubhouse-planner' ) ] = CHP_Bookings::format_fee( (int) $booking->fee );
		}

		$out = '<table cellpadding="4" cellspacing="0" style="border-collapse:collapse;">';
		foreach ( $lines as $label => $value ) {
			$out .= '<tr><td style="color:#555;">' . esc_html( $label ) . '</td><td><strong>' . esc_html( (string) $value ) . '</strong></td></tr>';
		}
		return $out . '</table>';
	}

	/** "Pay with Swish" link with payee, amount and message prefilled and locked. Empty without a Swish number or fee. */
	private static function swish_link( object $booking ): string {
		$number = (string) CHP_Settings::get( 'swish_number' );
		$fee    = (int) $booking->fee;
		if ( '' === $number || $fee <= 0 ) {
			return '';
		}

		/* translators: 1: booking date, 2: booking number. Swish messages are limited to 50 characters. */
		$message = sprintf( __( 'Booking %1$s, %2$d', 'clubhouse-planner' ), mysql2date( 'Y-m-d', $booking->start_at ), (int) $booking->id );
		$url     = add_query_arg(
			array(
				'sw'  => $number,
				'amt' => $fee,
				'cur' => 'SEK',
				'msg' => mb_substr( $message, 0, 50 ),
			),
			'https://app.swish.nu/1/p/sw/'
		);

		$html = '<br><br><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 22px 14px;background-color:#fff;background-image:linear-gradient(90deg,#6a1bff,#00b4ff,#00d26a,#c8d400,#ffc400,#ff6a00,#ff1f4b,#e83cff);background-repeat:no-repeat;background-position:bottom;background-size:100% 4px;border:1px solid #d9d9d9;border-radius:24px;color:#1a1a1a;font-weight:bold;text-decoration:none;">'
			. esc_html__( 'Pay with Swish', 'clubhouse-planner' ) . '</a>';

		// Swish only exists on phones, so also show a QR code to scan from a computer screen.
		$qr_url = self::swish_qr( $number, $fee, mb_substr( $message, 0, 50 ) );
		if ( '' !== $qr_url ) {
			$html .='<p style="margin:16px 0 4px;">' . esc_html__( 'Reading this on a computer? Scan the QR code with the Swish app on your phone.', 'clubhouse-planner' ) . '</p>'
				. '<img src="' . esc_url( $qr_url ) . '" width="180" height="180" alt="' . esc_attr__( 'Swish QR code', 'clubhouse-planner' ) . '" style="display:block;">';
		}

		return $html;
	}

	/**
	 * Public URL of a prefilled Swish payment QR code, or '' if it can't be made. The PNG comes from
	 * Swish's public generator and is stored in the uploads folder under a hard-to-guess name, because
	 * Gmail and others handle linked images far more reliably than images embedded in the email.
	 */
	private static function swish_qr( string $number, int $fee, string $message ): string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$file = 'swish-' . substr( wp_hash( $number . '|' . $fee . '|' . $message ), 0, 24 ) . '.png';
		$dir  = trailingslashit( $uploads['basedir'] ) . 'clubhouse-planner';
		$url  = trailingslashit( $uploads['baseurl'] ) . 'clubhouse-planner/' . $file;
		if ( is_readable( $dir . '/' . $file ) ) {
			return $url;
		}

		$response = wp_remote_post(
			'https://mpc.getswish.net/qrg-swish/api/v1/prefilled',
			array(
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'format'  => 'png',
						'size'    => 360,
						'payee'   => array(
							'value'    => $number,
							'editable' => false,
						),
						'amount'  => array(
							'value'    => $fee,
							'editable' => false,
						),
						'message' => array(
							'value'    => $message,
							'editable' => false,
						),
					)
				),
			)
		);

		$png = is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ? '' : wp_remote_retrieve_body( $response );
		if ( '' === $png || ! str_starts_with( $png, "\x89PNG" ) ) {
			error_log( 'ClubHouse Planner: could not get a Swish QR code, sending the email without it.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return '';
		}

		if ( ! wp_mkdir_p( $dir ) || false === file_put_contents( $dir . '/' . $file, $png ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			error_log( 'ClubHouse Planner: could not save the Swish QR code to the uploads folder.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return '';
		}
		return $url;
	}

	private static function note_block( string $note ): string {
		return $note
			? '<p><strong>' . esc_html__( 'Message from the housekeeper', 'clubhouse-planner' ) . '</strong><br>' . nl2br( esc_html( $note ) ) . '</p>'
			: '';
	}

	/**
	 * Sends the email as HTML. A failure is written to the PHP error log and to the booking history.
	 */
	private static function send( object $booking, string|array $to, string $subject, string $body, string $reply_to = '' ): bool {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$body    = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#222;">' . $body . '</div>';

		$from_email = (string) CHP_Settings::get( 'from_email' );
		if ( is_email( $from_email ) ) {
			$from_name = (string) CHP_Settings::get( 'from_name' );
			$headers[] = 'From: ' . ( '' !== $from_name ? $from_name . ' <' . $from_email . '>' : $from_email );
		}

		if ( ! $reply_to ) {
			$recipients = CHP_Settings::notify_recipients();
			$reply_to   = $recipients ? $recipients[0] : '';
		}
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$error   = null;
		$capture = function ( WP_Error $e ) use ( &$error ): void {
			$error = $e;
		};
		// With WP_DEBUG on, log how PHPMailer is set up after all other plugins have configured it.
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$trace = function ( $phpmailer ): void {
			error_log( sprintf( 'ClubHouse Planner: PHPMailer mailer=%s host=%s port=%s from=%s', $phpmailer->Mailer, $phpmailer->Host, $phpmailer->Port, $phpmailer->From ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		};
		if ( $debug ) {
			add_action( 'phpmailer_init', $trace, PHP_INT_MAX );
		}

		add_action( 'wp_mail_failed', $capture );
		$sent = wp_mail( $to, $subject, $body, $headers );
		remove_action( 'wp_mail_failed', $capture );
		remove_action( 'phpmailer_init', $trace, PHP_INT_MAX );

		if ( $sent && $debug ) {
			error_log( sprintf( 'ClubHouse Planner: email "%s" to %s handed over OK', $subject, implode( ', ', (array) $to ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( ! $sent ) {
			$reason = $error ? $error->get_error_message() : 'wp_mail() returned false';
			$to     = implode( ', ', (array) $to );
			error_log( sprintf( 'ClubHouse Planner: email "%s" to %s failed: %s', $subject, $to, $reason ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			CHP_Bookings::log(
				(int) $booking->id,
				$booking->status,
				/* translators: 1: email subject, 2: recipients, 3: error message */
				sprintf( __( 'Email "%1$s" to %2$s could not be sent: %3$s', 'clubhouse-planner' ), $subject, $to, $reason ),
				get_current_user_id()
			);
		}
		return $sent;
	}

}
