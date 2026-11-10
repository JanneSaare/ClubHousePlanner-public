<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var string $error
 */
$errors = CHP_Admin::add_errors();
$slots  = CHP_Bookings::slots();
$items  = CHP_Items::all( true );

// What the form needs to know about each item to show the right fields.
$item_data = array();
foreach ( $items as $row ) {
	$item_data[ (int) $row->id ] = array(
		'free'        => CHP_Pricing::is_priced( $row ),
		'units'       => CHP_Items::show_unit( $row ) ? CHP_Items::unit_labels( (int) $row->quantity ) : array(),
		'purposeMode' => $row->purpose_mode,
		'purposes'    => CHP_Items::purpose_options( $row ),
	);
}
$first = $items ? (int) $items[0]->id : 0;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Add booking', 'clubhouse-planner' ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-bookings' ) ); ?>">&larr; <?php esc_html_e( 'Back to all bookings', 'clubhouse-planner' ); ?></a></p>
	<p><?php esc_html_e( 'Use this to register a booking yourself. It is confirmed immediately and no email is sent to anyone.', 'clubhouse-planner' ); ?></p>

	<?php if ( isset( $errors[ $error ] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="chp-card" id="chp-add-form">
		<input type="hidden" name="action" value="chp_add_booking">
		<?php wp_nonce_field( 'chp_add_booking' ); ?>
		<table class="form-table" role="presentation">
			<?php if ( count( $items ) > 1 ) : ?>
				<tr>
					<th><label for="chp-add-item"><?php esc_html_e( 'Item', 'clubhouse-planner' ); ?></label></th>
					<td>
						<select id="chp-add-item" name="item_id">
							<?php foreach ( $items as $row ) : ?>
								<option value="<?php echo (int) $row->id; ?>"><?php echo esc_html( $row->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php else : ?>
				<input type="hidden" id="chp-add-item" name="item_id" value="<?php echo (int) $first; ?>">
			<?php endif; ?>
			<tr data-chp-mode="slots">
				<th><label for="chp-add-date"><?php esc_html_e( 'Date', 'clubhouse-planner' ); ?></label></th>
				<td><input type="date" id="chp-add-date" name="date" required></td>
			</tr>
			<tr data-chp-mode="slots">
				<th><label for="chp-add-slot"><?php esc_html_e( 'Booking type', 'clubhouse-planner' ); ?></label></th>
				<td>
					<select id="chp-add-slot" name="slot" required>
						<?php foreach ( $slots as $key => $slot ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $slot['label'] . ' (' . $slot['start'] . '–' . $slot['end'] . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr data-chp-mode="free" hidden>
				<th><label for="chp-add-from"><?php esc_html_e( 'From', 'clubhouse-planner' ); ?></label></th>
				<td><input type="datetime-local" id="chp-add-from" name="from" required disabled></td>
			</tr>
			<tr data-chp-mode="free" hidden>
				<th><label for="chp-add-to"><?php esc_html_e( 'To', 'clubhouse-planner' ); ?></label></th>
				<td><input type="datetime-local" id="chp-add-to" name="to" required disabled></td>
			</tr>
			<tr id="chp-add-unit-row" hidden>
				<th><label for="chp-add-unit"><?php esc_html_e( 'Unit', 'clubhouse-planner' ); ?></label></th>
				<td><select id="chp-add-unit" name="unit" disabled></select></td>
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
			<tr id="chp-add-purpose-row">
				<th><label for="chp-add-purpose"><?php esc_html_e( 'Purpose', 'clubhouse-planner' ); ?></label></th>
				<td>
					<select id="chp-add-purpose-list" name="purpose">
						<option value=""></option>
					</select>
					<input type="text" id="chp-add-purpose-text" name="purpose" class="regular-text" maxlength="190" disabled hidden>
				</td>
			</tr>
			<tr>
				<th><label for="chp-add-fee"><?php esc_html_e( 'Fee (SEK)', 'clubhouse-planner' ); ?></label></th>
				<td><input type="number" id="chp-add-fee" name="fee" min="0" step="1" class="small-text" placeholder="0"> kr <span class="description"><?php esc_html_e( 'Leave empty for free of charge, or for the price from the price list on items where the customer chooses start and end.', 'clubhouse-planner' ); ?></span></td>
			</tr>
			<tr>
				<th><label for="chp-add-message"><?php esc_html_e( 'Message', 'clubhouse-planner' ); ?></label></th>
				<td><textarea id="chp-add-message" name="message" rows="3" class="large-text"></textarea></td>
			</tr>
		</table>
		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Add booking', 'clubhouse-planner' ); ?></button></p>
	</form>
</div>

<script>
( function () {
	var items = <?php echo wp_json_encode( $item_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	var automatic = <?php echo wp_json_encode( __( 'Automatic (first free unit)', 'clubhouse-planner' ) ); ?>;
	var itemField = document.getElementById( 'chp-add-item' );
	var unitRow = document.getElementById( 'chp-add-unit-row' );
	var unitField = document.getElementById( 'chp-add-unit' );
	var purposeRow = document.getElementById( 'chp-add-purpose-row' );
	var purposeList = document.getElementById( 'chp-add-purpose-list' );
	var purposeText = document.getElementById( 'chp-add-purpose-text' );

	// Show or hide a field and keep it out of the submitted form (and out of validation) while hidden.
	function toggle( field, show ) {
		field.hidden = ! show;
		field.disabled = ! show;
	}

	function update() {
		var item = items[ itemField.value ] || { free: false, units: [], purposeMode: 'none', purposes: [] };

		Array.prototype.forEach.call( document.querySelectorAll( '[data-chp-mode]' ), function ( row ) {
			var show = ( row.getAttribute( 'data-chp-mode' ) === 'free' ) === item.free;
			row.hidden = ! show;
			Array.prototype.forEach.call( row.querySelectorAll( 'input, select' ), function ( field ) { field.disabled = ! show; } );
		} );

		// Units: only for items with more than one.
		unitRow.hidden = item.units.length === 0;
		unitField.disabled = item.units.length === 0;
		unitField.innerHTML = '';
		if ( item.units.length ) {
			var auto = document.createElement( 'option' );
			auto.value = '';
			auto.textContent = automatic;
			unitField.appendChild( auto );
			item.units.forEach( function ( unit ) {
				var option = document.createElement( 'option' );
				option.value = unit;
				option.textContent = unit;
				unitField.appendChild( option );
			} );
		}

		// Purpose: a list, free text, or not asked.
		purposeRow.hidden = item.purposeMode !== 'list' && item.purposeMode !== 'text';
		toggle( purposeList, item.purposeMode === 'list' );
		toggle( purposeText, item.purposeMode === 'text' );
		purposeList.innerHTML = '<option value=""></option>';
		( item.purposes || [] ).forEach( function ( purpose ) {
			var option = document.createElement( 'option' );
			option.value = purpose;
			option.textContent = purpose;
			purposeList.appendChild( option );
		} );
		if ( purposeRow.hidden ) {
			purposeList.disabled = true;
			purposeText.disabled = true;
		}
	}

	itemField.addEventListener( 'change', update );
	update();
}() );
</script>
