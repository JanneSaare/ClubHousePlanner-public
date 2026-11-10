<?php
defined( 'ABSPATH' ) || exit;

class CHP_Activator {

	const DB_VERSION = '4';
	const ROLE       = 'chp_housekeeper';
	const ROLE_NAME  = 'Club House Housekeeper';
	const CAP        = 'manage_clubhouse_bookings';

	public static function activate(): void {
		self::create_tables();
		self::add_roles();
		add_option( CHP_Settings::OPTION, CHP_Settings::defaults() );
		CHP_Reminders::schedule();
	}

	public static function deactivate(): void {
		CHP_Reminders::unschedule();
		// Data and roles are kept; they are removed in uninstall.php.
	}

	public static function create_tables(): void {
		global $wpdb;

		$charset  = $wpdb->get_charset_collate();
		$bookings = CHP_Bookings::table();
		$log      = CHP_Bookings::log_table();

		$sql = "CREATE TABLE $bookings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'pending',
			slot varchar(10) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(50) NOT NULL DEFAULT '',
			address varchar(190) NOT NULL DEFAULT '',
			fee int(10) unsigned NULL,
			start_at datetime NOT NULL,
			end_at datetime NOT NULL,
			guests smallint(5) unsigned NOT NULL DEFAULT 0,
			purpose varchar(190) NOT NULL DEFAULT '',
			message text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			treasurer_reminded_at datetime NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY period (start_at,end_at)
		) $charset;
		CREATE TABLE $log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL,
			note text NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'chp_db_version', self::DB_VERSION );
	}

	public static function add_roles(): void {
		// Re-create so a previously stored translated name is replaced; users keep the role.
		remove_role( self::ROLE );
		add_role(
			self::ROLE,
			self::ROLE_NAME,
			array(
				'read'    => true,
				self::CAP => true,
			)
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP );
		}
	}
}
