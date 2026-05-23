<?php
/**
 * Admin glue: asset enqueueing for the rule edit screen.
 *
 * Stage 3 will extend this class with the test override warning banner and
 * the per rule live status panel. For Stage 1 it only loads the admin CSS
 * and JS where they are needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Early_Bird_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin styles and the repeatable rows / toggle script, but only
	 * on the rule edit and add new screens. Keeps the footprint off every
	 * other admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || KDNA_EARLY_BIRD_CPT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'kdna-early-bird-admin',
			KDNA_EARLY_BIRD_URL . 'assets/css/kdna-early-bird-admin.css',
			array(),
			KDNA_EARLY_BIRD_VERSION
		);

		wp_enqueue_script(
			'kdna-early-bird-admin',
			KDNA_EARLY_BIRD_URL . 'assets/js/kdna-early-bird-admin.js',
			array( 'jquery' ),
			KDNA_EARLY_BIRD_VERSION,
			true
		);
	}
}
