<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var object[]    $blackouts All blocked periods.
 * @var object|null $blackout  The period being edited (id 0 = new), or null for the list.
 * @var object[]    $items     All items.
 * @var object[]    $conflicts Bookings inside the period that was just saved.
 * @var string      $notice    Notice or error code.
 */
$notices   = CHP_Admin::blackout_notices();
$base_url  = admin_url( 'admin.php?page=chp-blackouts' );
$fmt       = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$item_name = static function ( ?int $id ) use ( $items ): string {
	foreach ( $items as $row ) {
		if ( (int) $row->id === (int) $id ) {
			return $row->name;
		}
	}
	return '';
};
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Blocked periods', 'clubhouse-planner' ); ?></h1>
	<?php if ( ! $blackout ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'blackout', 'new', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add blocked period', 'clubhouse-planner' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( isset( $notices[ $notice ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $blackout && $conflicts ) : ?>
		<div class="notice notice-warning">
			<p><strong><?php esc_html_e( 'These bookings fall inside the blocked period. They have not been changed. Deny or cancel them if needed.', 'clubhouse-planner' ); ?></strong></p>
			<ul>
				<?php foreach ( $conflicts as $b ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-bookings&booking=' . (int) $b->id ) ); ?>">#<?php echo (int) $b->id; ?> <?php echo esc_html( $b->name ); ?></a>
						– <?php echo esc_html( mysql2date( $fmt, $b->start_at ) . ' – ' . mysql2date( $fmt, $b->end_at ) ); ?>
						(<?php echo esc_html( CHP_Bookings::status_label( $b->status ) ); ?>)
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! $blackout ) : ?>
		<p><?php esc_html_e( 'Times when an item cannot be booked, for example repairs or a season when it is closed. Customers see the days as "Not available", or the text you enter.', 'clubhouse-planner' ); ?></p>
		<table class="widefat striped chp-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'From', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'To', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Repeats', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Public text', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'clubhouse-planner' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $blackouts ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No blocked periods.', 'clubhouse-planner' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $blackouts as $row ) : ?>
					<tr>
						<td>
							<?php echo $row->item_id ? esc_html( $item_name( (int) $row->item_id ) ) : esc_html__( 'All items', 'clubhouse-planner' ); ?>
							<?php if ( $row->unit ) : ?>
								<small>(<?php echo esc_html( $row->unit ); ?>)</small>
							<?php endif; ?>
						</td>
						<td><a href="<?php echo esc_url( add_query_arg( 'blackout', (int) $row->id, $base_url ) ); ?>"><?php echo esc_html( mysql2date( $fmt, $row->start_at ) ); ?></a></td>
						<td><?php echo esc_html( mysql2date( $fmt, $row->end_at ) ); ?></td>
						<td><?php echo $row->yearly ? esc_html__( 'Every year', 'clubhouse-planner' ) : '–'; ?></td>
						<td><?php echo esc_html( (string) $row->public_text ); ?></td>
						<td><?php echo esc_html( $row->reason ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this blocked period?', 'clubhouse-planner' ) ); ?>');">
								<input type="hidden" name="action" value="chp_delete_blackout">
								<input type="hidden" name="blackout_id" value="<?php echo (int) $row->id; ?>">
								<?php wp_nonce_field( 'chp_delete_blackout_' . (int) $row->id ); ?>
								<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Remove', 'clubhouse-planner' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<?php
		$blackout_id = (int) $blackout->id;
		$to_input    = static fn( string $value ): string => '' === $value ? '' : str_replace( ' ', 'T', substr( $value, 0, 16 ) );
		?>
		<p><a href="<?php echo esc_url( $base_url ); ?>">&larr; <?php esc_html_e( 'Back to all blocked periods', 'clubhouse-planner' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="chp_save_blackout">
			<input type="hidden" name="blackout_id" value="<?php echo $blackout_id; ?>">
			<?php wp_nonce_field( 'chp_save_blackout_' . $blackout_id ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="chp-bo-item"><?php esc_html_e( 'Item', 'clubhouse-planner' ); ?></label></th>
					<td>
						<select id="chp-bo-item" name="item_id">
							<option value="0" <?php selected( (int) $blackout->item_id, 0 ); ?>><?php esc_html_e( 'All items', 'clubhouse-planner' ); ?></option>
							<?php foreach ( $items as $row ) : ?>
								<option value="<?php echo (int) $row->id; ?>" <?php selected( (int) $blackout->item_id, (int) $row->id ); ?>><?php echo esc_html( $row->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-bo-unit"><?php esc_html_e( 'Unit', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="text" id="chp-bo-unit" class="small-text" maxlength="3" name="unit" value="<?php echo esc_attr( (string) $blackout->unit ); ?>">
						<p class="description"><?php esc_html_e( 'Optional. A unit letter, for example B, to block only that unit of the item. Leave empty to block every unit.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-bo-start"><?php esc_html_e( 'From', 'clubhouse-planner' ); ?></label></th>
					<td><input type="datetime-local" id="chp-bo-start" name="start_at" value="<?php echo esc_attr( $to_input( (string) $blackout->start_at ) ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-bo-end"><?php esc_html_e( 'To', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="datetime-local" id="chp-bo-end" name="end_at" value="<?php echo esc_attr( $to_input( (string) $blackout->end_at ) ); ?>" required>
						<p class="description"><?php esc_html_e( 'The item is available again from this time. To block whole days, end at 00:00 on the day after the last blocked day.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Repeats', 'clubhouse-planner' ); ?></th>
					<td>
						<label><input type="checkbox" name="yearly" value="1" <?php checked( (int) $blackout->yearly ); ?>> <?php esc_html_e( 'Repeat every year', 'clubhouse-planner' ); ?></label>
						<p class="description"><?php esc_html_e( 'For seasonal closures, for example 1 November to 1 April. The dates above are the first time; the period then repeats on the same dates each year.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-bo-public"><?php esc_html_e( 'Public text', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="text" id="chp-bo-public" class="regular-text" maxlength="190" name="public_text" value="<?php echo esc_attr( (string) $blackout->public_text ); ?>">
						<p class="description"><?php esc_html_e( 'Shown to customers in the calendar, for example "Closed for the season". Leave empty to show "Not available".', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-bo-reason"><?php esc_html_e( 'Reason', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="text" id="chp-bo-reason" class="regular-text" maxlength="190" name="reason" value="<?php echo esc_attr( $blackout->reason ); ?>">
						<p class="description"><?php esc_html_e( 'A note for the housekeepers. It is never shown to customers.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( $blackout_id ? __( 'Save blocked period', 'clubhouse-planner' ) : __( 'Add blocked period', 'clubhouse-planner' ) ); ?>
		</form>
	<?php endif; ?>
</div>
