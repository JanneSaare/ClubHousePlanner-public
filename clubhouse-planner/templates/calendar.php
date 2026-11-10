<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var object $item    The item shown by this calendar.
 * @var bool   $grouped True when shown with other calendars by [clubhouse_calendars].
 */
$grouped = ! empty( $grouped );
$free    = CHP_Pricing::is_priced( $item );
?>
<div class="chp-calendar-wrap"<?php echo $grouped ? '' : ' style="max-width: ' . (int) CHP_Settings::get( 'calendar_width' ) . '%;"'; ?>>
	<?php if ( $grouped ) : ?>
		<h3 class="chp-calendar-title"><?php echo esc_html( $item->name ); ?></h3>
	<?php endif; ?>
	<div class="chp-calendar" data-item="<?php echo (int) $item->id; ?>" aria-live="polite"></div>
	<p class="chp-legend">
		<?php if ( $free ) : ?>
			<span class="chp-legend__item chp-legend__item--free"><?php esc_html_e( 'Booked', 'clubhouse-planner' ); ?></span>
		<?php else : ?>
			<?php foreach ( CHP_Bookings::slots() as $key => $slot ) : ?>
				<span class="chp-legend__item chp-legend__item--<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $slot['label'] . ' ' . $slot['start'] . '–' . $slot['end'] ); ?></span>
			<?php endforeach; ?>
		<?php endif; ?>
		<span class="chp-legend__item chp-legend__item--preliminary"><?php esc_html_e( 'Preliminary', 'clubhouse-planner' ); ?></span>
	</p>
</div>
