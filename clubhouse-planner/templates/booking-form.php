<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var string $result
 * @var object $item   The item this form books.
 */
$messages = CHP_Public::form_messages( $item );
$free     = CHP_Pricing::is_priced( $item );
?>
<div class="chp-form-wrap" id="chp-booking-form" style="max-width: <?php echo (int) CHP_Settings::get( 'form_width' ); ?>%;">
	<?php if ( $result && isset( $messages[ $result ] ) ) : ?>
		<div class="chp-notice chp-notice--<?php echo 'success' === $result ? 'success' : 'error'; ?>" role="alert">
			<?php echo esc_html( $messages[ $result ] ); ?>
		</div>
	<?php endif; ?>

	<form class="chp-form" data-item="<?php echo (int) $item->id; ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="chp_submit_booking">
		<input type="hidden" name="chp_item" value="<?php echo (int) $item->id; ?>">
		<?php wp_nonce_field( 'chp_submit_booking', 'chp_nonce' ); ?>

		<input type="hidden" name="chp_ts" value="<?php echo esc_attr( CHP_Public::form_timestamp() ); ?>">

		<?php if ( CHP_Items::has_multiple() ) : ?>
			<h3 class="chp-form__item"><?php echo esc_html( $item->name ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== trim( (string) $item->description ) ) : ?>
			<p class="chp-form__description"><?php echo nl2br( esc_html( $item->description ) ); ?></p>
		<?php endif; ?>

		<p class="chp-hp" aria-hidden="true">
			<label>Website <input type="text" name="chp_website" tabindex="-1" autocomplete="off"></label>
		</p>

		<?php if ( $free ) : ?>
		<fieldset class="chp-fieldset">
			<legend><?php esc_html_e( 'Date and time', 'clubhouse-planner' ); ?></legend>
			<div class="chp-row chp-row--times">
			<p>
			<label for="chp_start"><?php esc_html_e( 'From', 'clubhouse-planner' ); ?> *</label>
					<input type="datetime-local" id="chp_start" name="chp_start" min="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i' ) ); ?>" required>
				</p>
				<p>
					<label for="chp_end"><?php esc_html_e( 'To', 'clubhouse-planner' ); ?> *</label>
					<input type="datetime-local" id="chp_end" name="chp_end" min="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i' ) ); ?>" required>
				</p>
			</div>
			<?php if ( $item->max_duration ) : ?>
				<p class="chp-help">
					<?php
					/* translators: %s: length, e.g. 2 days */
					echo esc_html( sprintf( __( 'The longest booking is %s.', 'clubhouse-planner' ), CHP_Items::format_minutes( (int) $item->max_duration ) ) );
					?>
				</p>
			<?php endif; ?>
			<p class="chp-price" id="chp-price" aria-live="polite"></p>
		</fieldset>
		<?php else : ?>
		<fieldset class="chp-fieldset">
			<legend><?php esc_html_e( 'Date and time', 'clubhouse-planner' ); ?></legend>
			<div class="chp-row">
				<p>
					<label for="chp_date"><?php esc_html_e( 'Date', 'clubhouse-planner' ); ?> *</label>
					<input type="date" id="chp_date" name="chp_date" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required>
				</p>
				<div class="chp-grow" role="radiogroup" aria-labelledby="chp-slot-label">
					<span id="chp-slot-label" class="chp-label"><?php esc_html_e( 'Booking type', 'clubhouse-planner' ); ?> *</span>
					<?php $active_slots = CHP_Bookings::slots(); ?>
					<?php foreach ( $active_slots as $key => $slot ) : ?>
						<label class="chp-slot chp-slot--<?php echo esc_attr( $key ); ?>">
							<input type="radio" name="chp_slot" value="<?php echo esc_attr( $key ); ?>" required<?php checked( 1, count( $active_slots ) ); ?>>
							<?php echo esc_html( $slot['label'] ); ?>
							<span class="chp-slot__time"><?php echo esc_html( '(' . $slot['start'] . ' - ' . $slot['end'] . ')' ); ?></span>
							<?php if ( $slot['fee'] > 0 ) : ?>
								<span class="chp-slot__time chp-slot__fee" data-fee="<?php echo esc_attr( CHP_Bookings::format_fee( $slot['fee'] ) ); ?>" hidden></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</fieldset>
		<?php endif; ?>

		<fieldset class="chp-fieldset">
			<legend><?php esc_html_e( 'Contact details', 'clubhouse-planner' ); ?></legend>
			<div class="chp-row">
				<p>
					<label for="chp_name"><?php esc_html_e( 'Name', 'clubhouse-planner' ); ?> *</label>
					<input type="text" id="chp_name" name="chp_name" maxlength="190" autocomplete="name" required>
				</p>
				<p>
					<label for="chp_email"><?php esc_html_e( 'Email', 'clubhouse-planner' ); ?> *</label>
					<input type="email" id="chp_email" name="chp_email" maxlength="190" autocomplete="email" required>
				</p>
				<p>
					<label for="chp_phone"><?php esc_html_e( 'Phone', 'clubhouse-planner' ); ?></label>
					<input type="tel" id="chp_phone" name="chp_phone" maxlength="50" autocomplete="tel">
				</p>
			</div>
			<?php $streets = CHP_Settings::local_streets(); ?>
			<?php if ( CHP_Items::asks_address( $item ) ) : ?>
			<div class="chp-row chp-row--street">
				<?php if ( $streets ) : ?>
					<p class="chp-grow">
						<label for="chp_street"><?php esc_html_e( 'Street', 'clubhouse-planner' ); ?> *</label>
						<select id="chp_street" name="chp_street" required>
							<option value=""><?php esc_html_e( '— Choose street —', 'clubhouse-planner' ); ?></option>
							<?php foreach ( $streets as $street ) : ?>
								<option value="<?php echo esc_attr( $street ); ?>"><?php echo esc_html( $street ); ?></option>
							<?php endforeach; ?>
							<option value="<?php echo esc_attr( CHP_Public::OTHER_STREET ); ?>"><?php esc_html_e( 'Other street', 'clubhouse-planner' ); ?></option>
						</select>
					</p>
				<?php endif; ?>
				<p class="chp-grow chp-street-other">
					<label for="chp_street_other"><?php echo esc_html( $streets ? __( 'Other street', 'clubhouse-planner' ) : __( 'Street', 'clubhouse-planner' ) ); ?> *</label>
					<input type="text" id="chp_street_other" name="chp_street_other" maxlength="150" autocomplete="address-line1"<?php echo $streets ? '' : ' required'; ?>>
				</p>
				<p>
					<label for="chp_street_no"><?php esc_html_e( 'Street number', 'clubhouse-planner' ); ?> *</label>
					<input type="text" id="chp_street_no" name="chp_street_no" maxlength="8" pattern="\d{1,5}\s?[A-Za-zÅÄÖåäö]?" title="<?php esc_attr_e( 'For example 6 or 6a', 'clubhouse-planner' ); ?>" required>
				</p>
			</div>
			<?php endif; ?>
		</fieldset>

		<fieldset class="chp-fieldset">
			<legend><?php esc_html_e( 'Additional information', 'clubhouse-planner' ); ?></legend>
			<?php if ( 'list' === $item->purpose_mode ) : ?>
			<p>
				<label for="chp_purpose"><?php esc_html_e( 'Purpose', 'clubhouse-planner' ); ?> *</label>
				<select id="chp_purpose" name="chp_purpose" required>
					<option value=""><?php esc_html_e( 'Choose purpose', 'clubhouse-planner' ); ?></option>
					<?php foreach ( CHP_Items::purpose_options( $item ) as $purpose_option ) : ?>
						<option value="<?php echo esc_attr( $purpose_option ); ?>"><?php echo esc_html( $purpose_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php elseif ( 'text' === $item->purpose_mode ) : ?>
			<p>
				<label for="chp_purpose"><?php esc_html_e( 'Purpose', 'clubhouse-planner' ); ?></label>
				<input type="text" id="chp_purpose" name="chp_purpose" maxlength="190">
			</p>
			<?php endif; ?>
			<p>
				<label for="chp_message"><?php esc_html_e( 'Message', 'clubhouse-planner' ); ?></label>
				<textarea id="chp_message" name="chp_message" rows="4"></textarea>
			</p>
		</fieldset>

		<?php $rules_page = CHP_Settings::house_rules_page(); ?>
		<?php if ( $rules_page ) : ?>
			<p class="chp-rules">
				<label>
					<input type="checkbox" name="chp_rules" value="1" required>
					<?php
					printf(
						/* translators: %s: link to the house rules */
						esc_html__( 'I have read and understood the %s', 'clubhouse-planner' ),
						'<a href="' . esc_url( get_permalink( $rules_page ) ) . '" class="chp-rules-link" target="_blank" rel="noopener">' . esc_html__( 'house rules', 'clubhouse-planner' ) . '</a>'
					);
					?>
					*
				</label>
			</p>
		<?php endif; ?>

		<p>
			<button type="submit" class="chp-button"><?php esc_html_e( 'Send booking request', 'clubhouse-planner' ); ?></button>
		</p>
	</form>
</div>
