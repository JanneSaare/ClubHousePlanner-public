<?php
defined( 'ABSPATH' ) || exit;

class CHP_Activator {

	const DB_VERSION = '6';
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
		$items    = CHP_Items::table();
		$blackouts = CHP_Blackouts::table();
		$bookings = CHP_Bookings::table();
		$log    = CHP_Bookings::log_table();

		$sql = "CREATE TABLE $items (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			description text NULL,
			image_id bigint(20) unsigned NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			quantity smallint(5) unsigned NOT NULL DEFAULT 1,
			booking_mode varchar(10) NOT NULL DEFAULT 'slots',
			slots text NULL,
			fee_unit varchar(10) NOT NULL DEFAULT 'booking',
			fee_weekday int(10) unsigned NOT NULL DEFAULT 0,
			fee_weekend int(10) unsigned NULL,
			weekend_days varchar(20) NOT NULL DEFAULT '6,7',
			purpose_mode varchar(10) NOT NULL DEFAULT 'none',
			purposes text NULL,
			min_duration int(10) unsigned NULL,
			max_duration int(10) unsigned NULL,
			min_notice int(10) unsigned NULL,
			key_box_code varchar(100) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset;
		CREATE TABLE $blackouts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_id bigint(20) unsigned NULL,
			unit varchar(3) NULL,
			start_at datetime NOT NULL,
			end_at datetime NOT NULL,
			yearly tinyint(1) NOT NULL DEFAULT 0,
			reason varchar(190) NOT NULL DEFAULT '',
			public_text varchar(190) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY item_period (item_id,start_at,end_at)
		) $charset;
		CREATE TABLE $bookings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			unit varchar(3) NOT NULL DEFAULT 'A',
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
			KEY period (start_at,end_at),
			KEY item_period (item_id,start_at,end_at)
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

		// Create the club house item and attach existing bookings to it (idempotent).
		CHP_Items::install_default();

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
