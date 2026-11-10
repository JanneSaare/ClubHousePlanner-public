<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chp_booking_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chp_bookings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}chp_items" );

wp_clear_scheduled_hook( 'chp_daily_reminders' );

delete_option( 'chp_settings' );
delete_option( 'chp_db_version' );

remove_role( 'chp_housekeeper' );
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'manage_clubhouse_bookings' );
}
