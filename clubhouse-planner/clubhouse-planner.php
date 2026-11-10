<?php
/**
 * Plugin Name:       ClubHouse Planner
 * Description:       Booking and scheduling for a club house. Public calendar, booking requests and housekeeper approval workflow.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            Janne Saare
 * License:           GPL-2.0-or-later
 * Text Domain:       clubhouse-planner
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CHP_VERSION', '1.0.0' );
define( 'CHP_FILE', __FILE__ );
define( 'CHP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHP_URL', plugin_dir_url( __FILE__ ) );

require_once CHP_DIR . 'includes/class-chp-settings.php';
require_once CHP_DIR . 'includes/class-chp-items.php';
require_once CHP_DIR . 'includes/class-chp-blackouts.php';
require_once CHP_DIR . 'includes/class-chp-pricing.php';
require_once CHP_DIR . 'includes/class-chp-bookings.php';
require_once CHP_DIR . 'includes/class-chp-mailer.php';
require_once CHP_DIR . 'includes/class-chp-reminders.php';
require_once CHP_DIR . 'includes/class-chp-activator.php';
require_once CHP_DIR . 'includes/class-chp-public.php';
require_once CHP_DIR . 'includes/class-chp-admin.php';

register_activation_hook( __FILE__, array( 'CHP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CHP_Activator', 'deactivate' ) );

// WordPress 6.7+ warns if translations are loaded before init.
add_action(
	'init',
	function (): void {
		load_plugin_textdomain( 'clubhouse-planner', false, dirname( plugin_basename( CHP_FILE ) ) . '/languages' );
	}
);


add_action(
	'plugins_loaded',
	function (): void {
		if ( get_option( 'chp_db_version' ) !== CHP_Activator::DB_VERSION ) {
			CHP_Activator::create_tables();
		}

		CHP_Reminders::init();

		new CHP_Public();
		if ( is_admin() ) {
			new CHP_Admin();
		}
	}
);
