<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var object $booking
 * @var array  $log
 * @var string $notice
 */
$notices = CHP_Admin::notices();
$actions = CHP_Admin::actions();
$fmt     = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

$neighbours  = CHP_Bookings::neighbours( $booking );
$has_any     = (bool) array_filter( $neighbours );
$has_overlap = false;
foreach ( $neighbours as $rows ) {
	foreach ( $rows as $n ) {
		$has_overlap = $has_overlap || $n->overlaps;
	}
}
$badge = CHP_Admin::slot_badge( $booking, 'large' );
$item   = CHP_Items::get( (int) $booking->item_id );
$priced = CHP_Pricing::is_priced( $item );
// A priced item has a calculated price, so the address does not decide the fee.
$local = CHP_Bookings::is_local( $booking ) || $priced;
?>
<div class="wrap">
	<h1>
		<?php
		/* translators: %d: booking id */
		echo esc_html( sprintf( __( 'Booking #%d', 'clubhouse-planner' ), $booking->id ) );
		?>
		<span class="chp-status chp-status--<?php echo esc_attr( $booking->status ); ?>"><?php echo esc_html( CHP_Bookings::status_label( $booking->status ) ); ?></span>
	</h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-bookings' ) ); ?>">&larr; <?php esc_html_e( 'Back to all bookings', 'clubhouse-planner' ); ?></a></p>

	<p class="chp-slot-line">
		<?php if ( $badge ) : ?>
			<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in slot_badge(). ?>
			<strong><?php echo esc_html( CHP_Admin::booking_date( $booking ) ); ?></strong>
		<?php else : ?>
			<strong><?php echo esc_html( CHP_Bookings::format_period( $booking ) ); ?></strong>
		<?php endif; ?>
	</p>

	<div class="chp-nearby<?php echo $has_overlap ? ' chp-nearby--conflict' : ( $has_any ? ' chp-nearby--info' : '' ); ?>">
		<h2><?php esc_html_e( 'Other bookings around this date', 'clubhouse-planner' ); ?></h2>
		<?php if ( ! $has_any ) : ?>
			<p><?php esc_html_e( 'No other bookings the day before, the same day or the day after.', 'clubhouse-planner' ); ?></p>
		<?php endif; ?>
		<?php foreach ( CHP_Admin::neighbour_labels() as $offset => $label ) : ?>
			<?php if ( empty( $neighbours[ $offset ] ) ) { continue; } ?>
			<h3><?php echo esc_html( $label ); ?></h3>
			<ul>
				<?php foreach ( $neighbours[ $offset ] as $n ) : ?>
					<?php $n_badge = CHP_Admin::slot_badge( $n ); ?>
					<li>
						<?php echo $n_badge ? $n_badge : esc_html( CHP_Bookings::format_period( $n ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in slot_badge(). ?>
						<span class="chp-status chp-status--<?php echo esc_attr( $n->status ); ?>"><?php echo esc_html( CHP_Bookings::status_label( $n->status ) ); ?></span>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-bookings&booking=' . (int) $n->id ) ); ?>">#<?php echo (int) $n->id; ?> <?php echo esc_html( $n->name ); ?></a>
						<?php if ( $n->overlaps ) : ?>
							<strong class="chp-overlap"><?php esc_html_e( 'Overlaps', 'clubhouse-planner' ); ?></strong>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	</div>

	<?php if ( isset( $notices[ $notice ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
	<?php endif; ?>

	<div class="chp-columns">
		<div class="chp-card">
			<h2><?php esc_html_e( 'Request', 'clubhouse-planner' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php if ( $item && ( CHP_Items::has_multiple() || CHP_Items::show_unit( $item ) ) ) : ?>
					<tr><th><?php esc_html_e( 'Item', 'clubhouse-planner' ); ?></th><td><strong><?php echo esc_html( CHP_Items::label( $item, (string) $booking->unit ) ); ?></strong></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Period', 'clubhouse-planner' ); ?></th><td><strong><?php echo esc_html( CHP_Bookings::format_period( $booking ) ); ?></strong></td></tr>
				<tr><th><?php esc_html_e( 'Name', 'clubhouse-planner' ); ?></th><td><?php echo esc_html( $booking->name ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'clubhouse-planner' ); ?></th><td><?php if ( '' !== (string) $booking->email ) : ?><a href="<?php echo esc_url( 'mailto:' . $booking->email ); ?>"><?php echo esc_html( $booking->email ); ?></a><?php endif; ?></td></tr>
				<tr><th><?php esc_html_e( 'Phone', 'clubhouse-planner' ); ?></th><td><?php echo esc_html( $booking->phone ); ?></td></tr>
				<?php if ( ! $priced ) : ?>
				<tr>
					<th><?php esc_html_e( 'Address', 'clubhouse-planner' ); ?></th>
					<td>
						<?php echo esc_html( (string) $booking->address ); ?>
						<?php if ( $local ) : ?>
							<span class="chp-fee-tag chp-fee-tag--standard"><?php esc_html_e( 'Standard fee', 'clubhouse-planner' ); ?></span>
						<?php else : ?>
							<span class="chp-fee-tag chp-fee-tag--other"><?php esc_html_e( 'Other address', 'clubhouse-planner' ); ?></span>
						<?php endif; ?>
						/td>
						/tr>
						?php endif; ?>
						?php if ( null !== $booking->fee ) : ?>
					<tr><th><?php esc_html_e( 'Fee', 'clubhouse-planner' ); ?></th><td><strong><?php echo esc_html( CHP_Bookings::format_fee( (int) $booking->fee ) ); ?></strong></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Purpose', 'clubhouse-planner' ); ?></th><td><?php echo esc_html( $booking->purpose ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Message', 'clubhouse-planner' ); ?></th><td><?php echo nl2br( esc_html( (string) $booking->message ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Received', 'clubhouse-planner' ); ?></th><td><?php echo esc_html( mysql2date( $fmt, $booking->created_at ) ); ?></td></tr>
			</table>
		</div>

		<div class="chp-card">
			<h2><?php esc_html_e( 'Actions', 'clubhouse-planner' ); ?></h2>
			<?php $allowed = CHP_Bookings::allowed_transitions( $booking->status ); ?>
			<?php if ( empty( $allowed ) ) : ?>
				<p><?php esc_html_e( 'No further actions available for this booking.', 'clubhouse-planner' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $allowed as $to ) : ?>
				<?php
				if ( ! isset( $actions[ $to ] ) ) {
					continue;
				}
				$field_id = 'chp-note-' . $to;
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chp-action">
					<input type="hidden" name="action" value="chp_booking_action">
					<input type="hidden" name="booking_id" value="<?php echo (int) $booking->id; ?>">
					<input type="hidden" name="transition" value="<?php echo esc_attr( $to ); ?>">
					<?php wp_nonce_field( 'chp_booking_action_' . (int) $booking->id ); ?>
					<?php if ( CHP_Bookings::AWAITING_PAYMENT === $to ) : ?>
						<?php
						if ( null !== $booking->fee ) {
							$fee_value = (string) (int) $booking->fee;
						} else {
							$fee_value = $local ? (string) CHP_Settings::standard_fee( (string) $booking->slot ) : '';
						}
						?>
						<p class="chp-fee-field<?php echo $local ? '' : ' chp-fee-field--other'; ?>">
							<label for="chp-fee"><?php esc_html_e( 'Fee (SEK)', 'clubhouse-planner' ); ?></label>
							<input type="number" id="chp-fee" name="fee" min="0" step="1" class="small-text" value="<?php echo esc_attr( $fee_value ); ?>"<?php echo $local ? '' : ' required'; ?>> kr
							<label class="chp-free-label"><input type="checkbox" id="chp-free" name="free" value="1"> <?php esc_html_e( 'Free of charge', 'clubhouse-planner' ); ?></label>
							<span class="description">
								<?php if ( $priced ) : ?>
									<?php esc_html_e( 'Calculated from the price list of the item. You can change it if needed.', 'clubhouse-planner' ); ?>
								<?php elseif ( $local ) : ?>
									<?php esc_html_e( 'Standard fee for the listed streets and addresses. You can change it if needed.', 'clubhouse-planner' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'The address is not on the standard-fee lists. Enter the agreed fee.', 'clubhouse-planner' ); ?>
								<?php endif; ?>
							</span>
						</p>
					<?php endif; ?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $actions[ $to ]['note'] ); ?></label>
					<textarea id="<?php echo esc_attr( $field_id ); ?>" name="note" rows="3" class="large-text"></textarea>
					<?php if ( in_array( $to, array( CHP_Bookings::DENIED, CHP_Bookings::CANCELLED ), true ) ) : ?>
						<p><label><input type="checkbox" name="no_email" value="1"<?php echo '' === (string) $booking->email ? ' checked' : ''; ?>> <?php esc_html_e( "Don't send email confirmation", 'clubhouse-planner' ); ?></label></p>
					<?php endif; ?>
					<button type="submit" class="button <?php echo esc_attr( $actions[ $to ]['button'] ); ?>" data-label="<?php echo esc_attr( $actions[ $to ]['label'] ); ?>" data-label-free="<?php esc_attr_e( 'Free of charge – confirm booking', 'clubhouse-planner' ); ?>"><?php echo esc_html( $actions[ $to ]['label'] ); ?></button>
				</form>
			<?php endforeach; ?>
			<?php if ( $item && CHP_Items::show_unit( $item ) && in_array( $booking->status, CHP_Bookings::active_statuses(), true ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chp-action">
					<input type="hidden" name="action" value="chp_reassign_unit">
					<input type="hidden" name="booking_id" value="<?php echo (int) $booking->id; ?>">
					<?php wp_nonce_field( 'chp_reassign_unit_' . (int) $booking->id ); ?>
					<label for="chp-unit"><?php esc_html_e( 'Unit', 'clubhouse-planner' ); ?></label>
					<select id="chp-unit" name="unit">
						<?php foreach ( CHP_Items::unit_labels( (int) $item->quantity ) as $unit_label ) : ?>
							<option value="<?php echo esc_attr( $unit_label ); ?>" <?php selected( $booking->unit, $unit_label ); ?>><?php echo esc_html( $unit_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Change unit', 'clubhouse-planner' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( CHP_Bookings::AWAITING_PAYMENT === $booking->status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chp-action">
					<input type="hidden" name="action" value="chp_resend_payment">
					<input type="hidden" name="booking_id" value="<?php echo (int) $booking->id; ?>">
					<?php wp_nonce_field( 'chp_resend_payment_' . (int) $booking->id ); ?>
					<label for="chp-note-resend"><?php esc_html_e( 'Additional message to the requester (optional)', 'clubhouse-planner' ); ?></label>
					<textarea id="chp-note-resend" name="note" rows="3" class="large-text"></textarea>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Send payment information again', 'clubhouse-planner' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<script>
	( function () {
		var free = document.getElementById( 'chp-free' );
		if ( ! free ) { return; }
		var form = free.form, fee = document.getElementById( 'chp-fee' ), button = form.querySelector( 'button[type="submit"]' );
		var wasRequired = fee.required;
		free.addEventListener( 'change', function () {
			fee.disabled = free.checked;
			fee.required = ! free.checked && wasRequired;
			button.textContent = free.checked ? button.dataset.labelFree : button.dataset.label;
		} );
	}() );
	</script>

	<div class="chp-card">
		<h2><?php esc_html_e( 'History', 'clubhouse-planner' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Status', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'By', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Note', 'clubhouse-planner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $i => $entry ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( $fmt, $entry->created_at ) ); ?></td>
						<td><?php echo esc_html( CHP_Bookings::status_label( $entry->status ) ); ?></td>
						<td><?php echo esc_html( $entry->display_name ? $entry->display_name : ( 0 === $i ? __( 'Requester', 'clubhouse-planner' ) : __( 'System', 'clubhouse-planner' ) ) ); ?></td>
						<td><?php echo nl2br( esc_html( (string) $entry->note ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
