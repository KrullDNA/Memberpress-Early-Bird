<?php
/**
 * Offer state, purchase counting and the seamless price override.
 *
 * This class is the only place where the plugin ever changes a price. It
 * does so by filtering get_post_metadata on the MemberPress price meta
 * key, and only when an offer is provably live. In every other case it
 * returns the incoming value unchanged so MemberPress reads its own full
 * price from the database. Fail safe direction: anything uncertain means
 * step aside.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDNA_Early_Bird_Engine {

	const OPTION_INDEX     = 'kdna_early_bird_membership_index';
	const OPTION_OVERRIDES = 'kdna_early_bird_test_overrides';
	const TRANSIENT_PREFIX = 'kdna_eb_count_';
	const MEPR_PRICE_META  = '_mepr_product_price';
	const MEPR_PRODUCT_CPT = 'memberpressproduct';
	const MEPR_TXN_TABLE   = 'mepr_transactions';

	private static $instance = null;

	/**
	 * Per request cache of offer state, keyed by membership id. Avoids
	 * recomputing for the many MemberPress price reads inside a single
	 * page load.
	 */
	private static $request_cache = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// The seamless price override.
		add_filter( 'get_post_metadata', array( $this, 'filter_price_meta' ), 10, 4 );

		// Safety net for code paths in MemberPress that compute the price
		// without re-reading the meta (cached transaction amounts, the
		// MeprHooks adjusted price pipeline, level/pricing tables). Fail
		// safe direction is preserved: these handlers only ever lower the
		// price, never raise it.
		add_filter( 'mepr-adjusted-price', array( $this, 'filter_mepr_price' ), 10, 3 );
		add_filter( 'mepr-product-actual-price', array( $this, 'filter_mepr_price' ), 10, 3 );
		add_filter( 'mepr-product-price', array( $this, 'filter_mepr_price' ), 10, 3 );
		add_filter( 'mepr-membership-table-price', array( $this, 'filter_mepr_price' ), 10, 3 );

		// Keep the membership index in sync with the rule store.
		add_action( 'save_post_' . KDNA_EARLY_BIRD_CPT, array( $this, 'rebuild_index_on_rule_change' ), 20, 1 );
		add_action( 'trashed_post', array( $this, 'rebuild_index_on_rule_change' ), 20, 1 );
		add_action( 'untrashed_post', array( $this, 'rebuild_index_on_rule_change' ), 20, 1 );
		add_action( 'deleted_post', array( $this, 'rebuild_index_on_rule_delete' ), 20, 2 );

		// Refresh the count whenever a transaction changes status. We hook a
		// few of the most reliable MemberPress events so that the count
		// stays accurate without us ever querying on a normal page load.
		add_action( 'mepr-txn-status-complete', array( $this, 'on_txn_status_change' ), 10, 1 );
		add_action( 'mepr-txn-status-refunded', array( $this, 'on_txn_status_change' ), 10, 1 );
		add_action( 'mepr-txn-status-failed', array( $this, 'on_txn_status_change' ), 10, 1 );
		add_action( 'mepr-txn-status-pending', array( $this, 'on_txn_status_change' ), 10, 1 );
		add_action( 'mepr-event-transaction-completed', array( $this, 'on_event_transaction' ), 10, 1 );
		add_action( 'mepr-event-non-recurring-transaction-completed', array( $this, 'on_event_transaction' ), 10, 1 );
	}

	/**
	 * Returns the cached transient time to live. Filterable so a site can
	 * tune it without editing the plugin.
	 */
	private function get_count_ttl() {
		$ttl = (int) apply_filters( 'kdna_early_bird_count_ttl', 5 * MINUTE_IN_SECONDS );
		if ( $ttl < MINUTE_IN_SECONDS ) {
			$ttl = MINUTE_IN_SECONDS;
		}
		return $ttl;
	}

	/**
	 * The seamless price override. Fires for every post meta read so the
	 * first check is the cheapest possible early exit. When an offer is
	 * live for the membership being read we return the early bird price,
	 * otherwise we return the incoming value untouched.
	 *
	 * @param mixed  $value     The value being returned, or null if no
	 *                          earlier filter has set one.
	 * @param int    $object_id The post id being read.
	 * @param string $meta_key  The meta key being read.
	 * @param bool   $single    Whether a single value is being requested.
	 * @return mixed
	 */
	public function filter_price_meta( $value, $object_id, $meta_key, $single ) {
		if ( self::MEPR_PRICE_META !== $meta_key ) {
			return $value;
		}

		$object_id = (int) $object_id;
		if ( $object_id <= 0 ) {
			return $value;
		}

		$state = $this->get_offer_state( $object_id );
		if ( null === $state || empty( $state['live'] ) ) {
			return $value;
		}

		$price = isset( $state['early_bird_price'] ) ? (string) $state['early_bird_price'] : '';
		if ( '' === $price ) {
			return $value;
		}

		return $single ? $price : array( $price );
	}

	/**
	 * Safety net for MemberPress price filters. Some checkout paths cache
	 * the price on a transaction record or compute it through MeprHooks
	 * without going back through get_post_metadata. Those filters reach
	 * here so the early bird price still wins.
	 *
	 * Arg order varies by MemberPress hook and version, so we look for
	 * the MeprProduct object in any of the trailing args and pull its
	 * ID. Fail safe direction is preserved: this only ever lowers the
	 * price, never raises it.
	 */
	public function filter_mepr_price( $price, $second = null, $third = null ) {
		$product_id = 0;
		foreach ( array( $second, $third ) as $arg ) {
			if ( is_object( $arg ) && isset( $arg->ID ) && (int) $arg->ID > 0 ) {
				$product_id = (int) $arg->ID;
				break;
			}
		}
		if ( $product_id <= 0 ) {
			return $price;
		}

		$state = $this->get_offer_state( $product_id );
		if ( ! is_array( $state ) || empty( $state['live'] ) ) {
			return $price;
		}

		$eb_price = isset( $state['early_bird_price'] ) ? (float) $state['early_bird_price'] : 0;
		if ( $eb_price <= 0 ) {
			return $price;
		}

		$current = (float) $price;
		// Only ever lower the price. If the current value is already at
		// or below the early bird price, leave it alone.
		if ( $eb_price < $current ) {
			return $eb_price;
		}
		return $price;
	}

	/**
	 * Return the full offer state for a membership, or null if the
	 * membership is not covered by any active rule. The returned array
	 * always includes a boolean 'live' key and a 'reason' string so the
	 * status panel in Stage 3 can show why an offer is or is not live.
	 */
	public function get_offer_state( $membership_id ) {
		$membership_id = (int) $membership_id;
		if ( $membership_id <= 0 ) {
			return null;
		}

		if ( array_key_exists( $membership_id, self::$request_cache ) ) {
			return self::$request_cache[ $membership_id ];
		}

		$index = $this->get_index();
		if ( ! isset( $index[ $membership_id ] ) ) {
			self::$request_cache[ $membership_id ] = null;
			return null;
		}

		$entry = $index[ $membership_id ];
		$today = current_time( 'Y-m-d' );

		$entry['membership_id']   = $membership_id;
		$entry['real_count']      = $this->get_purchase_count( $membership_id );
		$entry['effective_count'] = is_int( $entry['test_override_count'] )
			? $entry['test_override_count']
			: $entry['real_count'];
		$entry['using_override']  = is_int( $entry['test_override_count'] );
		$entry['today']           = $today;

		// Fail safe: a row with no early bird price means we do nothing.
		if ( '' === (string) $entry['early_bird_price'] ) {
			$entry['live']   = false;
			$entry['reason'] = 'no_price';
			self::$request_cache[ $membership_id ] = $entry;
			return $entry;
		}

		// Start date check. Empty start date is treated as already started.
		if ( '' !== $entry['start_date'] && $today < $entry['start_date'] ) {
			$entry['live']   = false;
			$entry['reason'] = 'not_started';
			self::$request_cache[ $membership_id ] = $entry;
			return $entry;
		}

		// Time limit check. The end date is inclusive, today is still live.
		if ( '' !== $entry['end_date'] && $today > $entry['end_date'] ) {
			$entry['live']   = false;
			$entry['reason'] = 'time_limit_passed';
			self::$request_cache[ $membership_id ] = $entry;
			return $entry;
		}

		// Cap check, whichever of time or cap comes first ends it.
		if ( is_int( $entry['purchase_cap'] ) && $entry['effective_count'] >= $entry['purchase_cap'] ) {
			$entry['live']   = false;
			$entry['reason'] = 'cap_reached';
			self::$request_cache[ $membership_id ] = $entry;
			return $entry;
		}

		$entry['live']   = true;
		$entry['reason'] = 'live';

		self::$request_cache[ $membership_id ] = $entry;
		return $entry;
	}

	/**
	 * Return the cached completed purchase count for a membership. On a
	 * cache miss this computes the count once and stores it in a short
	 * lived transient as a safety net. Hook driven refreshes are the
	 * primary update path.
	 */
	public function get_purchase_count( $membership_id ) {
		$membership_id = (int) $membership_id;
		if ( $membership_id <= 0 ) {
			return 0;
		}

		$key    = self::TRANSIENT_PREFIX . $membership_id;
		$cached = get_transient( $key );
		if ( false !== $cached && '' !== $cached ) {
			return (int) $cached;
		}

		$count = $this->compute_purchase_count( $membership_id );
		set_transient( $key, $count, $this->get_count_ttl() );
		return $count;
	}

	/**
	 * Direct query against the MemberPress transactions table. Returns 0
	 * if the table is not present, in line with the fail safe direction.
	 */
	public function compute_purchase_count( $membership_id ) {
		global $wpdb;

		$membership_id = (int) $membership_id;
		if ( $membership_id <= 0 ) {
			return 0;
		}

		$table  = $wpdb->prefix . self::MEPR_TXN_TABLE;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return 0;
		}

		// product_id is the MemberPress column for the membership id.
		// status 'complete' is the documented completed status.
		// Table name is interpolated because identifiers cannot be bound.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE product_id = %d AND status = %s",
			$membership_id,
			'complete'
		) );

		return (int) $count;
	}

	/**
	 * Force a recount and refresh the cached value. Called by the
	 * transaction status hooks below.
	 */
	public function refresh_count( $membership_id ) {
		$membership_id = (int) $membership_id;
		if ( $membership_id <= 0 ) {
			return;
		}
		$count = $this->compute_purchase_count( $membership_id );
		set_transient( self::TRANSIENT_PREFIX . $membership_id, $count, $this->get_count_ttl() );
		unset( self::$request_cache[ $membership_id ] );
	}

	/**
	 * Refresh handler bound to mepr-txn-status-* hooks. MemberPress passes
	 * the MeprTransaction object whose product_id is the membership id.
	 */
	public function on_txn_status_change( $txn ) {
		$membership_id = $this->extract_membership_id_from_txn( $txn );
		if ( $membership_id > 0 ) {
			$this->refresh_count( $membership_id );
		}
	}

	/**
	 * Refresh handler bound to mepr-event-* hooks. The event passes a
	 * MeprEvent whose data is the transaction.
	 */
	public function on_event_transaction( $event ) {
		$txn = null;
		if ( is_object( $event ) && method_exists( $event, 'get_data' ) ) {
			$txn = $event->get_data();
		}
		$membership_id = $this->extract_membership_id_from_txn( $txn );
		if ( $membership_id > 0 ) {
			$this->refresh_count( $membership_id );
		}
	}

	private function extract_membership_id_from_txn( $txn ) {
		if ( ! is_object( $txn ) ) {
			return 0;
		}
		if ( isset( $txn->product_id ) ) {
			return (int) $txn->product_id;
		}
		if ( method_exists( $txn, 'product' ) ) {
			$product = $txn->product();
			if ( is_object( $product ) && isset( $product->ID ) ) {
				return (int) $product->ID;
			}
		}
		return 0;
	}

	/**
	 * Return the autoloaded membership index, rebuilding it lazily if it
	 * has not been built yet.
	 */
	public function get_index() {
		$index = get_option( self::OPTION_INDEX, null );
		if ( ! is_array( $index ) ) {
			$index = $this->rebuild_index();
		}
		return $index;
	}

	/**
	 * Rebuild handler triggered by changes to rule posts. The Rules class
	 * saves at priority 10, this fires at priority 20 so the meta is on
	 * disk by the time we read it.
	 */
	public function rebuild_index_on_rule_change( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( get_post_type( $post_id ) !== KDNA_EARLY_BIRD_CPT ) {
			return;
		}
		$this->rebuild_index();
		self::$request_cache = array();
	}

	/**
	 * deleted_post fires after the row is gone, so the post object passed
	 * to the hook is the only reliable way to check the type here.
	 */
	public function rebuild_index_on_rule_delete( $post_id, $post ) {
		if ( ! is_object( $post ) || KDNA_EARLY_BIRD_CPT !== $post->post_type ) {
			return;
		}
		$this->rebuild_index();
		self::$request_cache = array();
	}

	/**
	 * Scan all published rules and rebuild the membership index option.
	 * Includes only active rules. First active rule wins for any given
	 * membership, matching the brief.
	 */
	public function rebuild_index() {
		$rule_ids = get_posts( array(
			'post_type'        => KDNA_EARLY_BIRD_CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => 'menu_order ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		) );

		$index     = array();
		$overrides = array();

		if ( is_array( $rule_ids ) ) {
			foreach ( $rule_ids as $rule_id ) {
				$rows = get_post_meta( $rule_id, KDNA_Early_Bird_Rules::META_ROWS, true );
				if ( ! is_array( $rows ) || empty( $rows ) ) {
					continue;
				}

				$active     = (int) get_post_meta( $rule_id, KDNA_Early_Bird_Rules::META_ACTIVE, true );
				$rule_title = get_the_title( $rule_id );

				// Pass one. Scan rows for filled in test overrides. Done
				// regardless of active state so a stale override on an
				// inactive rule still triggers the warning banner.
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$mid = isset( $row['membership_id'] ) ? (int) $row['membership_id'] : 0;
					if ( $mid <= 0 ) {
						continue;
					}
					$override_raw = isset( $row['test_override_count'] ) ? $row['test_override_count'] : '';
					if ( '' === $override_raw || null === $override_raw ) {
						continue;
					}
					$membership_title = get_the_title( $mid );
					if ( '' === $membership_title ) {
						/* translators: %d is the membership post id. */
						$membership_title = sprintf( __( 'Membership #%d', 'kdna-early-bird' ), $mid );
					}
					$overrides[] = array(
						'rule_id'          => (int) $rule_id,
						'rule_title'       => '' !== $rule_title ? $rule_title : __( '(untitled rule)', 'kdna-early-bird' ),
						'rule_active'      => 1 === $active,
						'membership_id'    => $mid,
						'membership_title' => $membership_title,
						'override_count'   => max( 0, (int) $override_raw ),
					);
				}

				// Pass two. Only active rules contribute to the live
				// membership index used by the price filter.
				if ( 1 !== $active ) {
					continue;
				}

				$start_date      = (string) get_post_meta( $rule_id, KDNA_Early_Bird_Rules::META_START_DATE, true );
				$time_limit_type = (string) get_post_meta( $rule_id, KDNA_Early_Bird_Rules::META_TIME_LIMIT_TYPE, true );
				$end_date_field  = (string) get_post_meta( $rule_id, KDNA_Early_Bird_Rules::META_END_DATE, true );
				$days_from_start = get_post_meta( $rule_id, KDNA_Early_Bird_Rules::META_DAYS_FROM_START, true );

				if ( ! in_array( $time_limit_type, array( 'none', 'date', 'days' ), true ) ) {
					$time_limit_type = 'none';
				}

				$end_date = '';
				if ( 'date' === $time_limit_type ) {
					$end_date = $end_date_field;
				} elseif ( 'days' === $time_limit_type && '' !== $start_date && '' !== (string) $days_from_start ) {
					$timestamp = strtotime( $start_date . ' +' . (int) $days_from_start . ' days' );
					if ( false !== $timestamp ) {
						// The brief says "ends a number of days after the
						// start date". We treat the last live day as
						// start + days, so the offer is no longer live
						// from the day after.
						$end_date = gmdate( 'Y-m-d', $timestamp );
					}
				}

				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$mid = isset( $row['membership_id'] ) ? (int) $row['membership_id'] : 0;
					if ( $mid <= 0 ) {
						continue;
					}
					// First active rule wins. Later rules are ignored for
					// this membership.
					if ( isset( $index[ $mid ] ) ) {
						continue;
					}

					$price = isset( $row['early_bird_price'] ) ? (string) $row['early_bird_price'] : '';

					$cap_raw = isset( $row['purchase_cap'] ) ? $row['purchase_cap'] : '';
					$cap     = ( '' === $cap_raw || null === $cap_raw ) ? null : max( 0, (int) $cap_raw );

					$override_raw = isset( $row['test_override_count'] ) ? $row['test_override_count'] : '';
					$override     = ( '' === $override_raw || null === $override_raw ) ? null : max( 0, (int) $override_raw );

					$index[ $mid ] = array(
						'rule_id'             => (int) $rule_id,
						'rule_title'          => $rule_title,
						'early_bird_price'    => $price,
						'purchase_cap'        => $cap,
						'test_override_count' => $override,
						'start_date'          => $start_date,
						'time_limit_type'     => $time_limit_type,
						'end_date'            => $end_date,
					);
				}
			}
		}

		update_option( self::OPTION_INDEX, $index, true );
		// Overrides list is admin only, no need to autoload it.
		update_option( self::OPTION_OVERRIDES, $overrides, false );
		self::$request_cache = array();
		return $index;
	}

	/**
	 * Return the cached list of filled in test overrides, building it
	 * lazily if it has not been computed yet.
	 */
	public function get_test_overrides() {
		$list = get_option( self::OPTION_OVERRIDES, null );
		if ( ! is_array( $list ) ) {
			$this->rebuild_index();
			$list = get_option( self::OPTION_OVERRIDES, array() );
			if ( ! is_array( $list ) ) {
				$list = array();
			}
		}
		return $list;
	}

	/**
	 * Read the stored full price for a membership, bypassing this plugin's
	 * own price filter. Used by the status panel to show what MemberPress
	 * would charge without the override.
	 */
	public function get_stored_full_price( $membership_id ) {
		$membership_id = (int) $membership_id;
		if ( $membership_id <= 0 ) {
			return '';
		}
		remove_filter( 'get_post_metadata', array( $this, 'filter_price_meta' ), 10 );
		$price = get_post_meta( $membership_id, self::MEPR_PRICE_META, true );
		add_filter( 'get_post_metadata', array( $this, 'filter_price_meta' ), 10, 4 );
		return is_scalar( $price ) ? (string) $price : '';
	}

	/**
	 * Read the price that is currently being served to buyers, which is
	 * exactly what MemberPress sees, since our filter is registered.
	 */
	public function get_served_price( $membership_id ) {
		$membership_id = (int) $membership_id;
		if ( $membership_id <= 0 ) {
			return '';
		}
		$price = get_post_meta( $membership_id, self::MEPR_PRICE_META, true );
		return is_scalar( $price ) ? (string) $price : '';
	}

	/**
	 * Format a price using MemberPress's own currency settings. Reads
	 * the symbol from MeprOptions, falls back to deriving it from the
	 * currency code, then to MeprUtils::format_currency, then to a plain
	 * two decimal number. An explicit override always wins.
	 */
	public static function format_price( $value, $symbol_override = '' ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		$amount    = (float) $value;
		$formatted = number_format( $amount, 2, '.', ',' );

		if ( '' !== (string) $symbol_override ) {
			return (string) $symbol_override . $formatted;
		}

		$currency = self::get_currency_settings();
		if ( '' !== $currency['symbol'] ) {
			return $currency['after']
				? $formatted . $currency['symbol']
				: $currency['symbol'] . $formatted;
		}

		if ( class_exists( 'MeprUtils' ) && method_exists( 'MeprUtils', 'format_currency' ) ) {
			$out = MeprUtils::format_currency( $amount );
			if ( is_string( $out ) && '' !== $out ) {
				return $out;
			}
		}

		return $formatted;
	}

	/**
	 * Read MemberPress's configured currency symbol and position. If the
	 * symbol field is empty we derive a sensible one from the ISO code.
	 */
	private static function get_currency_settings() {
		$result = array( 'symbol' => '', 'after' => false );

		if ( ! class_exists( 'MeprOptions' ) || ! method_exists( 'MeprOptions', 'fetch' ) ) {
			return $result;
		}

		$opts = MeprOptions::fetch();
		if ( ! is_object( $opts ) ) {
			return $result;
		}

		if ( isset( $opts->currency_symbol ) && '' !== (string) $opts->currency_symbol ) {
			$result['symbol'] = (string) $opts->currency_symbol;
		} elseif ( isset( $opts->currency_code ) && '' !== (string) $opts->currency_code ) {
			$result['symbol'] = self::symbol_from_code( (string) $opts->currency_code );
		}

		if ( isset( $opts->currency_symbol_after ) ) {
			$result['after'] = (bool) $opts->currency_symbol_after;
		}

		return $result;
	}

	/**
	 * Fallback symbol map for common ISO currency codes. Used only when
	 * MemberPress's symbol field is blank but the code is set.
	 */
	private static function symbol_from_code( $code ) {
		$map = array(
			'USD' => '$',  'GBP' => '£',  'EUR' => '€',  'JPY' => '¥',  'INR' => '₹',
			'CAD' => 'CA$','AUD' => 'A$', 'NZD' => 'NZ$','CHF' => 'CHF ',
			'SEK' => 'kr ','NOK' => 'kr ','DKK' => 'kr ','ZAR' => 'R',
			'BRL' => 'R$', 'MXN' => 'MX$','KRW' => '₩',  'CNY' => '¥',  'HKD' => 'HK$',
			'SGD' => 'S$', 'TWD' => 'NT$','ILS' => '₪',  'PLN' => 'zł ','TRY' => '₺',
		);
		$up = strtoupper( (string) $code );
		return isset( $map[ $up ] ) ? $map[ $up ] : $code . ' ';
	}
}
