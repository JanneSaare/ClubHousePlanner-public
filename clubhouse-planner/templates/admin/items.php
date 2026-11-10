<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var object[]    $items  All items.
 * @var object|null $item   The item being edited (id 0 = new), or null for the list.
 * @var string      $notice Notice or error code.
 */
$notices  = CHP_Admin::item_notices();
$base_url = admin_url( 'admin.php?page=chp-items' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Items', 'clubhouse-planner' ); ?></h1>
	<?php if ( ! $item ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'item', 'new', $base_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add item', 'clubhouse-planner' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( isset( $notices[ $notice ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notices[ $notice ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ][1] ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $item ) : ?>
		<p><?php esc_html_e( 'Things that can be booked. An item with more than one unit has its units named A, B, C and so on; the letter is only shown when there is more than one unit.', 'clubhouse-planner' ); ?></p>
		<table class="widefat striped chp-table">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Name', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Units', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Booking', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Price', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Status', 'clubhouse-planner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $row ) : ?>
					<tr>
						<td><?php echo (int) $row->id; ?></td>
						<td><a href="<?php echo esc_url( add_query_arg( 'item', (int) $row->id, $base_url ) ); ?>"><strong><?php echo esc_html( $row->name ); ?></strong></a></td>
						<td>
							<?php echo (int) $row->quantity; ?>
							<?php if ( CHP_Items::show_unit( $row ) ) : ?>
								<small>(<?php echo esc_html( implode( ', ', CHP_Items::unit_labels( (int) $row->quantity ) ) ); ?>)</small>
							<?php endif; ?>
						</td>
						<td><?php echo CHP_Items::MODE_FREE === $row->booking_mode ? esc_html__( 'Free choice of time', 'clubhouse-planner' ) : esc_html__( 'Fixed booking types', 'clubhouse-planner' ); ?></td>
						<td><?php echo esc_html( CHP_Items::fee_summary( $row ) ); ?></td>
						<td><?php echo $row->active ? esc_html__( 'Active', 'clubhouse-planner' ) : esc_html__( 'Inactive', 'clubhouse-planner' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<?php $item_id = (int) $item->id; ?>
		<p><a href="<?php echo esc_url( $base_url ); ?>">&larr; <?php esc_html_e( 'Back to all items', 'clubhouse-planner' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="chp_save_item">
			<input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
			<?php wp_nonce_field( 'chp_save_item_' . $item_id ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="chp-item-name"><?php esc_html_e( 'Name', 'clubhouse-planner' ); ?></label></th>
					<td><input type="text" id="chp-item-name" class="regular-text" maxlength="190" name="name" value="<?php echo esc_attr( $item->name ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-description"><?php esc_html_e( 'Description', 'clubhouse-planner' ); ?></label></th>
					<td><textarea id="chp-item-description" class="large-text" rows="3" name="description"><?php echo esc_textarea( (string) $item->description ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-quantity"><?php esc_html_e( 'Number of units', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="number" id="chp-item-quantity" class="small-text" min="1" max="<?php echo (int) CHP_Items::MAX_QUANTITY; ?>" step="1" name="quantity" value="<?php echo (int) $item->quantity; ?>" required>
						<p class="description"><?php esc_html_e( 'How many identical copies can be booked at the same time. Units are named A, B, C and so on, and a request gets the first free unit.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-mode"><?php esc_html_e( 'Booking', 'clubhouse-planner' ); ?></label></th>
					<td>
						<select id="chp-item-mode" name="booking_mode">
							<option value="slots" <?php selected( $item->booking_mode, 'slots' ); ?>><?php esc_html_e( 'Fixed booking types (day, evening, whole day – set in Settings)', 'clubhouse-planner' ); ?></option>
							<option value="free" <?php selected( $item->booking_mode, 'free' ); ?>><?php esc_html_e( 'The customer chooses start and end', 'clubhouse-planner' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'The price, weekend and length settings below are used when the customer chooses start and end.', 'clubhouse-planner' ); ?></p>
						<p class="description"><strong><?php esc_html_e( 'Fixed booking types use the types, times and prices set under Settings, shared with the club house.', 'clubhouse-planner' ); ?></strong> <?php esc_html_e( 'Choose "The customer chooses start and end" for any other item that needs its own price.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-fee"><?php esc_html_e( 'Price', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="number" id="chp-item-fee" class="small-text" min="0" step="1" name="fee_weekday" value="<?php echo (int) $item->fee_weekday; ?>"> kr
						<select name="fee_unit" aria-label="<?php esc_attr_e( 'Price is', 'clubhouse-planner' ); ?>">
							<?php foreach ( CHP_Pricing::fee_units() as $unit_key => $unit_label ) : ?>
								<option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( $item->fee_unit, $unit_key ); ?>><?php echo esc_html( $unit_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'A booking of 24 hours or less counts as one day, also when it crosses midnight. Longer bookings are counted in blocks of 24 hours.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-fee-weekend"><?php esc_html_e( 'Weekend price', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="number" id="chp-item-fee-weekend" class="small-text" min="0" step="1" name="fee_weekend" value="<?php echo null === $item->fee_weekend ? '' : (int) $item->fee_weekend; ?>"> kr
						<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the same price every day. For a price per booking, a booking longer than 24 hours gets the weekend price if it includes a weekend day.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Weekend days', 'clubhouse-planner' ); ?></th>
					<td>
						<?php
						$weekend_days = CHP_Pricing::weekend_days( $item );
						$day_names    = array(
							1 => __( 'Monday', 'clubhouse-planner' ),
							2 => __( 'Tuesday', 'clubhouse-planner' ),
							3 => __( 'Wednesday', 'clubhouse-planner' ),
							4 => __( 'Thursday', 'clubhouse-planner' ),
							5 => __( 'Friday', 'clubhouse-planner' ),
							6 => __( 'Saturday', 'clubhouse-planner' ),
							7 => __( 'Sunday', 'clubhouse-planner' ),
						);
						foreach ( $day_names as $number => $day_name ) :
							?>
							<label style="margin-right:1em;white-space:nowrap;"><input type="checkbox" name="weekend_days[]" value="<?php echo (int) $number; ?>" <?php checked( in_array( $number, $weekend_days, true ) ); ?>> <?php echo esc_html( $day_name ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'The days that get the weekend price.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<?php
				// A stored length in minutes shown as whole days or hours.
				$split = static function ( $minutes ): array {
					if ( ! $minutes ) {
						return array( '', 'hours' );
					}
					$minutes = (int) $minutes;
					return 0 === $minutes % 1440 ? array( (string) ( $minutes / 1440 ), 'days' ) : array( (string) round( $minutes / 60, 2 ), 'hours' );
				};
				?>
				<?php foreach ( array( 'min_duration' => __( 'Shortest booking', 'clubhouse-planner' ), 'max_duration' => __( 'Longest booking', 'clubhouse-planner' ) ) as $field => $field_label ) : ?>
					<?php list( $length_value, $length_unit ) = $split( $item->$field ); ?>
					<tr>
						<th scope="row"><label for="chp-item-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $field_label ); ?></label></th>
						<td>
							<input type="number" id="chp-item-<?php echo esc_attr( $field ); ?>" class="small-text" min="0" step="any" name="<?php echo esc_attr( $field ); ?>_value" value="<?php echo esc_attr( $length_value ); ?>">
							<select name="<?php echo esc_attr( $field ); ?>_unit" aria-label="<?php echo esc_attr( $field_label ); ?>">
								<option value="hours" <?php selected( $length_unit, 'hours' ); ?>><?php esc_html_e( 'hours', 'clubhouse-planner' ); ?></option>
								<option value="days" <?php selected( $length_unit, 'days' ); ?>><?php esc_html_e( 'days', 'clubhouse-planner' ); ?></option>
							</select>
							<?php if ( 'max_duration' === $field ) : ?>
								<p class="description"><?php esc_html_e( 'Optional. Leave empty for no limit.', 'clubhouse-planner' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="chp-item-notice"><?php esc_html_e( 'Notice needed', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="number" id="chp-item-notice" class="small-text" min="0" step="1" name="min_notice" value="<?php echo $item->min_notice ? (int) $item->min_notice : ''; ?>"> <?php esc_html_e( 'hours before the start', 'clubhouse-planner' ); ?>
						<p class="description"><?php esc_html_e( 'Optional. How long before the start a request can be made.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-purpose-mode"><?php esc_html_e( 'Purpose field', 'clubhouse-planner' ); ?></label></th>
					<td>
						<select id="chp-item-purpose-mode" name="purpose_mode">
							<option value="none" <?php selected( $item->purpose_mode, 'none' ); ?>><?php esc_html_e( 'Not asked', 'clubhouse-planner' ); ?></option>
							<option value="list" <?php selected( $item->purpose_mode, 'list' ); ?>><?php esc_html_e( 'Choose from a list', 'clubhouse-planner' ); ?></option>
							<option value="text" <?php selected( $item->purpose_mode, 'text' ); ?>><?php esc_html_e( 'Free text', 'clubhouse-planner' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'What the booking form asks about the purpose of the booking.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-purposes"><?php esc_html_e( 'Purpose list', 'clubhouse-planner' ); ?></label></th>
					<td>
						<textarea id="chp-item-purposes" class="large-text" rows="5" name="purposes"><?php echo esc_textarea( implode( "\n", CHP_Items::purpose_options( $item ) ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One purpose per line. Used when the purpose field is a list.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-key-box"><?php esc_html_e( 'Key box code', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="text" id="chp-item-key-box" class="regular-text" maxlength="100" name="key_box_code" value="<?php echo esc_attr( (string) $item->key_box_code ); ?>">
						<p class="description"><?php esc_html_e( 'Optional. Replaces the general key box code in the Confirmed email for this item. Leave empty to use the general one.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-sort"><?php esc_html_e( 'Order', 'clubhouse-planner' ); ?></label></th>
					<td>
						<input type="number" id="chp-item-sort" class="small-text" step="1" name="sort_order" value="<?php echo (int) $item->sort_order; ?>">
						<p class="description"><?php esc_html_e( 'Lower numbers come first. The first active item is the one used when no item is chosen.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'clubhouse-planner' ); ?></th>
					<td>
						<label><input type="checkbox" name="active" value="1" <?php checked( (int) $item->active ); ?>> <?php esc_html_e( 'The item can be booked', 'clubhouse-planner' ); ?></label>
						<p class="description"><?php esc_html_e( 'Inactive items cannot be booked. Their existing bookings are kept.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( $item_id ? __( 'Save item', 'clubhouse-planner' ) : __( 'Add item', 'clubhouse-planner' ) ); ?>
		</form>
			<?php if ( $item_id ) : ?>
				<h2><?php esc_html_e( 'Shortcodes for this item', 'clubhouse-planner' ); ?></h2>
				<p><?php esc_html_e( 'Paste these into a page. The number is the item number shown in the item list.', 'clubhouse-planner' ); ?></p>
				<p>
					<?php esc_html_e( 'Booking form', 'clubhouse-planner' ); ?><br>
					<input type="text" class="large-text code" readonly onfocus="this.select()" value="<?php echo esc_attr( '[clubhouse_booking_form item="' . $item_id . '"]' ); ?>">
				</p>
				<p>
					<?php esc_html_e( 'Calendar', 'clubhouse-planner' ); ?><br>
					<input type="text" class="large-text code" readonly onfocus="this.select()" value="<?php echo esc_attr( '[clubhouse_calendar item="' . $item_id . '"]' ); ?>">
				</p>
			<?php endif; ?>
	<?php endif; ?>
</div>
