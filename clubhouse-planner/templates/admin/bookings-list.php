<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var array  $bookings
 * @var array  $counts
 * @var string $status
 * @var int    $paged
 * @var int    $pages
 * @var bool   $all          True when bookings of all dates are shown.
 * @var int    $recent_total Upcoming bookings and those ended in the last CHP_Admin::RECENT_DAYS days.
 * @var int    $all_total
 * @var int    $open_total  Bookings waiting for the housekeeper (request received, awaiting payment).
 */
$base_url   = admin_url( 'admin.php?page=chp-bookings' );
$status_url = $all ? add_query_arg( 'period', 'all', $base_url ) : $base_url;
$period_url = $status ? add_query_arg( 'status', $status, $base_url ) : $base_url;
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Club House bookings', 'clubhouse-planner' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=chp-add-booking' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add booking', 'clubhouse-planner' ); ?></a>
	<hr class="wp-header-end">

	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( $status_url ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>">
				<?php esc_html_e( 'Open', 'clubhouse-planner' ); ?> <span class="count">(<?php echo (int) $open_total; ?>)</span>
			</a>
		</li>
		<li>
			| <a href="<?php echo esc_url( add_query_arg( 'status', 'all', $status_url ) ); ?>" class="<?php echo 'all' === $status ? 'current' : ''; ?>">
				<?php esc_html_e( 'All', 'clubhouse-planner' ); ?> <span class="count">(<?php echo (int) array_sum( $counts ); ?>)</span>
			</a>
		</li>
		<?php foreach ( CHP_Bookings::statuses() as $key => $label ) : ?>
			<li>
				| <a href="<?php echo esc_url( add_query_arg( 'status', $key, $status_url ) ); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>">
					<?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) ( $counts[ $key ] ?? 0 ); ?>)</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<ul class="subsubsub chp-period-filter">
		<li>
			<a href="<?php echo esc_url( $period_url ); ?>" class="<?php echo $all ? '' : 'current'; ?>">
				<?php
				/* translators: %d: number of days */
				echo esc_html( sprintf( __( 'Upcoming and last %d days', 'clubhouse-planner' ), CHP_Admin::RECENT_DAYS ) );
				?>
				<span class="count">(<?php echo (int) $recent_total; ?>)</span>
			</a>
		</li>
		<li>
			| <a href="<?php echo esc_url( add_query_arg( 'period', 'all', $period_url ) ); ?>" class="<?php echo $all ? 'current' : ''; ?>">
				<?php esc_html_e( 'All dates', 'clubhouse-planner' ); ?> <span class="count">(<?php echo (int) $all_total; ?>)</span>
			</a>
		</li>
	</ul>

	<table class="widefat striped chp-table">
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'Requested by', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Period', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Purpose', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Status', 'clubhouse-planner' ); ?></th>
				<th><?php esc_html_e( 'Received', 'clubhouse-planner' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $bookings ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No bookings found.', 'clubhouse-planner' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $bookings as $b ) : ?>
				<?php $link = add_query_arg( 'booking', (int) $b->id, $base_url ); ?>
				<?php $past = CHP_Bookings::is_past( $b ); ?>
				<tr<?php echo $past ? ' class="chp-row--past"' : ''; ?>>
					<td><a href="<?php echo esc_url( $link ); ?>"><?php echo (int) $b->id; ?></a></td>
					<td>
						<a href="<?php echo esc_url( $link ); ?>"><strong><?php echo esc_html( $b->name ); ?></strong></a><br>
						<?php echo esc_html( $b->email ); ?>
						<?php if ( '' !== (string) $b->address ) : ?>
							<br><?php echo esc_html( $b->address ); ?>
							<?php if ( ! CHP_Pricing::is_priced( CHP_Items::find( (int) $b->item_id ) ) && ! CHP_Bookings::is_local( $b ) ) : ?>
								<span class="chp-fee-tag chp-fee-tag--other"><?php esc_html_e( 'Other address', 'clubhouse-planner' ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( CHP_Items::has_multiple() ) : ?>
							<div><strong><?php echo esc_html( CHP_Items::booking_label( $b ) ); ?></strong></div>
						<?php endif; ?>
						<?php $badge = CHP_Admin::slot_badge( $b ); ?>
						<?php if ( $badge ) : ?>
							<?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in slot_badge(). ?>
							<div><?php echo esc_html( CHP_Admin::booking_date( $b ) ); ?></div>
						<?php else : ?>
							<?php echo esc_html( CHP_Bookings::format_period( $b ) ); ?>
						<?php endif; ?>

						<?php if ( $past ) : ?>
							<div class="chp-past-tag"><span class="dashicons dashicons-backup" aria-hidden="true"></span> <?php esc_html_e( 'Past booking', 'clubhouse-planner' ); ?></div>
						<?php endif; ?>

						<?php if ( ! $past && in_array( $b->status, CHP_Bookings::active_statuses(), true ) ) : ?>
							<?php $neighbours = CHP_Bookings::neighbours( $b ); ?>
							<?php foreach ( CHP_Admin::neighbour_labels() as $offset => $label ) : ?>
								<?php
								if ( empty( $neighbours[ $offset ] ) ) {
									continue;
								}
								$conflict = (bool) array_filter( $neighbours[ $offset ], fn( object $n ): bool => $n->overlaps );
								?>
								<div class="chp-nearby-mini<?php echo $conflict ? ' chp-nearby-mini--conflict' : ''; ?>">
									<span class="dashicons dashicons-warning" aria-hidden="true"></span>
									<?php echo esc_html( $label ); ?>:
									<?php foreach ( $neighbours[ $offset ] as $n ) : ?>
										<?php echo CHP_Admin::slot_badge( $n, 'small' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in slot_badge(). ?>
									<?php endforeach; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $b->purpose ); ?></td>
					<td><span class="chp-status chp-status--<?php echo esc_attr( $b->status ); ?>"><?php echo esc_html( CHP_Bookings::status_label( $b->status ) ); ?></span></td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $b->created_at ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
</div>
