<?php
defined( 'ABSPATH' ) || exit;

/** @var array $settings */
$name = CHP_Settings::OPTION;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Club House settings', 'clubhouse-planner' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'chp_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="chp-notify"><?php esc_html_e( 'Notification emails', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="text" id="chp-notify" class="large-text" name="<?php echo esc_attr( $name ); ?>[notify_emails]" value="<?php echo esc_attr( $settings['notify_emails'] ); ?>">
					<p class="description"><?php esc_html_e( 'Comma separated. Users with the "Club House Housekeeper" role are always notified as well.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-treasurer"><?php esc_html_e( 'Treasurer', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="text" id="chp-treasurer" class="large-text" name="<?php echo esc_attr( $name ); ?>[treasurer_emails]" value="<?php echo esc_attr( $settings['treasurer_emails'] ); ?>">
					<p class="description"><?php esc_html_e( 'Comma separated. Leave empty to turn payment reminders off.', 'clubhouse-planner' ); ?></p>
					<p>
						<label for="chp-treasurer-days"><?php esc_html_e( 'Send the reminder', 'clubhouse-planner' ); ?></label>
						<input type="number" id="chp-treasurer-days" class="small-text" min="1" max="365" step="1" name="<?php echo esc_attr( $name ); ?>[treasurer_days]" value="<?php echo esc_attr( (string) $settings['treasurer_days'] ); ?>">
						<?php esc_html_e( 'days before the stay', 'clubhouse-planner' ); ?>
					</p>
					<p class="description"><?php esc_html_e( 'The treasurer gets one email per booking that is still awaiting payment when the stay is this close. It is logged in the booking history.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-from-email"><?php esc_html_e( 'Sender address', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="email" id="chp-from-email" class="regular-text" name="<?php echo esc_attr( $name ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" placeholder="stugan@example.com">
					<input type="text" id="chp-from-name" class="regular-text" name="<?php echo esc_attr( $name ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>" placeholder="<?php esc_attr_e( 'Sender name', 'clubhouse-planner' ); ?>">
					<p class="description"><?php esc_html_e( 'Address and name shown as the sender ("From") of booking emails, e.g. Svinningeudd Stugan <stugan@example.com>. Leave the address empty to use the site default. Replies still go to the housekeepers. Your mail server must allow sending from this address.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-payment"><?php esc_html_e( 'Payment instructions', 'clubhouse-planner' ); ?></label></th>
				<td>
					<textarea id="chp-payment" class="large-text" rows="6" name="<?php echo esc_attr( $name ); ?>[payment_instructions]"><?php echo esc_textarea( $settings['payment_instructions'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Sent when a request is approved. Placeholders: {booking_id}, {name}, {fee}, {date}', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-swish"><?php esc_html_e( 'Swish number', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="text" id="chp-swish" class="regular-text" name="<?php echo esc_attr( $name ); ?>[swish_number]" value="<?php echo esc_attr( $settings['swish_number'] ); ?>" placeholder="1234567890">
					<p class="description"><?php esc_html_e( 'Optional. When set, the approval email gets a "Pay with Swish" link with this number, the fee and the booking number already filled in. Leave empty to skip the link.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-key-box"><?php esc_html_e( 'Key box code', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="text" id="chp-key-box" class="regular-text" name="<?php echo esc_attr( $name ); ?>[key_box_code]" value="<?php echo esc_attr( $settings['key_box_code'] ); ?>" autocomplete="off">
					<p class="description"><?php esc_html_e( 'Optional. The code to the key box. It is included in the Confirmed email (after payment has been received) through the {key_box} placeholder. Leave empty to skip it.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-mail-select"><?php esc_html_e( 'Emails', 'clubhouse-planner' ); ?></label></th>
				<td>
					<?php $mail_types = CHP_Mailer::email_defaults(); ?>
					<div class="chp-mail-editor">
						<p>
							<select id="chp-mail-select">
								<?php foreach ( $mail_types as $key => $default ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" data-placeholders="<?php echo esc_attr( implode( ', ', $default['placeholders'] ) ); ?>"><?php echo esc_html( $default['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button" id="chp-mail-reset"><?php esc_html_e( 'Reset to default', 'clubhouse-planner' ); ?></button>
						</p>
						<p>
							<label for="chp-mail-subject"><strong><?php esc_html_e( 'Subject', 'clubhouse-planner' ); ?></strong></label>
							<input type="text" id="chp-mail-subject" class="large-text">
						</p>
						<?php
						wp_editor(
							CHP_Mailer::body_template( (string) array_key_first( $mail_types ) ),
							'chp-mail-body',
							array(
								'textarea_name' => 'chp_mail_body_current',
								'textarea_rows' => 16,
								'media_buttons' => false,
								'teeny'         => true,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Subject placeholders: {site}, {booking_id}, {name}, {date}. Leave the subject empty to use the default.', 'clubhouse-planner' ); ?></p>
						<p class="description"><?php esc_html_e( 'Body placeholders for this email:', 'clubhouse-planner' ); ?> <code id="chp-mail-placeholders"></code></p>
						<p class="description"><?php esc_html_e( '{key_box} inserts the key box code from the setting above. It is only available in the Confirmed email and is left out when no code is set.', 'clubhouse-planner' ); ?></p>
					</div>
					<?php foreach ( $mail_types as $key => $default ) : ?>
						<input type="hidden" data-chp-subject="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>[subjects][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $settings['subjects'][ $key ] ?? '' ) ); ?>">
						<textarea hidden data-chp-body="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name ); ?>[bodies][<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( CHP_Mailer::body_template( $key ) ); ?></textarea>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-streets"><?php esc_html_e( 'Standard-fee streets', 'clubhouse-planner' ); ?></label></th>
				<td>
					<textarea id="chp-streets" class="large-text" rows="8" name="<?php echo esc_attr( $name ); ?>[local_streets]"><?php echo esc_textarea( $settings['local_streets'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One street name per line. These streets are listed in the booking form and get the standard fee. Other streets need a fee agreed by the housekeeper.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-addresses"><?php esc_html_e( 'Standard-fee addresses', 'clubhouse-planner' ); ?></label></th>
				<td>
					<textarea id="chp-addresses" class="large-text" rows="6" name="<?php echo esc_attr( $name ); ?>[local_addresses]"><?php echo esc_textarea( $settings['local_addresses'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One address per line, e.g. Storgatan 12B. Single addresses on other streets that also get the standard fee.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Booking types', 'clubhouse-planner' ); ?></th>
				<td>
					<table class="chp-slot-settings">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Enabled', 'clubhouse-planner' ); ?></th>
								<th><?php esc_html_e( 'Type', 'clubhouse-planner' ); ?></th>
								<th><?php esc_html_e( 'Caption', 'clubhouse-planner' ); ?></th>
								<th><?php esc_html_e( 'Start time', 'clubhouse-planner' ); ?></th>
								<th><?php esc_html_e( 'End time', 'clubhouse-planner' ); ?></th>
								<th><?php esc_html_e( 'Normal price (SEK)', 'clubhouse-planner' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( CHP_Bookings::slot_definitions() as $key => $slot ) : ?>
								<?php $field = $name . '[slots][' . $key . ']'; ?>
								<tr>
									<td><input type="checkbox" id="chp-slot-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $field ); ?>[enabled]" value="1" <?php checked( $slot['enabled'] ); ?>></td>
									<td><label for="chp-slot-<?php echo esc_attr( $key ); ?>"><?php echo CHP_Admin::slot_badge( (object) array( 'slot' => $key ), 'small' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in slot_badge(). ?></label></td>
									<td><input type="text" class="regular-text" maxlength="40" name="<?php echo esc_attr( $field ); ?>[caption]" value="<?php echo esc_attr( (string) ( CHP_Settings::slot_settings()[ $key ]['caption'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $slot['label'] ); ?>"></td>
									<td><input type="time" name="<?php echo esc_attr( $field ); ?>[start]" value="<?php echo esc_attr( $slot['start'] ); ?>" required></td>
									<td><input type="time" name="<?php echo esc_attr( $field ); ?>[end]" value="<?php echo esc_attr( $slot['end'] ); ?>" required></td>
									<td><input type="number" class="small-text" min="0" step="1" name="<?php echo esc_attr( $field ); ?>[fee]" value="<?php echo esc_attr( (string) $slot['fee'] ); ?>"> kr</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'The caption is the name shown together with the hours, e.g. "Whole day 11:00–11:00". Leave it empty to use the default name.', 'clubhouse-planner' ); ?> <?php esc_html_e( 'An end time that is not after the start time means the next day, e.g. 00:00 is midnight. Changed times apply to new requests only; existing bookings keep their times.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Public calendar', 'clubhouse-planner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_pending]" value="1" <?php checked( ! empty( $settings['show_pending'] ) ); ?>>
						<?php esc_html_e( 'Show unhandled requests as preliminary', 'clubhouse-planner' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-calendar-width"><?php esc_html_e( 'Calendar width', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="number" id="chp-calendar-width" class="small-text" min="30" max="100" step="1" name="<?php echo esc_attr( $name ); ?>[calendar_width]" value="<?php echo esc_attr( (string) (int) $settings['calendar_width'] ); ?>"> %
					<p class="description"><?php esc_html_e( 'Maximum width of the public calendar as a percentage of the available width (30–100). The calendar is centered.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-form-width"><?php esc_html_e( 'Booking form width', 'clubhouse-planner' ); ?></label></th>
				<td>
					<input type="number" id="chp-form-width" class="small-text" min="30" max="100" step="1" name="<?php echo esc_attr( $name ); ?>[form_width]" value="<?php echo esc_attr( (string) (int) $settings['form_width'] ); ?>"> %
					<p class="description"><?php esc_html_e( 'Maximum width of the booking form as a percentage of the available width (30–100). The form is centered.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="chp-house-rules"><?php esc_html_e( 'House rules page', 'clubhouse-planner' ); ?></label></th>
				<td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => $name . '[house_rules_page]',
							'id'                => 'chp-house-rules',
							'selected'          => (int) $settings['house_rules_page'],
							'show_option_none'  => __( '— None —', 'clubhouse-planner' ),
							'option_none_value' => 0,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'The page with the house rules. The booking form then requires the requester to confirm that they have read and understood them, and shows the page in a pop-up window. Choose "None" to leave the confirmation out.', 'clubhouse-planner' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
