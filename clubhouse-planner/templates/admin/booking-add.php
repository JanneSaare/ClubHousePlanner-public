<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var string $error
 */
$errors = CHP_Admin::add_errors();
$slots  = CHP_Bookings::slots();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Add booking', 'clubhouse-planner' ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-bookings' ) ); ?>">&larr; <?php esc_html_e( 'Back to all bookings', 'clubhouse-planner' ); ?></a></p>
	<p><?php esc_html_e( 'Use this to register a booking yourself. It is confirmed immediately and no email is sent to anyone.', 'clubhouse-planner' ); ?></p>

	<?php if ( isset( $errors[ $error ] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chp-card">
		<input type="hidden" name="action" value="chp_add_booking">
		<?php wp_nonce_field( 'chp_add_booking' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="chp-add-date"><?php esc_html_e( 'Date', 'clubhouse-planner' ); ?></label></th>
				<td><input type="date" id="chp-add-date" name="date" required></td>
			</tr>
			<tr>
				<th><label for="chp-add-slot"><?php esc_html_e( 'Booking type', 'clubhouse-planner' ); ?></label></th>
				<td>
					<select id="chp-add-slot" name="slot" required>
						<?php foreach ( $slots as $key => $slot ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $slot['label'] . ' (' . $slot['start'] . '–' . $slot['end'] . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="chp-add-name"><?php esc_html_e( 'Name', 'clubhouse-planner' ); ?></label></th>
				<td><input type="text" id="chp-add-name" name="name" class="regular-text" required></td>
			</tr>
			<tr>
				<th><label for="chp-add-email"><?php esc_html_e( 'Email', 'clubhouse-planner' ); ?></label></th>
				<td><input type="email" id="chp-add-email" name="email" class="regular-text"> <span class="description"><?php esc_html_e( 'Optional. Nothing is sent to this address.', 'clubhouse-planner' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="chp-add-phone"><?php esc_html_e( 'Phone', 'clubhouse-planner' ); ?></label></th>
				<td><input type="text" id="chp-add-phone" name="phone" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="chp-add-address"><?php esc_html_e( 'Address', 'clubhouse-planner' ); ?></label></th>
				<td><input type="text" id="chp-add-address" name="address" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="chp-add-purpose"><?php esc_html_e( 'Purpose', 'clubhouse-planner' ); ?></label></th>
				<td>
					<select id="chp-add-purpose" name="purpose">
						<option value=""></option>
						<?php foreach ( CHP_Bookings::purposes() as $purpose ) : ?>
							<option value="<?php echo esc_attr( $purpose ); ?>"><?php echo esc_html( $purpose ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="chp-add-fee"><?php esc_html_e( 'Fee (SEK)', 'clubhouse-planner' ); ?></label></th>
				<td><input type="number" id="chp-add-fee" name="fee" min="0" step="1" class="small-text" value="0"> kr <span class="description"><?php esc_html_e( '0 means free of charge.', 'clubhouse-planner' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="chp-add-message"><?php esc_html_e( 'Message', 'clubhouse-planner' ); ?></label></th>
				<td><textarea id="chp-add-message" name="message" rows="3" class="large-text"></textarea></td>
			</tr>
		</table>
		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Add booking', 'clubhouse-planner' ); ?></button></p>
	</form>
</div>
