<?php
/**
 * Plugin Name: KDNA Early Bird Pricing for MemberPress
 * Description: Run limited early bird offers on MemberPress memberships without coupons. The plugin quietly hands MemberPress the early bird price while an offer is live, then steps aside.
 * Version:     1.0.0
 * Author:      KDNA
 * Text Domain: kdna-early-bird
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDNA_EARLY_BIRD_VERSION', '1.0.0' );
define( 'KDNA_EARLY_BIRD_FILE', __FILE__ );
define( 'KDNA_EARLY_BIRD_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_EARLY_BIRD_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_EARLY_BIRD_BASENAME', plugin_basename( __FILE__ ) );
define( 'KDNA_EARLY_BIRD_CPT', 'kdna_eb_rule' );

require_once KDNA_EARLY_BIRD_PATH . 'includes/class-kdna-eb-rules.php';
require_once KDNA_EARLY_BIRD_PATH . 'includes/class-kdna-eb-engine.php';
require_once KDNA_EARLY_BIRD_PATH . 'includes/class-kdna-eb-admin.php';

/**
 * Detect whether MemberPress is active by looking for one of its core classes
 * or its plugin constant. Avoids requiring is_plugin_active() on the front end.
 */
function kdna_early_bird_is_memberpress_active() {
	return class_exists( 'MeprAppCtrl' )
		|| class_exists( 'MeprProduct' )
		|| defined( 'MEPR_PLUGIN_NAME' );
}

/**
 * Bootstrap the plugin once all plugins are loaded so we can reliably detect
 * MemberPress. If MemberPress is missing we show an admin notice and step
 * aside, in line with the fail safe direction.
 */
function kdna_early_bird_bootstrap() {
	load_plugin_textdomain( 'kdna-early-bird', false, dirname( KDNA_EARLY_BIRD_BASENAME ) . '/languages' );

	if ( ! kdna_early_bird_is_memberpress_active() ) {
		add_action( 'admin_notices', 'kdna_early_bird_memberpress_missing_notice' );
		return;
	}

	KDNA_Early_Bird_Rules::instance();
	KDNA_Early_Bird_Engine::instance();
	KDNA_Early_Bird_Admin::instance();
}
add_action( 'plugins_loaded', 'kdna_early_bird_bootstrap' );

/**
 * Rebuild the membership index on activation so the price engine has a
 * usable map even before the first rule save.
 */
function kdna_early_bird_activate() {
	if ( class_exists( 'KDNA_Early_Bird_Engine' ) ) {
		KDNA_Early_Bird_Engine::instance()->rebuild_index();
	}
}
register_activation_hook( __FILE__, 'kdna_early_bird_activate' );

/**
 * Tidy up the autoloaded option on deactivation. Transients expire on
 * their own. We do not delete rule posts, those remain in the database
 * in case the plugin is reactivated later.
 */
function kdna_early_bird_deactivate() {
	delete_option( KDNA_Early_Bird_Engine::OPTION_INDEX );
}
register_deactivation_hook( __FILE__, 'kdna_early_bird_deactivate' );

/**
 * Admin notice shown when MemberPress is not active.
 */
function kdna_early_bird_memberpress_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'KDNA Early Bird Pricing requires MemberPress to be installed and active. The plugin is loaded but will not change any prices until MemberPress is available.', 'kdna-early-bird' );
	echo '</p></div>';
}
