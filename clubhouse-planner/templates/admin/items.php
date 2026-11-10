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
					<th><?php esc_html_e( 'Name', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Units', 'clubhouse-planner' ); ?></th>
					<th><?php esc_html_e( 'Status', 'clubhouse-planner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( add_query_arg( 'item', (int) $row->id, $base_url ) ); ?>"><strong><?php echo esc_html( $row->name ); ?></strong></a></td>
						<td>
							<?php echo (int) $row->quantity; ?>
							<?php if ( CHP_Items::show_unit( $row ) ) : ?>
								<small>(<?php echo esc_html( implode( ', ', CHP_Items::unit_labels( (int) $row->quantity ) ) ); ?>)</small>
							<?php endif; ?>
						</td>
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
					<th scope="row"><label for="chp-item-purpose-mode"><?php esc_html_e( 'Purpose field', 'clubhouse-planner' ); ?></label></th>
					<td>
						<select id="chp-item-purpose-mode" name="purpose_mode">
							<option value="none" <?php selected( $item->purpose_mode, 'none' ); ?>><?php esc_html_e( 'Not asked', 'clubhouse-planner' ); ?></option>
							<option value="list" <?php selected( $item->purpose_mode, 'list' ); ?>><?php esc_html_e( 'Choose from a list', 'clubhouse-planner' ); ?></option>
							<option value="text" <?php selected( $item->purpose_mode, 'text' ); ?>><?php esc_html_e( 'Free text', 'clubhouse-planner' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'What the booking form asks about the purpose of the booking. The booking form will use this when it can book other items.', 'clubhouse-planner' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chp-item-purposes"><?php esc_html_e( 'Purpose list', 'clubhouse-planner' ); ?></label></th>
					<td>
						<textarea id="chp-item-purposes" class="large-text" rows="5" name="purposes"><?php echo esc_textarea( implode( "\n", CHP_Items::purposes( $item ) ) ); ?></textarea>
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
	<?php endif; ?>
</div>
