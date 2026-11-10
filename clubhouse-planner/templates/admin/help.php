<?php
defined( 'ABSPATH' ) || exit;

$settings_url = admin_url( 'admin.php?page=chp-settings' );
$bookings_url = admin_url( 'admin.php?page=chp-bookings' );
$can_settings = current_user_can( 'manage_options' );
?>
<div class="wrap chp-help">
	<h1><?php esc_html_e( 'Club House instructions', 'clubhouse-planner' ); ?></h1>

	<p><?php esc_html_e( 'This page explains how a booking request is handled, which emails are sent and how the settings work.', 'clubhouse-planner' ); ?></p>

	<ul class="chp-help-toc">
		<li><a href="#chp-help-flow"><?php esc_html_e( 'Handling a booking', 'clubhouse-planner' ); ?></a></li>
		<li><a href="#chp-help-add"><?php esc_html_e( 'Adding a booking yourself', 'clubhouse-planner' ); ?></a></li>
		<li><a href="#chp-help-statuses"><?php esc_html_e( 'Statuses', 'clubhouse-planner' ); ?></a></li>
		<li><a href="#chp-help-emails"><?php esc_html_e( 'Emails', 'clubhouse-planner' ); ?></a></li>
		<li><a href="#chp-help-addresses"><?php esc_html_e( 'Addresses and fees', 'clubhouse-planner' ); ?></a></li>
		<li><a href="#chp-help-items"><?php esc_html_e( 'Items, blocked periods and prices', 'clubhouse-planner' ); ?></a></li>
		<li><a href="#chp-help-other"><?php esc_html_e( 'Other settings', 'clubhouse-planner' ); ?></a></li>
	</ul>

	<h2 id="chp-help-flow"><?php esc_html_e( 'Handling a booking', 'clubhouse-planner' ); ?></h2>
	<ol>
		<li><?php esc_html_e( 'Someone fills in the booking form on the website. They get a confirmation email and the housekeepers get a notification.', 'clubhouse-planner' ); ?></li>
		<li>
			<?php
			printf(
				/* translators: %s: link to the bookings page */
				esc_html__( 'Open the request under %s. The page shows the requester, the period, the fee and other bookings the day before, the same day and the day after.', 'clubhouse-planner' ),
				'<a href="' . esc_url( $bookings_url ) . '">' . esc_html__( 'Club House → Bookings', 'clubhouse-planner' ) . '</a>'
			);
			?>
		</li>
		<li><?php esc_html_e( 'Approve the request (the requester gets payment instructions) or deny it (the requester gets your reason).', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'If the booking is free, tick "Free of charge" next to the fee before approving. The payment email is skipped and the booking is confirmed directly, so the requester gets the confirmation email straight away.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'When the payment has arrived, press "Payment received – confirm booking". The requester gets a confirmation.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'If the booking has to be called off later, press "Cancel booking". The period becomes free again and the requester is told.', 'clubhouse-planner' ); ?></li>
	</ol>
	<p><?php esc_html_e( 'Every action has an optional message that is included in the email to the requester. Every change is saved in the history of the booking.', 'clubhouse-planner' ); ?></p>
	<p><?php esc_html_e( 'The bookings list shows the open bookings (request received and awaiting payment) by default. Use the tabs to see all bookings or one status, and the date filter to include older bookings.', 'clubhouse-planner' ); ?></p>

	<h2 id="chp-help-add"><?php esc_html_e( 'Adding a booking yourself', 'clubhouse-planner' ); ?></h2>
	<p><?php esc_html_e( 'Use this when the booking was agreed outside the website, for example by phone or in person, or to block the house for your own use. Nothing is sent to anyone.', 'clubhouse-planner' ); ?></p>
	<ol>
		<li><?php esc_html_e( 'Press "Add booking" on the bookings page, or choose Club House → Add booking in the menu.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Choose the date and booking type and enter the name. Email, phone, address, purpose and message are optional. The fee is 0 (free of charge) unless you enter an amount.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'The booking is confirmed immediately and blocks the period in the public calendar. No email is sent, not to the booker, the housekeepers or the treasurer.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'The booking cannot be saved if it overlaps an approved or confirmed booking. Past dates are allowed, so you can register earlier bookings.', 'clubhouse-planner' ); ?></li>
	</ol>
	<p><?php esc_html_e( 'Bookings you add are marked in the history with your name. They can be cancelled like any other booking; if no email address was entered, no email is sent.', 'clubhouse-planner' ); ?></p>

	<h2 id="chp-help-statuses"><?php esc_html_e( 'Statuses', 'clubhouse-planner' ); ?></h2>
	<table class="widefat striped chp-help-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Status', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Meaning', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Blocks the period?', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Possible next step', 'clubhouse-planner' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echo esc_html( CHP_Bookings::status_label( CHP_Bookings::PENDING ) ); ?></td>
				<td><?php esc_html_e( 'A new request that nobody has handled yet.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'No. Shown as preliminary in the public calendar if that is enabled in the settings.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'Approve or deny.', 'clubhouse-planner' ); ?></td>
			</tr>
			<tr>
				<td><?php echo esc_html( CHP_Bookings::status_label( CHP_Bookings::AWAITING_PAYMENT ) ); ?></td>
				<td><?php esc_html_e( 'Approved and the fee has been set. The booking is preliminary until the payment has been received.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'Yes. New requests for the same time are rejected.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'Confirm when paid, deny or cancel.', 'clubhouse-planner' ); ?></td>
			</tr>
			<tr>
				<td><?php echo esc_html( CHP_Bookings::status_label( CHP_Bookings::CONFIRMED ) ); ?></td>
				<td><?php esc_html_e( 'The payment has been received and the booking is fully confirmed.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'Yes.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'Cancel.', 'clubhouse-planner' ); ?></td>
			</tr>
			<tr>
				<td><?php echo esc_html( CHP_Bookings::status_label( CHP_Bookings::DENIED ) ); ?></td>
				<td><?php esc_html_e( 'The request was not accepted.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'No.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'None. The status is final.', 'clubhouse-planner' ); ?></td>
			</tr>
			<tr>
				<td><?php echo esc_html( CHP_Bookings::status_label( CHP_Bookings::CANCELLED ) ); ?></td>
				<td><?php esc_html_e( 'A booking that was approved or confirmed has been called off.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'No. The period is free again.', 'clubhouse-planner' ); ?></td>
				<td><?php esc_html_e( 'None. The status is final.', 'clubhouse-planner' ); ?></td>
			</tr>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'A request cannot be approved if an approved or confirmed booking already overlaps the period. Deny the request or cancel the other booking first.', 'clubhouse-planner' ); ?></p>

	<h2 id="chp-help-emails"><?php esc_html_e( 'Emails', 'clubhouse-planner' ); ?></h2>
	<table class="widefat striped chp-help-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'When', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Email', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Sent to', 'clubhouse-planner' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$rows = array(
				array( __( 'A request is submitted', 'clubhouse-planner' ), 'request_received', __( 'The requester', 'clubhouse-planner' ) ),
				array( __( 'A request is submitted', 'clubhouse-planner' ), 'new_request', __( 'The housekeepers', 'clubhouse-planner' ) ),
				array( __( 'You approve the request', 'clubhouse-planner' ), 'approved', __( 'The requester', 'clubhouse-planner' ) ),
				array( __( 'You confirm the payment', 'clubhouse-planner' ), 'confirmed', __( 'The requester', 'clubhouse-planner' ) ),
				array( __( 'You deny the request', 'clubhouse-planner' ), 'denied', __( 'The requester', 'clubhouse-planner' ) ),
				array( __( 'You cancel the booking', 'clubhouse-planner' ), 'cancelled', __( 'The requester', 'clubhouse-planner' ) ),
			);
			$defs = CHP_Mailer::email_defaults();
			foreach ( $rows as $row ) :
				?>
				<tr>
					<td><?php echo esc_html( $row[0] ); ?></td>
					<td><?php echo esc_html( $defs[ $row[1] ]['label'] ); ?></td>
					<td><?php echo esc_html( $row[2] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'The housekeepers are the addresses entered as notification emails in the settings plus all users with the "Club House Housekeeper" role. The notification email is sent with the requester as reply-to, so you can answer directly.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'If an email to a requester cannot be sent, the booking is still updated. You are told on the booking page and the error is written to the history.', 'clubhouse-planner' ); ?></li>
	</ul>

	<h3><?php esc_html_e( 'Editing the emails', 'clubhouse-planner' ); ?></h3>
	<p>
		<?php
		if ( $can_settings ) {
			printf(
				/* translators: %s: link to the settings page */
				esc_html__( 'Go to %s and find "Emails". Choose which email to edit in the list; subject and text are shown in the editor.', 'clubhouse-planner' ),
				'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Club House → Settings', 'clubhouse-planner' ) . '</a>'
			);
		} else {
			esc_html_e( 'Administrators edit the emails under Club House → Settings → Emails. Choose which email to edit in the list; subject and text are shown in the editor.', 'clubhouse-planner' );
		}
		?>
	</p>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'Emails are sent as HTML. Use the editor to format the text and add links.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Placeholders in curly brackets, for example {name}, are replaced with the real values when the email is sent. The available placeholders are listed under the editor and differ between the emails.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( '{summary} is a table with the booking number, name, address, period, purpose, status and fee. {note} is the optional message you write when handling the booking and is left out when empty.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( '"Reset to default" restores the original subject and text of the email shown. Nothing is saved until you press Save changes.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Leaving the subject empty uses the default subject.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'The text for how to pay is written separately in the setting "Payment instructions" and is inserted where the approved email contains {payment_instructions}. It can use {booking_id}, {name}, {fee} and {date}.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'If a Swish number is entered in the setting "Swish number", a "Pay with Swish" button is added after the payment instructions in the approved email. It opens Swish with the number, the fee and the booking number already filled in. Below the button is a QR code with the same details, for people reading the email on a computer who can scan it with the Swish app on their phone. The button and QR code are left out when no number is set or the booking has no fee.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'If a code is entered in the setting "Key box code", it is shown in the Confirmed email, which is sent after the payment has been received (or straight away for free bookings). It is inserted where the email contains {key_box} and left out when no code is set. If you have edited the Confirmed email, add {key_box} to the text yourself or use "Reset to default".', 'clubhouse-planner' ); ?></li>
	</ul>

	<h2 id="chp-help-addresses"><?php esc_html_e( 'Addresses and fees', 'clubhouse-planner' ); ?></h2>
	<p><?php esc_html_e( 'The fee depends on where the requester lives. Residents of the listed streets and addresses pay the standard fee. For everyone else the housekeeper agrees a fee.', 'clubhouse-planner' ); ?></p>

	<h3><?php esc_html_e( 'The booking form', 'clubhouse-planner' ); ?></h3>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'The requester chooses a street from the list "Standard-fee streets", or "Other street" and types the street name. If no streets are listed, the street is always typed.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'The street number is entered separately, for example 6 or 6a. A number that does not look like that is rejected. The address is saved as street, number and capital letter, for example "Storgatan 6A".', 'clubhouse-planner' ); ?></li>
	</ul>

	<h3><?php esc_html_e( 'What gets the standard fee', 'clubhouse-planner' ); ?></h3>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'Standard-fee streets: one street name per line. Every address that starts with one of these street names gets the standard fee, whatever the number. "Storgatan" matches "Storgatan 6" and "Storgatan 12B", but not "Storgatsvägen 3".', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Standard-fee addresses: one full address per line, for single houses on other streets, for example "Parkvägen 12B". Capital letters and spaces do not matter, so "parkvägen 12 b" is the same address.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Normal price: set per booking type under Booking types in the settings. It is the fee used for those addresses.', 'clubhouse-planner' ); ?></li>
	</ul>

	<h3><?php esc_html_e( 'When you approve a request', 'clubhouse-planner' ); ?></h3>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'Standard-fee address: the fee field is filled in with the standard fee. You may change it for this booking.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Other address: the booking is marked "Other address" and the fee field is empty. You must enter the agreed fee before you can approve. Contact the requester first; the email to the housekeepers states this.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'The fee is saved on the booking when you approve. Changing the standard fee later does not change existing bookings.', 'clubhouse-planner' ); ?></li>
	</ul>

	<h2 id="chp-help-items"><?php esc_html_e( 'Items, blocked periods and prices', 'clubhouse-planner' ); ?></h2>

	<h3><?php esc_html_e( 'Items', 'clubhouse-planner' ); ?></h3>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'Administrators add things that can be booked besides the club house, for example a boule court or surfboards, under Club House → Items. Each item has its own name, description, number of units, price and rules.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'An item can have several units, which are identical copies such as three surfboards. The units are named A, B, C and so on. A request gets the first free unit, and you can move a booking to another unit on the booking page. The letter is only shown when an item has more than one unit.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( '"Fixed booking types" uses the whole day, day and evening types from the settings. "The customer chooses start and end" lets the customer enter their own start and end, with a price per booking, hour or day, an optional weekend price and an optional shortest and longest booking.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'The price is calculated when the request is made and becomes the fee when you approve it. You can change it. A booking of 24 hours or less counts as one day, also when it crosses midnight.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'When you add a booking yourself you choose the item first. You can also choose a unit.', 'clubhouse-planner' ); ?></li>
	</ul>

	<h3><?php esc_html_e( 'Blocked periods', 'clubhouse-planner' ); ?></h3>
	<ul class="chp-help-list">
		<li><?php esc_html_e( 'Under Club House → Blocked periods you block times when an item cannot be booked, for example repairs or a closed season. Customers see the days as "Not available", or the text you enter. Tick "Repeat every year" for a seasonal closure.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( 'Bookings that already fall inside a new blocked period are not changed. They are listed after you save, so that you can deny or cancel them.', 'clubhouse-planner' ); ?></li>
	</ul>

	<h3><?php esc_html_e( 'On the website', 'clubhouse-planner' ); ?></h3>
	<ul class="chp-help-list">
		<li><?php esc_html_e( '[clubhouse_calendar item="2"] and [clubhouse_booking_form item="2"] show the calendar and the booking form of another item. The item number is shown in the item list, together with ready-made shortcodes.', 'clubhouse-planner' ); ?></li>
		<li><?php esc_html_e( '[clubhouse_calendars] shows the calendars of all active items side by side. Use [clubhouse_calendars items="1,2"] to choose which ones. For an item with several units the calendar shows how many units are free.', 'clubhouse-planner' ); ?></li>
	</ul>

	<h2 id="chp-help-other"><?php esc_html_e( 'Other settings', 'clubhouse-planner' ); ?></h2>
	<ul class="chp-help-list">
		<li><strong><?php esc_html_e( 'Notification emails', 'clubhouse-planner' ); ?></strong> – <?php esc_html_e( 'Who gets the email about new requests, in addition to the users with the housekeeper role. Separate addresses with commas.', 'clubhouse-planner' ); ?></li>
		<li><strong><?php esc_html_e( 'Booking types', 'clubhouse-planner' ); ?></strong> – <?php esc_html_e( 'The whole day, day and evening bookings. Each type can be switched off, and its start and end time changed. An end time that is not after the start time means the next day, so 00:00 is midnight. At least one type must be enabled. Changed times apply to new requests only; existing bookings keep their times.', 'clubhouse-planner' ); ?></li>
		<li><strong><?php esc_html_e( 'House rules page', 'clubhouse-planner' ); ?></strong> – <?php esc_html_e( 'Choose the page with the house rules. The booking form then has a mandatory checkbox "I have read and understood the house rules" and the link opens the page in a pop-up window. Without a page the checkbox is not shown.', 'clubhouse-planner' ); ?></li>
		<li><strong><?php esc_html_e( 'Public calendar', 'clubhouse-planner' ); ?></strong> – <?php esc_html_e( 'If enabled, requests that nobody has handled yet are shown as preliminary. Approved and confirmed bookings are always shown as booked. No personal data is shown in the calendar.', 'clubhouse-planner' ); ?></li>
	</ul>
	<p class="description"><?php esc_html_e( 'On the website, the calendar is added with the shortcode [clubhouse_calendar] and the booking form with [clubhouse_booking_form].', 'clubhouse-planner' ); ?></p>
</div>
