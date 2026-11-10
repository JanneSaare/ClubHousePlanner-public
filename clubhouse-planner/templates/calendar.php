<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="chp-calendar-wrap" style="max-width: <?php echo (int) CHP_Settings::get( 'calendar_width' ); ?>%;">
	<div class="chp-calendar" aria-live="polite"></div>
	<p class="chp-legend">
		<?php foreach ( CHP_Bookings::slots() as $key => $slot ) : ?>
			<span class="chp-legend__item chp-legend__item--<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $slot['label'] . ' ' . $slot['start'] . '–' . $slot['end'] ); ?></span>
		<?php endforeach; ?>
		<span class="chp-legend__item chp-legend__item--preliminary"><?php esc_html_e( 'Preliminary', 'clubhouse-planner' ); ?></span>
	</p>
</div>
