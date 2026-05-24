<?php
/**
 * Admin glue.
 *
 * Stage 1 scope: asset enqueueing for the rule edit screen.
 * Stage 3 scope: test override warning banner across wp-admin and the live
 * status panel on the rule edit screen.
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
		add_action( 'admin_notices', array( $this, 'render_test_override_banner' ) );
		add_action( 'add_meta_boxes_' . KDNA_EARLY_BIRD_CPT, array( $this, 'register_status_meta_box' ) );
	}

	/**
	 * Enqueue admin styles and the repeatable rows / toggle script on the
	 * rule edit screens. The warning banner styles live in the same file
	 * but the banner can appear on any admin page, so the small banner
	 * specific CSS is inlined via admin_print_styles to keep the rest off
	 * every admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Banner styles, lightweight, loaded everywhere in admin so the
		// banner renders correctly wherever it appears.
		wp_register_style( 'kdna-early-bird-admin-banner', false, array(), KDNA_EARLY_BIRD_VERSION );
		wp_enqueue_style( 'kdna-early-bird-admin-banner' );
		wp_add_inline_style( 'kdna-early-bird-admin-banner', $this->banner_inline_css() );

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

	/**
	 * Show a bright warning across wp-admin whenever any rule has a test
	 * override count filled in. Names each rule and membership, with a
	 * link to edit the rule. The banner is intentionally noisy because
	 * the whole point is that an override cannot be left on by accident.
	 */
	public function render_test_override_banner() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$overrides = KDNA_Early_Bird_Engine::instance()->get_test_overrides();
		if ( empty( $overrides ) ) {
			return;
		}

		$count = count( $overrides );
		?>
		<div class="notice notice-warning kdna-eb-override-banner">
			<p class="kdna-eb-override-banner-title">
				<strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d is the number of membership rows with a test override. */
							_n(
								'Early Bird test override is active on %d membership row.',
								'Early Bird test override is active on %d membership rows.',
								$count,
								'kdna-early-bird'
							),
							$count
						)
					);
					?>
				</strong>
			</p>
			<p>
				<?php esc_html_e( 'The prices below are being controlled by a typed in test count rather than real completed sales. Clear these values before this site goes live, otherwise the early bird offer will switch on or off based on the test number.', 'kdna-early-bird' ); ?>
			</p>
			<ul class="kdna-eb-override-banner-list">
				<?php
				foreach ( $overrides as $entry ) :
					$edit_url   = get_edit_post_link( (int) $entry['rule_id'] );
					$rule_label = (string) $entry['rule_title'];
					$mem_label  = (string) $entry['membership_title'];
					?>
					<li>
						<strong><?php echo esc_html( $rule_label ); ?></strong>
						<?php if ( empty( $entry['rule_active'] ) ) : ?>
							<span class="kdna-eb-override-banner-pill kdna-eb-override-banner-pill-inactive">
								<?php esc_html_e( 'rule not active', 'kdna-early-bird' ); ?>
							</span>
						<?php endif; ?>
						<span class="kdna-eb-override-banner-sep">&middot;</span>
						<?php echo esc_html( $mem_label ); ?>
						<span class="kdna-eb-override-banner-sep">&middot;</span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d is the typed in test override count. */
								__( 'override count = %d', 'kdna-early-bird' ),
								(int) $entry['override_count']
							)
						);
						?>
						<?php if ( $edit_url ) : ?>
							<span class="kdna-eb-override-banner-sep">&middot;</span>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit rule', 'kdna-early-bird' ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Register the live status meta box on the rule edit screen.
	 */
	public function register_status_meta_box() {
		add_meta_box(
			'kdna-early-bird-rule-status',
			__( 'Live offer status', 'kdna-early-bird' ),
			array( $this, 'render_status_meta_box' ),
			KDNA_EARLY_BIRD_CPT,
			'normal',
			'low'
		);
	}

	/**
	 * Render the status panel for a rule. Shows, per membership in the
	 * rule, the real completed purchase count, the cap, whether the offer
	 * is currently live, the early bird price, and the price currently
	 * being served to buyers.
	 */
	public function render_status_meta_box( $post ) {
		$rules  = KDNA_Early_Bird_Rules::instance();
		$engine = KDNA_Early_Bird_Engine::instance();
		$rows   = $rules->get_rule_rows( $post->ID );
		$active = (int) get_post_meta( $post->ID, KDNA_Early_Bird_Rules::META_ACTIVE, true );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Add at least one membership row and save the rule to see live status here.', 'kdna-early-bird' ) . '</p>';
			return;
		}

		if ( 1 !== $active ) {
			echo '<p class="kdna-eb-status-inactive">' . esc_html__( 'This rule is not active. The status below shows what would happen if it were switched on, real prices are not being changed.', 'kdna-early-bird' ) . '</p>';
		}

		echo '<div class="kdna-eb-status-list">';

		foreach ( $rows as $row ) {
			$mid = (int) $row['membership_id'];
			if ( $mid <= 0 ) {
				continue;
			}

			$membership_title = get_the_title( $mid );
			if ( '' === $membership_title ) {
				/* translators: %d is the membership post id. */
				$membership_title = sprintf( __( 'Membership #%d (deleted)', 'kdna-early-bird' ), $mid );
			}

			$row_price    = isset( $row['early_bird_price'] ) ? (string) $row['early_bird_price'] : '';
			$row_cap_raw  = isset( $row['purchase_cap'] ) ? $row['purchase_cap'] : '';
			$row_override = isset( $row['test_override_count'] ) ? $row['test_override_count'] : '';

			$state             = $engine->get_offer_state( $mid );
			$real_count        = $engine->get_purchase_count( $mid );
			$stored_full_price = $engine->get_stored_full_price( $mid );
			$served_price      = $engine->get_served_price( $mid );

			$is_this_rule = is_array( $state ) && (int) $state['rule_id'] === (int) $post->ID;
			$live         = $is_this_rule && ! empty( $state['live'] );

			$status_class = $live ? 'kdna-eb-status-live' : 'kdna-eb-status-not-live';
			$status_label = $live
				? __( 'Live, early bird active', 'kdna-early-bird' )
				: __( 'Not live, full price showing', 'kdna-early-bird' );

			$reason_label = $this->humanise_reason( is_array( $state ) ? (string) $state['reason'] : '', $active, $is_this_rule, $state, $post->ID );

			$cap_display = ( '' === $row_cap_raw )
				? __( 'No cap', 'kdna-early-bird' )
				: sprintf( '%d', (int) $row_cap_raw );

			$override_display = ( '' === $row_override )
				? __( 'Empty, counting real sales', 'kdna-early-bird' )
				: sprintf(
					/* translators: %d is the typed in test override count. */
					__( '%d (in use, banner showing)', 'kdna-early-bird' ),
					(int) $row_override
				);
			?>
			<div class="kdna-eb-status-card">
				<div class="kdna-eb-status-card-header">
					<span class="kdna-eb-status-card-title"><?php echo esc_html( $membership_title ); ?></span>
					<span class="kdna-eb-status-pill <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</div>
				<?php if ( '' !== $reason_label ) : ?>
					<p class="kdna-eb-status-reason"><?php echo esc_html( $reason_label ); ?></p>
				<?php endif; ?>
				<table class="kdna-eb-status-table widefat striped">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Real completed purchases', 'kdna-early-bird' ); ?></th>
							<td><?php echo esc_html( (string) (int) $real_count ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Test override count', 'kdna-early-bird' ); ?></th>
							<td><?php echo esc_html( $override_display ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Purchase cap', 'kdna-early-bird' ); ?></th>
							<td><?php echo esc_html( $cap_display ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Early bird price', 'kdna-early-bird' ); ?></th>
							<td><?php echo esc_html( $this->format_price( $row_price ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Stored full price', 'kdna-early-bird' ); ?></th>
							<td><?php echo esc_html( $this->format_price( $stored_full_price ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Price currently being served', 'kdna-early-bird' ); ?></th>
							<td>
								<strong><?php echo esc_html( $this->format_price( $served_price ) ); ?></strong>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<?php
		}

		echo '</div>';

		echo '<p class="description kdna-eb-status-note">';
		esc_html_e( 'Status is read live from the cached purchase count, which refreshes whenever a MemberPress transaction status changes. Save the rule to apply any edits to the data shown here.', 'kdna-early-bird' );
		echo '</p>';
	}

	/**
	 * Turn the engine's machine reason into a one line plain English
	 * description for the status panel.
	 */
	private function humanise_reason( $reason, $rule_active, $is_this_rule, $state, $current_rule_id ) {
		if ( 1 !== (int) $rule_active ) {
			return __( 'This rule is not active, so no offer is live for any of its memberships.', 'kdna-early-bird' );
		}

		if ( is_array( $state ) && ! $is_this_rule ) {
			$other_rule_title = isset( $state['rule_title'] ) ? (string) $state['rule_title'] : '';
			return sprintf(
				/* translators: %s is the title of the other active rule. */
				__( 'Ignored here. This membership is covered by another active rule that runs first: %s.', 'kdna-early-bird' ),
				$other_rule_title
			);
		}

		switch ( $reason ) {
			case 'live':
				return __( 'Offer is live. The early bird price is being served.', 'kdna-early-bird' );
			case 'not_started':
				return __( 'Offer has not started yet, the start date is in the future.', 'kdna-early-bird' );
			case 'time_limit_passed':
				return __( 'Offer has ended, the time limit has passed.', 'kdna-early-bird' );
			case 'cap_reached':
				return __( 'Offer has ended, the purchase cap has been reached.', 'kdna-early-bird' );
			case 'no_price':
				return __( 'No early bird price is set on this row, MemberPress will use its full price.', 'kdna-early-bird' );
		}

		return '';
	}

	/**
	 * Format a price string using MemberPress's own formatter when
	 * available, otherwise a plain two decimal fallback.
	 */
	private function format_price( $value ) {
		if ( '' === $value || null === $value ) {
			return __( 'not set', 'kdna-early-bird' );
		}
		if ( class_exists( 'MeprUtils' ) && method_exists( 'MeprUtils', 'format_currency' ) ) {
			return MeprUtils::format_currency( (float) $value );
		}
		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * Inline CSS for the warning banner. Kept tiny so we can ship it on
	 * every admin page without enqueueing an extra file.
	 */
	private function banner_inline_css() {
		return '
.kdna-eb-override-banner { border-left-width: 6px; border-left-color: #dba617; padding: 14px 16px; }
.kdna-eb-override-banner .kdna-eb-override-banner-title { font-size: 15px; margin: 0 0 6px; }
.kdna-eb-override-banner-list { margin: 8px 0 0 0; padding: 0; list-style: none; }
.kdna-eb-override-banner-list li { padding: 4px 0; border-top: 1px solid rgba(0,0,0,0.06); }
.kdna-eb-override-banner-list li:first-child { border-top: 0; }
.kdna-eb-override-banner-pill { display: inline-block; background: #f0b849; color: #1d2327; font-size: 11px; padding: 1px 8px; border-radius: 10px; margin: 0 4px; vertical-align: middle; }
.kdna-eb-override-banner-pill-inactive { background: #c3c4c7; color: #1d2327; }
.kdna-eb-override-banner-sep { color: #8c8f94; padding: 0 4px; }
';
	}
}
