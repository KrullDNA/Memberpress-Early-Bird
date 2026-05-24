<?php
/**
 * Rule custom post type, meta boxes and save handling.
 *
 * This file is Stage 1 only: the data model and admin UI. The price engine
 * is not wired in here and no MemberPress prices are touched by this class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Early_Bird_Rules {

	const META_ACTIVE          = '_kdna_early_bird_active';
	const META_START_DATE      = '_kdna_early_bird_start_date';
	const META_TIME_LIMIT_TYPE = '_kdna_early_bird_time_limit_type';
	const META_END_DATE        = '_kdna_early_bird_end_date';
	const META_DAYS_FROM_START = '_kdna_early_bird_days_from_start';
	const META_ROWS            = '_kdna_early_bird_rows';
	const NONCE_ACTION         = 'kdna_early_bird_save_rule';
	const NONCE_NAME           = 'kdna_early_bird_rule_nonce';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . KDNA_EARLY_BIRD_CPT, array( $this, 'save_rule' ), 10, 2 );

		// Hide some columns we do not need on the rule list table.
		add_filter( 'manage_' . KDNA_EARLY_BIRD_CPT . '_posts_columns', array( $this, 'filter_list_columns' ) );
		add_action( 'manage_' . KDNA_EARLY_BIRD_CPT . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
	}

	/**
	 * Register the rule CPT as a submenu of the MemberPress admin menu.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Early Bird Pricing', 'kdna-early-bird' ),
			'singular_name'      => __( 'Early Bird Rule', 'kdna-early-bird' ),
			'menu_name'          => __( 'Early Bird Pricing', 'kdna-early-bird' ),
			'add_new'            => __( 'Add New', 'kdna-early-bird' ),
			'add_new_item'       => __( 'Add New Rule', 'kdna-early-bird' ),
			'edit_item'          => __( 'Edit Rule', 'kdna-early-bird' ),
			'new_item'           => __( 'New Rule', 'kdna-early-bird' ),
			'view_item'          => __( 'View Rule', 'kdna-early-bird' ),
			'search_items'       => __( 'Search Rules', 'kdna-early-bird' ),
			'not_found'          => __( 'No rules found.', 'kdna-early-bird' ),
			'not_found_in_trash' => __( 'No rules found in Trash.', 'kdna-early-bird' ),
			'all_items'          => __( 'Early Bird Pricing', 'kdna-early-bird' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'memberpress',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'delete_with_user'    => false,
		);

		register_post_type( KDNA_EARLY_BIRD_CPT, $args );
	}

	/**
	 * Register the meta boxes shown on the rule edit screen.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'kdna-early-bird-rule-settings',
			__( 'Rule Settings', 'kdna-early-bird' ),
			array( $this, 'render_settings_meta_box' ),
			KDNA_EARLY_BIRD_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'kdna-early-bird-rule-memberships',
			__( 'Memberships and Pricing', 'kdna-early-bird' ),
			array( $this, 'render_memberships_meta_box' ),
			KDNA_EARLY_BIRD_CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Render the rule settings meta box.
	 */
	public function render_settings_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$active          = (int) get_post_meta( $post->ID, self::META_ACTIVE, true );
		$start_date      = (string) get_post_meta( $post->ID, self::META_START_DATE, true );
		$time_limit_type = (string) get_post_meta( $post->ID, self::META_TIME_LIMIT_TYPE, true );
		$end_date        = (string) get_post_meta( $post->ID, self::META_END_DATE, true );
		$days_from_start = get_post_meta( $post->ID, self::META_DAYS_FROM_START, true );

		if ( ! in_array( $time_limit_type, array( 'none', 'date', 'days' ), true ) ) {
			$time_limit_type = 'none';
		}
		?>
		<table class="form-table kdna-eb-form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="kdna-eb-active"><?php esc_html_e( 'Active', 'kdna-early-bird' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="kdna-eb-active" name="kdna_early_bird_active" value="1" <?php checked( 1, $active ); ?> />
							<?php esc_html_e( 'This rule is active.', 'kdna-early-bird' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Only active rules can override prices. An offer is live only when the rule is active, the start date has passed, the time limit has not passed, and the purchase cap has not been reached.', 'kdna-early-bird' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="kdna-eb-start-date"><?php esc_html_e( 'Start date', 'kdna-early-bird' ); ?></label>
					</th>
					<td>
						<input type="date" id="kdna-eb-start-date" name="kdna_early_bird_start_date" value="<?php echo esc_attr( $start_date ); ?>" class="kdna-eb-input" />
						<p class="description"><?php esc_html_e( 'If left empty, the start date defaults to the day the rule is first switched on.', 'kdna-early-bird' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Time limit', 'kdna-early-bird' ); ?></th>
					<td>
						<fieldset class="kdna-eb-fieldset">
							<legend class="screen-reader-text"><?php esc_html_e( 'Time limit', 'kdna-early-bird' ); ?></legend>
							<p>
								<label>
									<input type="radio" name="kdna_early_bird_time_limit_type" value="none" <?php checked( 'none', $time_limit_type ); ?> class="kdna-eb-time-limit-toggle" />
									<?php esc_html_e( 'No time limit, the offer ends only when the cap is reached.', 'kdna-early-bird' ); ?>
								</label>
							</p>
							<p>
								<label>
									<input type="radio" name="kdna_early_bird_time_limit_type" value="date" <?php checked( 'date', $time_limit_type ); ?> class="kdna-eb-time-limit-toggle" />
									<?php esc_html_e( 'End on a fixed date', 'kdna-early-bird' ); ?>
								</label>
							</p>
							<p class="kdna-eb-time-limit-fields kdna-eb-time-limit-date">
								<label for="kdna-eb-end-date" class="screen-reader-text"><?php esc_html_e( 'End date', 'kdna-early-bird' ); ?></label>
								<input type="date" id="kdna-eb-end-date" name="kdna_early_bird_end_date" value="<?php echo esc_attr( $end_date ); ?>" class="kdna-eb-input" />
							</p>
							<p>
								<label>
									<input type="radio" name="kdna_early_bird_time_limit_type" value="days" <?php checked( 'days', $time_limit_type ); ?> class="kdna-eb-time-limit-toggle" />
									<?php esc_html_e( 'End a number of days after the start date', 'kdna-early-bird' ); ?>
								</label>
							</p>
							<p class="kdna-eb-time-limit-fields kdna-eb-time-limit-days">
								<label for="kdna-eb-days-from-start" class="screen-reader-text"><?php esc_html_e( 'Days from start', 'kdna-early-bird' ); ?></label>
								<input type="number" id="kdna-eb-days-from-start" name="kdna_early_bird_days_from_start" value="<?php echo esc_attr( '' === $days_from_start ? '' : (string) (int) $days_from_start ); ?>" min="1" step="1" class="small-text" />
								<span><?php esc_html_e( 'days', 'kdna-early-bird' ); ?></span>
							</p>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Whichever of the time limit or purchase cap is reached first ends the offer.', 'kdna-early-bird' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the memberships and pricing meta box, with repeatable rows.
	 */
	public function render_memberships_meta_box( $post ) {
		$rows        = $this->get_rule_rows( $post->ID );
		$memberships = $this->get_memberpress_memberships();
		?>
		<p class="description">
			<?php esc_html_e( 'Add one row per membership covered by this rule. Each membership has its own early bird price, purchase cap and test override count. Counts and caps are tracked independently per membership.', 'kdna-early-bird' ); ?>
		</p>

		<?php if ( empty( $memberships ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'No MemberPress memberships were found. Create at least one membership in MemberPress before adding rows here.', 'kdna-early-bird' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="kdna-eb-rows" data-next-index="<?php echo esc_attr( (string) count( $rows ) ); ?>">
			<?php
			if ( empty( $rows ) ) {
				$this->render_row( 0, array(), $memberships );
			} else {
				$i = 0;
				foreach ( $rows as $row ) {
					$this->render_row( $i, $row, $memberships );
					$i++;
				}
			}
			?>
		</div>

		<p>
			<button type="button" class="button kdna-eb-add-row">
				<?php esc_html_e( 'Add membership row', 'kdna-early-bird' ); ?>
			</button>
		</p>

		<script type="text/html" id="kdna-eb-row-template">
			<?php $this->render_row( '__INDEX__', array(), $memberships ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single membership row. $index may be an int or the literal
	 * string __INDEX__ for the JS template.
	 */
	private function render_row( $index, $row, $memberships ) {
		$membership_id       = isset( $row['membership_id'] ) ? (int) $row['membership_id'] : 0;
		$early_bird_price    = isset( $row['early_bird_price'] ) ? (string) $row['early_bird_price'] : '';
		$purchase_cap        = isset( $row['purchase_cap'] ) && '' !== $row['purchase_cap'] ? (string) (int) $row['purchase_cap'] : '';
		$test_override_count = isset( $row['test_override_count'] ) && '' !== $row['test_override_count'] ? (string) (int) $row['test_override_count'] : '';

		$name_base = 'kdna_early_bird_rows[' . $index . ']';
		?>
		<div class="kdna-eb-row" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="kdna-eb-row-grid">
				<div class="kdna-eb-field">
					<label><?php esc_html_e( 'Membership', 'kdna-early-bird' ); ?></label>
					<select name="<?php echo esc_attr( $name_base . '[membership_id]' ); ?>" class="kdna-eb-input">
						<option value="0"><?php esc_html_e( 'Select a membership', 'kdna-early-bird' ); ?></option>
						<?php foreach ( $memberships as $membership ) : ?>
							<option value="<?php echo esc_attr( (string) $membership->ID ); ?>" <?php selected( $membership_id, $membership->ID ); ?>>
								<?php echo esc_html( $membership->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="kdna-eb-field">
					<label><?php esc_html_e( 'Early bird price', 'kdna-early-bird' ); ?></label>
					<input type="text" inputmode="decimal" name="<?php echo esc_attr( $name_base . '[early_bird_price]' ); ?>" value="<?php echo esc_attr( $early_bird_price ); ?>" class="kdna-eb-input" placeholder="0.00" />
				</div>
				<div class="kdna-eb-field">
					<label><?php esc_html_e( 'Purchase cap', 'kdna-early-bird' ); ?></label>
					<input type="number" min="0" step="1" name="<?php echo esc_attr( $name_base . '[purchase_cap]' ); ?>" value="<?php echo esc_attr( $purchase_cap ); ?>" class="kdna-eb-input" placeholder="<?php esc_attr_e( 'Optional', 'kdna-early-bird' ); ?>" />
				</div>
				<div class="kdna-eb-field">
					<label><?php esc_html_e( 'Test override count', 'kdna-early-bird' ); ?></label>
					<input type="number" min="0" step="1" name="<?php echo esc_attr( $name_base . '[test_override_count]' ); ?>" value="<?php echo esc_attr( $test_override_count ); ?>" class="kdna-eb-input kdna-eb-test-override" placeholder="<?php esc_attr_e( 'Empty counts real sales', 'kdna-early-bird' ); ?>" />
				</div>
				<div class="kdna-eb-field kdna-eb-row-actions">
					<button type="button" class="button-link-delete kdna-eb-remove-row">
						<?php esc_html_e( 'Remove row', 'kdna-early-bird' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save handler. Validates the nonce, capability, and the usual edge cases
	 * (autosave, revision, bulk edit), then sanitises and stores all fields.
	 */
	public function save_rule( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( KDNA_EARLY_BIRD_CPT !== $post->post_type ) {
			return;
		}

		// Active toggle.
		$active = ! empty( $_POST['kdna_early_bird_active'] ) ? 1 : 0;

		// Start date.
		$start_date_raw = isset( $_POST['kdna_early_bird_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_early_bird_start_date'] ) ) : '';
		$start_date     = $this->sanitise_date( $start_date_raw );

		// Default start date to today the first time the rule is switched on.
		if ( 1 === $active && '' === $start_date ) {
			$existing_start = (string) get_post_meta( $post_id, self::META_START_DATE, true );
			if ( '' === $existing_start ) {
				$start_date = current_time( 'Y-m-d' );
			} else {
				$start_date = $this->sanitise_date( $existing_start );
			}
		}

		// Time limit type.
		$time_limit_type_raw = isset( $_POST['kdna_early_bird_time_limit_type'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_early_bird_time_limit_type'] ) ) : 'none';
		$time_limit_type     = in_array( $time_limit_type_raw, array( 'none', 'date', 'days' ), true ) ? $time_limit_type_raw : 'none';

		// End date and days from start are only kept when their toggle is the active one.
		$end_date_raw   = isset( $_POST['kdna_early_bird_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_early_bird_end_date'] ) ) : '';
		$days_from_raw  = isset( $_POST['kdna_early_bird_days_from_start'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_early_bird_days_from_start'] ) ) : '';
		$end_date       = 'date' === $time_limit_type ? $this->sanitise_date( $end_date_raw ) : '';
		$days_from_start = 'days' === $time_limit_type ? max( 0, (int) $days_from_raw ) : '';

		update_post_meta( $post_id, self::META_ACTIVE, $active );
		update_post_meta( $post_id, self::META_START_DATE, $start_date );
		update_post_meta( $post_id, self::META_TIME_LIMIT_TYPE, $time_limit_type );
		update_post_meta( $post_id, self::META_END_DATE, $end_date );

		if ( '' === $days_from_start ) {
			delete_post_meta( $post_id, self::META_DAYS_FROM_START );
		} else {
			update_post_meta( $post_id, self::META_DAYS_FROM_START, (int) $days_from_start );
		}

		// Membership rows.
		$rows_raw = isset( $_POST['kdna_early_bird_rows'] ) && is_array( $_POST['kdna_early_bird_rows'] )
			? wp_unslash( $_POST['kdna_early_bird_rows'] )
			: array();

		$rows_clean = $this->sanitise_rows( $rows_raw );

		if ( empty( $rows_clean ) ) {
			delete_post_meta( $post_id, self::META_ROWS );
		} else {
			update_post_meta( $post_id, self::META_ROWS, $rows_clean );
		}
	}

	/**
	 * Sanitise the repeatable membership rows. Drops rows with no membership
	 * selected. Preserves blanks for the optional fields so the engine can
	 * tell "not set" apart from "set to zero".
	 */
	private function sanitise_rows( $rows_raw ) {
		$clean = array();
		$seen  = array();

		if ( ! is_array( $rows_raw ) ) {
			return $clean;
		}

		foreach ( $rows_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$membership_id = isset( $row['membership_id'] ) ? (int) $row['membership_id'] : 0;
			if ( $membership_id <= 0 ) {
				continue;
			}

			// Deduplicate by membership within this rule, first one wins.
			if ( isset( $seen[ $membership_id ] ) ) {
				continue;
			}
			$seen[ $membership_id ] = true;

			$price_raw = isset( $row['early_bird_price'] ) ? (string) $row['early_bird_price'] : '';
			$price     = $this->sanitise_price( $price_raw );

			$cap_raw           = isset( $row['purchase_cap'] ) ? trim( (string) $row['purchase_cap'] ) : '';
			$purchase_cap      = '' === $cap_raw ? '' : max( 0, (int) $cap_raw );

			$override_raw        = isset( $row['test_override_count'] ) ? trim( (string) $row['test_override_count'] ) : '';
			$test_override_count = '' === $override_raw ? '' : max( 0, (int) $override_raw );

			$clean[] = array(
				'membership_id'       => $membership_id,
				'early_bird_price'    => $price,
				'purchase_cap'        => $purchase_cap,
				'test_override_count' => $test_override_count,
			);
		}

		return $clean;
	}

	/**
	 * Sanitise a date string to YYYY-MM-DD or return empty.
	 */
	private function sanitise_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}
		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Sanitise a price string. Keeps digits and a single decimal separator,
	 * stores as a plain string. Empty input returns an empty string so the
	 * engine can leave the membership alone.
	 */
	private function sanitise_price( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Normalise comma decimal to dot, strip everything else.
		$value = str_replace( ',', '.', $value );
		$value = preg_replace( '/[^0-9\.]/', '', $value );
		if ( '' === $value || '.' === $value ) {
			return '';
		}
		// Keep only the first dot, strip any subsequent ones.
		$parts = explode( '.', $value );
		if ( count( $parts ) > 2 ) {
			$value = array_shift( $parts ) . '.' . implode( '', $parts );
		}
		// Cast through float and back to string to normalise.
		$as_float = (float) $value;
		if ( $as_float < 0 ) {
			$as_float = 0;
		}
		return rtrim( rtrim( number_format( $as_float, 2, '.', '' ), '0' ), '.' ) === ''
			? '0'
			: number_format( $as_float, 2, '.', '' );
	}

	/**
	 * Read and normalise the stored rows for a rule.
	 */
	public function get_rule_rows( $rule_id ) {
		$rows = get_post_meta( $rule_id, self::META_ROWS, true );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_values( $rows );
	}

	/**
	 * Fetch published MemberPress memberships for the select dropdown.
	 * Cached per request via a static so reopening the meta box does not
	 * re-query.
	 */
	private function get_memberpress_memberships() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$cache = get_posts( array(
			'post_type'        => 'memberpressproduct',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		) );

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		return $cache;
	}

	/**
	 * Trim the rule list table columns and add a small Active column.
	 */
	public function filter_list_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['kdna_eb_active'] = __( 'Active', 'kdna-early-bird' );
			}
		}
		return $new;
	}

	public function render_list_column( $column, $post_id ) {
		if ( 'kdna_eb_active' !== $column ) {
			return;
		}
		$active = (int) get_post_meta( $post_id, self::META_ACTIVE, true );
		echo $active
			? '<span class="kdna-eb-active-yes">' . esc_html__( 'Yes', 'kdna-early-bird' ) . '</span>'
			: '<span class="kdna-eb-active-no">' . esc_html__( 'No', 'kdna-early-bird' ) . '</span>';
	}
}
