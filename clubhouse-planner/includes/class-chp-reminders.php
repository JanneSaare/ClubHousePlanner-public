<?php
defined( 'ABSPATH' ) || exit;

/** Daily WP-Cron job: heads-up to the treasurer about unpaid bookings that are coming up. */
class CHP_Reminders {

	const HOOK = 'chp_daily_reminders';

	public static function init(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		// Also covers sites where the plugin was updated rather than re-activated.
		self::schedule();
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Sends one reminder per booking; the booking is marked and logged only when the email was handed over. */
	public static function run(): void {
		$to = CHP_Settings::treasurer_recipients();
		if ( empty( $to ) ) {
			return;
		}

		$days = max( 1, (int) CHP_Settings::get( 'treasurer_days' ) );
		foreach ( CHP_Bookings::due_for_treasurer_reminder( $days ) as $booking ) {
			if ( ! CHP_Mailer::treasurer_reminder( $booking, $to ) ) {
				continue;
			}
			CHP_Bookings::mark_treasurer_reminded( (int) $booking->id );
			CHP_Bookings::log(
				(int) $booking->id,
				$booking->status,
				/* translators: %s: treasurer email address(es) */
				sprintf( __( 'Payment reminder sent to the treasurer (%s).', 'clubhouse-planner' ), implode( ', ', $to ) ),
				0
			);
		}
	}
}
