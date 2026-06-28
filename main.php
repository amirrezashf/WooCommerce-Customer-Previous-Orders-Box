<?php
/**
 * Plugin Name: WooCommerce Customer Previous Orders Box
 * Plugin URI: https://github.com/amirrezashf/woocommerce-customer-previous-orders-box
 * Description: Display previous customer orders, previous order status, status change count, reprocessing indicators, customer purchase summary, and order history modal inside the WooCommerce orders list with HPOS support.
 * Version: 1.0.0
 * Author: Amirreza Shayesteh Far
 * Author URI: https://amirrezaa.ir/
 * License: GPL-2.0-or-later
 * Text Domain: woocommerce-customer-previous-orders-box
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*--------------------------------------------------------------
# WooCommerce Guard
--------------------------------------------------------------*/
function fa_prevbox_wc_dependency_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>WooCommerce Customer Previous Orders Box</strong> برای اجرا به افزونه WooCommerce نیاز دارد.</p></div>';
}
add_action( 'admin_notices', 'fa_prevbox_wc_dependency_notice' );

/*--------------------------------------------------------------
# ذخیره وضعیت قبلی سفارش هنگام تغییر وضعیت
--------------------------------------------------------------*/
add_action( 'woocommerce_new_order', 'fa_statusbox_init_order_status_tracking', 10, 1 );
function fa_statusbox_init_order_status_tracking( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$current_status = fa_statusbox_normalize_status_key( $order->get_status() );
	if ( '' === $current_status ) {
		return;
	}

	$history = $order->get_meta( '_fa_statusbox_status_history', true );
	if ( ! is_array( $history ) ) {
		$history = array();
	}

	if ( empty( $history ) ) {
		$history[] = array(
			'from' => '',
			'to'   => $current_status,
			'time' => time(),
		);
	}

	$processing_entries = (int) $order->get_meta( '_fa_statusbox_processing_entries', true );
	if ( $processing_entries <= 0 && 'processing' === $current_status ) {
		$processing_entries = 1;
	}

	$order->update_meta_data( '_fa_statusbox_status_history', $history );
	$order->update_meta_data( '_fa_statusbox_last_status', $current_status );
	$order->update_meta_data( '_fa_statusbox_status_changes_count', max( 0, count( $history ) - 1 ) );
	$order->update_meta_data( '_fa_statusbox_processing_entries', $processing_entries );

	if ( ! $order->meta_exists( '_previous_order_status' ) ) {
		$order->update_meta_data( '_previous_order_status', '' );
	}

	$order->save_meta_data();
}

add_action( 'woocommerce_order_status_changed', 'fa_statusbox_save_previous_order_status', 10, 3 );
function fa_statusbox_save_previous_order_status( $order_id, $old_status, $new_status ) {
	if ( $old_status === $new_status ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$old_status = fa_statusbox_normalize_status_key( $old_status );
	$new_status = fa_statusbox_normalize_status_key( $new_status );

	if ( '' === $new_status ) {
		return;
	}

	$history = $order->get_meta( '_fa_statusbox_status_history', true );
	if ( ! is_array( $history ) ) {
		$history = array();
	}

	$last_history_to = '';
	if ( ! empty( $history ) ) {
		$last_item       = end( $history );
		$last_history_to = isset( $last_item['to'] ) ? fa_statusbox_normalize_status_key( $last_item['to'] ) : '';
		reset( $history );
	}

	$should_append_history = true;
	if ( '' !== $last_history_to && $last_history_to === $new_status ) {
		$should_append_history = false;
	}

	if ( $should_append_history ) {
		$history[] = array(
			'from' => $old_status,
			'to'   => $new_status,
			'time' => time(),
		);
	}

	$processing_entries = (int) $order->get_meta( '_fa_statusbox_processing_entries', true );
	if ( 'processing' === $new_status ) {
		$processing_entries++;
	}

	$order->update_meta_data( '_previous_order_status', sanitize_text_field( $old_status ) );
	$order->update_meta_data( '_fa_statusbox_last_status', sanitize_text_field( $new_status ) );
	$order->update_meta_data( '_fa_statusbox_status_history', $history );
	$order->update_meta_data( '_fa_statusbox_processing_entries', max( 0, $processing_entries ) );
	$order->update_meta_data( '_fa_statusbox_status_changes_count', max( 0, count( $history ) - 1 ) );
	$order->save_meta_data();
}

/*--------------------------------------------------------------
# ابزارها
--------------------------------------------------------------*/
function fa_prevbox_is_wc_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' );
}

function fa_prevbox_is_hpos_enabled() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
		return false;
	}
	return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

function fa_prevbox_cache_ttl() {
	return 15 * MINUTE_IN_SECONDS;
}

function fa_prevbox_modal_cache_key( $user_id, $order_id ) {
	return 'fa_prevbox_modal_' . absint( $user_id ) . '_' . absint( $order_id );
}

function fa_prevbox_to_english_digits( $value ) {
	$value   = (string) $value;
	$persian = array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' );
	$arabic  = array( '٠','١','٢','٣','٤','٥','٦','٧','٨','٩' );
	$english = array( '0','1','2','3','4','5','6','7','8','9' );

	$value = str_replace( $persian, $english, $value );
	$value = str_replace( $arabic, $english, $value );

	return $value;
}

function fa_prevbox_order_num_en( $order_id ) {
	return fa_prevbox_to_english_digits( (string) absint( $order_id ) );
}

function fa_prevbox_money_plain( $amount ) {
	$amount = (float) $amount;

	if ( function_exists( 'wc_price' ) ) {
		$text = wc_price( $amount, array( 'decimals' => 0 ) );
	} else {
		$text = number_format_i18n( $amount );
	}

	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = trim( preg_replace( '/\s+/', ' ', $text ) );

	return fa_prevbox_to_english_digits( $text );
}

function fa_prevbox_format_order_date( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return '—';
	}

	$date = $order->get_date_created();
	if ( ! $date ) {
		return '—';
	}

	return wp_date(
		get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ),
		$date->getTimestamp(),
		wp_timezone()
	);
}

function fa_statusbox_normalize_status_key( $status ) {
	$status = (string) $status;
	$status = sanitize_key( $status );
	$status = preg_replace( '/^wc-/', '', $status );
	return trim( $status );
}

function fa_statusbox_get_previous_status_from_history( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return '';
	}

	$history = $order->get_meta( '_fa_statusbox_status_history', true );
	if ( ! is_array( $history ) || count( $history ) < 2 ) {
		return '';
	}

	$last_index = count( $history ) - 1;
	$prev_index = $last_index - 1;

	if ( ! isset( $history[ $prev_index ]['to'] ) ) {
		return '';
	}

	return fa_statusbox_normalize_status_key( $history[ $prev_index ]['to'] );
}

function fa_statusbox_get_previous_status( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return '';
	}

	$current_status  = fa_statusbox_normalize_status_key( $order->get_status() );
	$previous_status = fa_statusbox_normalize_status_key( $order->get_meta( '_previous_order_status', true ) );

	if ( '' !== $previous_status && $previous_status !== $current_status ) {
		return $previous_status;
	}

	$history_previous = fa_statusbox_get_previous_status_from_history( $order );
	if ( '' !== $history_previous && $history_previous !== $current_status ) {
		return $history_previous;
	}

	$last_status = fa_statusbox_normalize_status_key( $order->get_meta( '_fa_statusbox_last_status', true ) );
	if ( '' !== $last_status && $last_status !== $current_status ) {
		return $last_status;
	}

	return '';
}

function fa_statusbox_get_status_changes_count( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return 0;
	}

	$count = (int) $order->get_meta( '_fa_statusbox_status_changes_count', true );
	if ( $count > 0 ) {
		return $count;
	}

	$history = $order->get_meta( '_fa_statusbox_status_history', true );
	if ( is_array( $history ) ) {
		return max( 0, count( $history ) - 1 );
	}

	return 0;
}

function fa_statusbox_is_reprocessed( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	$processing_entries = (int) $order->get_meta( '_fa_statusbox_processing_entries', true );
	if ( $processing_entries > 1 ) {
		return true;
	}

	$history = $order->get_meta( '_fa_statusbox_status_history', true );
	if ( ! is_array( $history ) || empty( $history ) ) {
		return false;
	}

	$count = 0;
	foreach ( $history as $item ) {
		$to = isset( $item['to'] ) ? fa_statusbox_normalize_status_key( $item['to'] ) : '';
		if ( 'processing' === $to ) {
			$count++;
		}
	}

	return $count > 1;
}

function fa_prevbox_get_status_badge_html( $status ) {
	$status      = fa_statusbox_normalize_status_key( $status );
	$status_key  = 'wc-' . $status;
	$status_name = wc_get_order_status_name( $status_key );

	if ( '' === trim( (string) $status_name ) ) {
		$status_name = $status_key;
	}

	$map = array(
		'pending'    => 'fa-prevbox-badge--pending',
		'processing' => 'fa-prevbox-badge--processing',
		'completed'  => 'fa-prevbox-badge--completed',
		'on-hold'    => 'fa-prevbox-badge--onhold',
		'cancelled'  => 'fa-prevbox-badge--cancelled',
		'refunded'   => 'fa-prevbox-badge--refunded',
		'failed'     => 'fa-prevbox-badge--failed',
	);

	$class = isset( $map[ $status ] ) ? $map[ $status ] : 'fa-prevbox-badge--default';

	return '<span class="fa-prevbox-badge ' . esc_attr( $class ) . '">' . esc_html( $status_name ) . '</span>';
}

function fa_prevbox_get_order_items_names_array( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return array();
	}

	$names = array();

	foreach ( $order->get_items() as $item ) {
		$name = trim( wp_strip_all_tags( $item->get_name() ) );
		if ( '' !== $name ) {
			$names[] = $name;
		}
	}

	return array_values( array_unique( $names ) );
}

function fa_prevbox_get_order_items_short( $order, $limit = 2 ) {
	$names = fa_prevbox_get_order_items_names_array( $order );

	if ( empty( $names ) ) {
		return '—';
	}

	if ( count( $names ) <= $limit ) {
		return implode( '، ', $names );
	}

	$short = array_slice( $names, 0, $limit );
	return implode( '، ', $short ) . ' ...';
}

function fa_prevbox_get_order_items_tooltip( $order ) {
	$names = fa_prevbox_get_order_items_names_array( $order );

	if ( empty( $names ) ) {
		return 'آیتمی ثبت نشده است';
	}

	return implode( ' | ', $names );
}

function fa_prevbox_get_order_edit_link( $order_id ) {
	$order_id = absint( $order_id );

	if ( fa_prevbox_is_hpos_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
	}

	return get_edit_post_link( $order_id, '' );
}

function fa_prevbox_sql_placeholders( $count, $type = '%s' ) {
	if ( $count <= 0 ) {
		return '';
	}
	return implode( ',', array_fill( 0, $count, $type ) );
}

function fa_prevbox_get_statuses() {
	static $statuses = null;

	if ( null !== $statuses ) {
		return $statuses;
	}

	if ( function_exists( 'wc_get_order_statuses' ) ) {
		$statuses = array_keys( wc_get_order_statuses() );
	} else {
		$statuses = array();
	}

	if ( empty( $statuses ) ) {
		$statuses = array(
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-completed',
			'wc-cancelled',
			'wc-refunded',
			'wc-failed',
		);
	}

	return array_values( array_unique( array_filter( $statuses ) ) );
}

function fa_prevbox_format_duration_human( $seconds ) {
	$seconds = absint( $seconds );

	if ( $seconds <= 0 ) {
		return '0 ساعت';
	}

	$days    = floor( $seconds / DAY_IN_SECONDS );
	$hours   = floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
	$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

	$parts = array();

	if ( $days > 0 ) {
		$parts[] = fa_prevbox_to_english_digits( (string) $days ) . ' روز';
	}

	if ( $hours > 0 ) {
		$parts[] = fa_prevbox_to_english_digits( (string) $hours ) . ' ساعت';
	}

	if ( $minutes > 0 && count( $parts ) < 2 ) {
		$parts[] = fa_prevbox_to_english_digits( (string) $minutes ) . ' دقیقه';
	}

	if ( empty( $parts ) ) {
		$parts[] = 'کمتر از 1 ساعت';
	}

	return implode( ' و ', array_slice( $parts, 0, 2 ) );
}

function fa_prevbox_get_user_order_timestamps( $user_id, $limit = 200 ) {
	global $wpdb;

	$user_id = absint( $user_id );
	$limit   = absint( $limit );

	if ( $user_id <= 0 ) {
		return array();
	}

	if ( $limit <= 0 ) {
		$limit = 200;
	}

	$statuses  = fa_prevbox_get_statuses();
	$status_ph = fa_prevbox_sql_placeholders( count( $statuses ), '%s' );

	if ( fa_prevbox_is_hpos_enabled() ) {
		$table = $wpdb->prefix . 'wc_orders';

		$sql = "
			SELECT date_created_gmt
			FROM {$table}
			WHERE customer_id = %d
			AND type = 'shop_order'
			AND status IN ({$status_ph})
			AND date_created_gmt IS NOT NULL
			AND date_created_gmt != '0000-00-00 00:00:00'
			ORDER BY date_created_gmt ASC, id ASC
			LIMIT %d
		";

		$args = array_merge( array( $user_id ), $statuses, array( $limit ) );
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
	} else {
		$posts    = $wpdb->posts;
		$postmeta = $wpdb->postmeta;

		$sql = "
			SELECT p.post_date_gmt
			FROM {$posts} p
			INNER JOIN {$postmeta} pm_user
				ON p.ID = pm_user.post_id
				AND pm_user.meta_key = '_customer_user'
			WHERE CAST(pm_user.meta_value AS UNSIGNED) = %d
			AND p.post_type = 'shop_order'
			AND p.post_status IN ({$status_ph})
			AND p.post_date_gmt IS NOT NULL
			AND p.post_date_gmt != '0000-00-00 00:00:00'
			ORDER BY p.post_date_gmt ASC, p.ID ASC
			LIMIT %d
		";

		$args = array_merge( array( $user_id ), $statuses, array( $limit ) );
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
	}

	$timestamps = array();

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $gmt_date ) {
			$timestamp = mysql2date( 'U', $gmt_date, false );
			if ( $timestamp ) {
				$timestamps[] = (int) $timestamp;
			}
		}
	}

	return $timestamps;
}

function fa_prevbox_get_average_purchase_gap_data( $user_id ) {
	$timestamps = fa_prevbox_get_user_order_timestamps( $user_id, 250 );
	$total      = count( $timestamps );

	if ( $total <= 5 ) {
		return array(
			'show'             => false,
			'total_orders'     => $total,
			'average_gap'      => 0,
			'average_gap_text' => '',
		);
	}

	if ( $total < 2 ) {
		return array(
			'show'             => false,
			'total_orders'     => $total,
			'average_gap'      => 0,
			'average_gap_text' => '',
		);
	}

	sort( $timestamps, SORT_NUMERIC );

	$gaps = array();
	$last = null;

	foreach ( $timestamps as $timestamp ) {
		if ( null !== $last && $timestamp > $last ) {
			$gaps[] = $timestamp - $last;
		}
		$last = $timestamp;
	}

	if ( empty( $gaps ) ) {
		return array(
			'show'             => false,
			'total_orders'     => $total,
			'average_gap'      => 0,
			'average_gap_text' => '',
		);
	}

	$average_gap = (int) floor( array_sum( $gaps ) / count( $gaps ) );

	return array(
		'show'             => true,
		'total_orders'     => $total,
		'average_gap'      => $average_gap,
		'average_gap_text' => fa_prevbox_format_duration_human( $average_gap ),
	);
}

/*--------------------------------------------------------------
# پاکسازی کش مودال
--------------------------------------------------------------*/
function fa_prevbox_clear_user_modal_cache( $user_id ) {
	global $wpdb;

	$user_id = absint( $user_id );
	if ( $user_id <= 0 ) {
		return;
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_fa_prevbox_modal_' . $user_id . '_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_fa_prevbox_modal_' . $user_id . '_' ) . '%'
		)
	);
}

function fa_prevbox_clear_cache_by_order( $order_id ) {
	if ( ! fa_prevbox_is_wc_active() ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}

	$user_id = (int) $order->get_user_id();
	if ( $user_id > 0 ) {
		fa_prevbox_clear_user_modal_cache( $user_id );
	}
}

add_action( 'woocommerce_new_order', 'fa_prevbox_clear_cache_by_order', 10 );
add_action( 'woocommerce_update_order', 'fa_prevbox_clear_cache_by_order', 10 );
add_action( 'woocommerce_order_status_changed', 'fa_prevbox_clear_cache_by_order', 10 );

add_action( 'before_delete_post', function( $post_id ) {
	if ( 'shop_order' === get_post_type( $post_id ) ) {
		fa_prevbox_clear_cache_by_order( $post_id );
	}
}, 10 );

/*--------------------------------------------------------------
# افزودن وضعیت قبلی + دکمه سفارشات قبلی داخل ستون وضعیت
--------------------------------------------------------------*/
function fa_prevbox_render_inside_order_status_column( $column, $order_or_post_id ) {
	if ( 'order_status' !== $column || ! fa_prevbox_is_wc_active() ) {
		return;
	}

	$order = is_a( $order_or_post_id, 'WC_Order' ) ? $order_or_post_id : wc_get_order( $order_or_post_id );

	if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'shop_order' !== $order->get_type() ) {
		return;
	}

	$order_id             = $order->get_id();
	$user_id              = (int) $order->get_user_id();
	$previous_status      = fa_statusbox_get_previous_status( $order );
	$current_status       = fa_statusbox_normalize_status_key( $order->get_status() );
	$status_changes_count = fa_statusbox_get_status_changes_count( $order );
	$is_reprocessed       = fa_statusbox_is_reprocessed( $order );

	echo '<div class="fa-statusbox-wrap">';

	if ( ! empty( $previous_status ) && $previous_status !== $current_status ) {
		$previous_status_name = wc_get_order_status_name( 'wc-' . $previous_status );
		if ( '' === trim( (string) $previous_status_name ) ) {
			$previous_status_name = 'wc-' . $previous_status;
		}

		echo '<div class="fa-statusbox-previous">';
		echo '<span class="fa-statusbox-previous-label">وضعیت قبلی</span>';
		echo '<span class="fa-statusbox-previous-value">' . esc_html( $previous_status_name ) . '</span>';
		echo '</div>';
	}

	if ( $is_reprocessed ) {
		echo '<div class="fa-statusbox-reprocessed">';
		echo '<span class="fa-statusbox-reprocessed-label">ورود مجدد به در حال انجام</span>';
		echo '</div>';
	}

	echo '<div class="fa-statusbox-changes">';
	echo '<span class="fa-statusbox-changes-label">مجموع تغییر وضعیت‌ها</span>';
	echo '<span class="fa-statusbox-changes-value">' . esc_html( fa_prevbox_to_english_digits( (string) $status_changes_count ) ) . '</span>';
	echo '</div>';

	echo '<div class="fa-prevbox-col-wrap">';

	if ( $user_id <= 0 ) {
		echo '<button type="button" class="button fa-prevbox-btn is-disabled" disabled="disabled">بدون سفارش قبلی</button>';
		echo '</div>';
		echo '</div>';
		return;
	}

	echo '<button type="button"
		class="button fa-prevbox-btn is-loading fa-prevbox-open-modal fa-prevbox-gtooltip"
		data-user-id="' . esc_attr( $user_id ) . '"
		data-order-id="' . esc_attr( $order_id ) . '"
		data-order-number="' . esc_attr( fa_prevbox_order_num_en( $order_id ) ) . '"
		data-tooltip="در حال بارگذاری..."
		aria-label="نمایش سفارشات قبلی"
		disabled="disabled">...</button>';

	echo '</div>';
	echo '</div>';
}
add_action( 'manage_shop_order_posts_custom_column', 'fa_prevbox_render_inside_order_status_column', 20, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'fa_prevbox_render_inside_order_status_column', 20, 2 );

/*--------------------------------------------------------------
# آمار گروهی دکمه‌ها - فقط یک AJAX برای کل صفحه
--------------------------------------------------------------*/
function fa_prevbox_get_bulk_stats_for_users( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_filter( array_map( 'absint', (array) $user_ids ) ) );
	$user_ids = array_slice( array_unique( $user_ids ), 0, 300 );

	if ( empty( $user_ids ) ) {
		return array();
	}

	$statuses      = fa_prevbox_get_statuses();
	$user_ph       = fa_prevbox_sql_placeholders( count( $user_ids ), '%d' );
	$status_ph     = fa_prevbox_sql_placeholders( count( $statuses ), '%s' );
	$prepared_args = array_merge( $user_ids, $statuses );
	$results       = array();

	if ( fa_prevbox_is_hpos_enabled() ) {
		$table = $wpdb->prefix . 'wc_orders';

		$sql = "
			SELECT
				customer_id AS user_id,
				COUNT(id) AS order_count,
				COALESCE(SUM(total_amount), 0) AS total_spent,
				MAX(date_created_gmt) AS last_order_gmt
			FROM {$table}
			WHERE customer_id IN ({$user_ph})
			AND type = 'shop_order'
			AND status IN ({$status_ph})
			GROUP BY customer_id
		";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $prepared_args ) );
	} else {
		$posts    = $wpdb->posts;
		$postmeta = $wpdb->postmeta;

		$sql = "
			SELECT
				CAST(pm_user.meta_value AS UNSIGNED) AS user_id,
				COUNT(p.ID) AS order_count,
				COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(20,6))), 0) AS total_spent,
				MAX(p.post_date_gmt) AS last_order_gmt
			FROM {$posts} p
			INNER JOIN {$postmeta} pm_user
				ON p.ID = pm_user.post_id
				AND pm_user.meta_key = '_customer_user'
			LEFT JOIN {$postmeta} pm_total
				ON p.ID = pm_total.post_id
				AND pm_total.meta_key = '_order_total'
			WHERE CAST(pm_user.meta_value AS UNSIGNED) IN ({$user_ph})
			AND p.post_type = 'shop_order'
			AND p.post_status IN ({$status_ph})
			GROUP BY CAST(pm_user.meta_value AS UNSIGNED)
		";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $prepared_args ) );
	}

	foreach ( $user_ids as $uid ) {
		$results[ $uid ] = array(
			'order_count'     => 0,
			'previous_count'  => 0,
			'total_spent'     => fa_prevbox_money_plain( 0 ),
			'last_order_date' => '',
			'tooltip'         => "تعداد سفارش‌های قبلی: 0\nمجموع خرید: " . fa_prevbox_money_plain( 0 ),
		);
	}

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			$user_id     = isset( $row->user_id ) ? absint( $row->user_id ) : 0;
			$order_count = isset( $row->order_count ) ? (int) $row->order_count : 0;
			$total_spent = isset( $row->total_spent ) ? (float) $row->total_spent : 0;
			$last_gmt    = isset( $row->last_order_gmt ) ? (string) $row->last_order_gmt : '';

			if ( $user_id <= 0 ) {
				continue;
			}

			$previous_count = max( 0, $order_count - 1 );
			$last_date      = '';

			if ( $last_gmt && '0000-00-00 00:00:00' !== $last_gmt ) {
				$timestamp = mysql2date( 'U', $last_gmt, false );
				if ( $timestamp ) {
					$last_date = wp_date(
						get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ),
						$timestamp,
						wp_timezone()
					);
				}
			}

			$tooltip_lines   = array();
			$tooltip_lines[] = 'تعداد سفارش‌های قبلی: ' . fa_prevbox_to_english_digits( (string) $previous_count );
			$tooltip_lines[] = 'مجموع خرید: ' . fa_prevbox_money_plain( $total_spent );

			if ( '' !== $last_date ) {
				$tooltip_lines[] = 'آخرین خرید: ' . fa_prevbox_to_english_digits( $last_date );
			}

			$results[ $user_id ] = array(
				'order_count'      => $order_count,
				'previous_count'   => $previous_count,
				'total_spent'      => fa_prevbox_money_plain( $total_spent ),
				'last_order_date'  => fa_prevbox_to_english_digits( $last_date ),
				'tooltip'          => implode( "\n", $tooltip_lines ),
			);
		}
	}

	return $results;
}

function fa_prevbox_ajax_batch_stats() {
	if ( ! fa_prevbox_is_wc_active() ) {
		wp_send_json_error();
	}

	check_ajax_referer( 'fa_prevbox_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error();
	}

	$user_ids = isset( $_POST['user_ids'] ) ? (array) wp_unslash( $_POST['user_ids'] ) : array();

	$data = fa_prevbox_get_bulk_stats_for_users( $user_ids );

	wp_send_json_success( array( 'stats' => $data ) );
}
add_action( 'wp_ajax_fa_prevbox_batch_stats', 'fa_prevbox_ajax_batch_stats' );

/*--------------------------------------------------------------
# گرفتن شناسه سفارشات قبلی - فقط هنگام کلیک روی مودال
--------------------------------------------------------------*/
function fa_prevbox_get_previous_order_ids( $user_id, $current_order_id, $limit = 25 ) {
	global $wpdb;

	$user_id          = absint( $user_id );
	$current_order_id = absint( $current_order_id );
	$limit            = absint( $limit );

	if ( $user_id <= 0 ) {
		return array();
	}

	if ( $limit <= 0 ) {
		$limit = 25;
	}

	$statuses  = fa_prevbox_get_statuses();
	$status_ph = fa_prevbox_sql_placeholders( count( $statuses ), '%s' );

	if ( fa_prevbox_is_hpos_enabled() ) {
		$table = $wpdb->prefix . 'wc_orders';

		$sql = "
			SELECT id
			FROM {$table}
			WHERE customer_id = %d
			AND type = 'shop_order'
			AND id != %d
			AND status IN ({$status_ph})
			ORDER BY date_created_gmt DESC, id DESC
			LIMIT %d
		";

		$args = array_merge( array( $user_id, $current_order_id ), $statuses, array( $limit ) );
		$ids  = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
	} else {
		$posts    = $wpdb->posts;
		$postmeta = $wpdb->postmeta;

		$sql = "
			SELECT p.ID
			FROM {$posts} p
			INNER JOIN {$postmeta} pm_user
				ON p.ID = pm_user.post_id
				AND pm_user.meta_key = '_customer_user'
			WHERE CAST(pm_user.meta_value AS UNSIGNED) = %d
			AND p.post_type = 'shop_order'
			AND p.ID != %d
			AND p.post_status IN ({$status_ph})
			ORDER BY p.post_date_gmt DESC, p.ID DESC
			LIMIT %d
		";

		$args = array_merge( array( $user_id, $current_order_id ), $statuses, array( $limit ) );
		$ids  = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
	}

	return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
}

/*--------------------------------------------------------------
# ساخت HTML مودال
--------------------------------------------------------------*/
function fa_prevbox_build_modal_html( $user_id, $order_id ) {
	$stats      = fa_prevbox_get_bulk_stats_for_users( array( $user_id ) );
	$stat       = isset( $stats[ $user_id ] ) ? $stats[ $user_id ] : array(
		'order_count'      => 0,
		'previous_count'   => 0,
		'total_spent'      => fa_prevbox_money_plain( 0 ),
		'last_order_date'  => '',
	);
	$order_ids   = fa_prevbox_get_previous_order_ids( $user_id, $order_id, 25 );
	$gap_data    = fa_prevbox_get_average_purchase_gap_data( $user_id );
	$show_gap    = ! empty( $gap_data['show'] ) && ! empty( $gap_data['average_gap_text'] );

	ob_start();

	echo '<div class="fa-prevbox-summary' . ( $show_gap ? ' fa-prevbox-summary--four' : '' ) . '">';
	echo '<div class="fa-prevbox-summary-box fa-prevbox-summary-box--count">';
	echo '<span class="fa-prevbox-summary-label">تعداد سفارشات قبلی</span>';
	echo '<span class="fa-prevbox-summary-value"><span class="fa-prevbox-en-num">' . esc_html( fa_prevbox_to_english_digits( (string) count( $order_ids ) ) ) . '</span></span>';
	echo '</div>';

	echo '<div class="fa-prevbox-summary-box fa-prevbox-summary-box--spent">';
	echo '<span class="fa-prevbox-summary-label">مجموع خرید مشتری</span>';
	echo '<span class="fa-prevbox-summary-value">' . esc_html( $stat['total_spent'] ) . '</span>';
	echo '</div>';

	echo '<div class="fa-prevbox-summary-box fa-prevbox-summary-box--last">';
	echo '<span class="fa-prevbox-summary-label">آخرین خرید</span>';
	echo '<span class="fa-prevbox-summary-value">' . esc_html( ! empty( $stat['last_order_date'] ) ? $stat['last_order_date'] : '—' ) . '</span>';
	echo '</div>';

	if ( $show_gap ) {
		echo '<div class="fa-prevbox-summary-box fa-prevbox-summary-box--gap">';
		echo '<span class="fa-prevbox-summary-label">میانگین فاصله هر خرید</span>';
		echo '<span class="fa-prevbox-summary-value">' . esc_html( $gap_data['average_gap_text'] ) . '</span>';
		echo '</div>';
	}

	echo '</div>';

	if ( empty( $order_ids ) ) {
		echo '<div class="fa-prevbox-empty">هیچ سفارش قبلی‌ای برای این مشتری پیدا نشد.</div>';
		return ob_get_clean();
	}

	echo '<div class="fa-prevbox-list">';

	foreach ( $order_ids as $prev_order_id ) {
		$order = wc_get_order( $prev_order_id );

		if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'shop_order' !== $order->get_type() ) {
			continue;
		}

		$item_names_short   = fa_prevbox_get_order_items_short( $order, 2 );
		$item_names_tooltip = fa_prevbox_get_order_items_tooltip( $order );

		echo '<div class="fa-prevbox-card">';
		echo '<div class="fa-prevbox-card-top">';
		echo '<h4 class="fa-prevbox-card-title">#<span class="fa-prevbox-en-num">' . esc_html( fa_prevbox_order_num_en( $prev_order_id ) ) . '</span></h4>';
		echo wp_kses_post( fa_prevbox_get_status_badge_html( $order->get_status() ) );
		echo '</div>';

		echo '<div class="fa-prevbox-card-meta">';
		echo '<span>تاریخ: ' . esc_html( fa_prevbox_to_english_digits( fa_prevbox_format_order_date( $order ) ) ) . '</span>';
		echo '<span>مبلغ: ' . esc_html( fa_prevbox_money_plain( $order->get_total() ) ) . '</span>';
		echo '</div>';

		echo '<div class="fa-prevbox-card-items fa-prevbox-gtooltip" data-tooltip="' . esc_attr( $item_names_tooltip ) . '"><strong>آیتم‌ها:</strong> ' . esc_html( $item_names_short ) . '</div>';

		echo '<div class="fa-prevbox-card-actions">';
		echo '<a href="' . esc_url( fa_prevbox_get_order_edit_link( $prev_order_id ) ) . '" class="fa-prevbox-view-link" target="_blank" rel="noopener noreferrer">مشاهده سفارش</a>';
		echo '<button type="button" class="fa-prevbox-icon-btn" data-copy-text="' . esc_attr( fa_prevbox_order_num_en( $prev_order_id ) ) . '" aria-label="کپی شماره سفارش" title="کپی شماره سفارش">';
		echo '<span class="dashicons dashicons-admin-page"></span>';
		echo '</button>';
		echo '</div>';

		echo '</div>';
	}

	echo '</div>';

	return ob_get_clean();
}

/*--------------------------------------------------------------
# ایجکس مودال با کش 15 دقیقه‌ای
--------------------------------------------------------------*/
function fa_prevbox_ajax_get_modal() {
	if ( ! fa_prevbox_is_wc_active() ) {
		wp_send_json_error();
	}

	check_ajax_referer( 'fa_prevbox_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error();
	}

	$user_id  = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
	$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;

	if ( $user_id <= 0 || $order_id <= 0 ) {
		wp_send_json_error();
	}

	$current_order = wc_get_order( $order_id );
	if ( ! $current_order || ! is_a( $current_order, 'WC_Order' ) || 'shop_order' !== $current_order->get_type() ) {
		wp_send_json_error();
	}

	$cache_key = fa_prevbox_modal_cache_key( $user_id, $order_id );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached && is_array( $cached ) ) {
		wp_send_json_success( $cached );
	}

	$order_ids = fa_prevbox_get_previous_order_ids( $user_id, $order_id, 25 );

	$response = array(
		'subtitle' => 'سفارش جاری: #' . fa_prevbox_order_num_en( $order_id ) . ' | نمایش ' . fa_prevbox_to_english_digits( (string) count( $order_ids ) ) . ' سفارش اخیر',
		'html'     => fa_prevbox_build_modal_html( $user_id, $order_id ),
	);

	set_transient( $cache_key, $response, fa_prevbox_cache_ttl() );

	wp_send_json_success( $response );
}
add_action( 'wp_ajax_fa_prevbox_get_modal', 'fa_prevbox_ajax_get_modal' );

/*--------------------------------------------------------------
# استایل و اسکریپت
--------------------------------------------------------------*/
function fa_prevbox_admin_assets() {
	if ( ! fa_prevbox_is_wc_active() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return;
	}

	$allowed_screens = array(
		'edit-shop_order',
		'woocommerce_page_wc-orders',
	);

	if ( ! in_array( $screen->id, $allowed_screens, true ) ) {
		return;
	}

	wp_register_style( 'fa-prevbox-inline-style', false, array(), '1.0.0' );
	wp_enqueue_style( 'fa-prevbox-inline-style' );

	$css = '
	.fa-statusbox-wrap{
	display:flex;
	flex-direction:column;
	align-items:center;
	justify-content:center;
	gap:7px;
	margin-top:6px;
	text-align:center;
}
	.fa-statusbox-previous{
	display:inline-flex;
	align-items:center;
	justify-content:center;
	gap:6px;
	flex-wrap:wrap;
	padding:5px 9px;
	border:1px solid #e9e5ff;
	background:linear-gradient(180deg,#faf7ff 0%,#f5f3ff 100%);
	border-radius:999px;
	box-shadow:0 4px 12px rgba(109,40,217,.06);
	text-align:center;
}
	.fa-statusbox-previous-label{
		font-size:10px;
		font-weight:700;
		line-height:1.6;
		color:#6b7280;
	}
	.fa-statusbox-previous-value{
		font-size:11px;
		font-weight:800;
		line-height:1.6;
		color:#6d28d9;
	}
	.fa-statusbox-reprocessed{
		display:inline-flex;
		align-items:center;
		justify-content:center;
		padding:5px 9px;
		border:1px solid #bfdbfe;
		background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);
		border-radius:999px;
		box-shadow:0 4px 12px rgba(37,99,235,.08);
		text-align:center;
	}
	.fa-statusbox-reprocessed-label{
		font-size:10px;
		font-weight:800;
		line-height:1.6;
		color:#1d4ed8;
	}
	.fa-statusbox-changes{
		display:inline-flex;
		align-items:center;
		justify-content:center;
		gap:5px;
		flex-wrap:wrap;
		padding:4px 8px;
		border:1px solid #e5e7eb;
		background:#ffffff;
		border-radius:999px;
		text-align:center;
	}
	.fa-statusbox-changes-label{
		font-size:10px;
		font-weight:700;
		line-height:1.6;
		color:#6b7280;
	}
	.fa-statusbox-changes-value{
		font-size:11px;
		font-weight:800;
		line-height:1.6;
		color:#111827;
		font-family:Arial, Helvetica, sans-serif !important;
		direction:ltr !important;
		unicode-bidi:isolate !important;
	}

	.fa-prevbox-col-wrap{
	display:flex;
	align-items:center;
	justify-content:center;
	position:relative;
	width:100%;
}
	.fa-prevbox-btn{
		min-width:116px;
		height:31px;
		padding:0 10px !important;
		border-radius:10px !important;
		display:inline-flex !important;
		align-items:center;
		justify-content:center;
		font-size:11px !important;
		font-weight:800 !important;
		line-height:1 !important;
		box-shadow:none !important;
		background:#6d28d9 !important;
		border:1px solid #6d28d9 !important;
		color:#fff !important;
		position:relative;
		white-space:nowrap;
	}
	.fa-prevbox-btn:hover{
		background:#5b21b6 !important;
		border-color:#5b21b6 !important;
		color:#fff !important;
	}
	.fa-prevbox-btn.is-disabled,
	.fa-prevbox-btn[disabled]{
		background:#f3f4f6 !important;
		border-color:#e5e7eb !important;
		color:#6b7280 !important;
		cursor:not-allowed !important;
	}
	.fa-prevbox-btn.is-loading{
		opacity:.85;
	}
	.fa-prevbox-en-num{
		font-family:Arial, Helvetica, sans-serif !important;
		direction:ltr !important;
		unicode-bidi:isolate !important;
		font-variant-numeric:normal !important;
		letter-spacing:.1px;
	}

	.fa-prevbox-gtooltip{
		position:relative;
	}
	.fa-prevbox-gtooltip::before{
		content:attr(data-tooltip);
		position:absolute;
		right:50%;
		bottom:calc(100% + 10px);
		transform:translateX(50%) translateY(6px);
		min-width:170px;
		max-width:250px;
		white-space:pre-line;
		padding:10px 12px;
		border-radius:12px;
		background:#111827;
		color:#fff;
		font-size:11px;
		line-height:1.9;
		font-weight:700;
		text-align:right;
		box-shadow:0 16px 30px rgba(0,0,0,.18);
		border:1px solid rgba(255,255,255,.08);
		opacity:0;
		visibility:hidden;
		pointer-events:none;
		transition:all .18s ease;
		z-index:9999;
	}
	.fa-prevbox-gtooltip::after{
		content:"";
		position:absolute;
		right:50%;
		bottom:calc(100% + 4px);
		transform:translateX(50%) translateY(6px);
		border-width:6px;
		border-style:solid;
		border-color:#111827 transparent transparent transparent;
		opacity:0;
		visibility:hidden;
		pointer-events:none;
		transition:all .18s ease;
		z-index:9999;
	}
	.fa-prevbox-gtooltip:hover::before,
	.fa-prevbox-gtooltip:hover::after,
	.fa-prevbox-gtooltip:focus::before,
	.fa-prevbox-gtooltip:focus::after{
		opacity:1;
		visibility:visible;
		transform:translateX(50%) translateY(0);
	}

	.fa-prevbox-badge{
		display:inline-flex;
		align-items:center;
		justify-content:center;
		padding:3px 8px;
		border-radius:999px;
		font-size:10px;
		font-weight:700;
		line-height:1.4;
		border:1px solid transparent;
		white-space:nowrap;
	}
	.fa-prevbox-badge--pending{background:#fff7ed;color:#c2410c;border-color:#fed7aa;}
	.fa-prevbox-badge--processing{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
	.fa-prevbox-badge--completed{background:#ecfdf5;color:#047857;border-color:#a7f3d0;}
	.fa-prevbox-badge--onhold{background:#fefce8;color:#a16207;border-color:#fde68a;}
	.fa-prevbox-badge--cancelled{background:#f9fafb;color:#4b5563;border-color:#e5e7eb;}
	.fa-prevbox-badge--refunded{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe;}
	.fa-prevbox-badge--failed{background:#fef2f2;color:#b91c1c;border-color:#fecaca;}
	.fa-prevbox-badge--default{background:#f3f4f6;color:#111827;border-color:#e5e7eb;}

	.fa-prevbox-modal{
		position:fixed;
		inset:0;
		z-index:999999;
		display:none;
		align-items:center;
		justify-content:center;
		padding:18px;
		background:rgba(15,23,42,.42);
	}
	.fa-prevbox-modal.is-open{
		display:flex;
	}
	.fa-prevbox-modal-dialog{
		width:min(860px, 100%);
		height:min(88vh, 860px);
		max-height:88vh;
		display:flex;
		flex-direction:column;
		overflow:hidden;
		border-radius:14px;
		background:#fff;
		border:1px solid #e5e7eb;
		box-shadow:0 18px 50px rgba(0,0,0,.18);
	}
	.fa-prevbox-modal-header{
		flex:0 0 auto;
		display:flex;
		align-items:center;
		justify-content:space-between;
		gap:12px;
		padding:12px 14px;
		border-bottom:1px solid #f1f5f9;
		background:#fff;
	}
	.fa-prevbox-modal-title{
		margin:0;
		font-size:14px;
		font-weight:800;
		line-height:1.6;
		color:#111827;
	}
	.fa-prevbox-modal-subtitle{
		margin:2px 0 0;
		font-size:11px;
		color:#6b7280;
		line-height:1.7;
	}
	.fa-prevbox-modal-close{
		width:40px;
		height:40px;
		border:none;
		border-radius:10px;
		background:#fee2e2;
		color:#dc2626;
		cursor:pointer;
		font-size:24px;
		line-height:1;
		display:inline-flex;
		align-items:center;
		justify-content:center;
		flex:0 0 auto;
	}
	.fa-prevbox-modal-close:hover{
		background:#fecaca;
		color:#b91c1c;
	}
	.fa-prevbox-modal-body{
		flex:1 1 auto;
		min-height:0;
		padding:14px 14px 18px;
		overflow-y:auto;
		overflow-x:hidden;
		overscroll-behavior:contain;
		background:#fafafa;
	}
	.fa-prevbox-loader,
	.fa-prevbox-empty{
		padding:16px;
		text-align:center;
		font-size:12px;
		font-weight:600;
		color:#6b7280;
		background:#fff;
		border:1px dashed #d1d5db;
		border-radius:12px;
	}
	.fa-prevbox-summary{
		display:grid;
		grid-template-columns:repeat(3, minmax(0, 1fr));
		gap:10px;
		margin-bottom:12px;
	}
	.fa-prevbox-summary.fa-prevbox-summary--four{
		grid-template-columns:repeat(4, minmax(0, 1fr));
	}
	.fa-prevbox-summary-box{
		position:relative;
		background:#fff;
		border:1px solid #ececf3;
		border-radius:14px;
		padding:11px 12px;
		overflow:hidden;
		box-shadow:0 6px 16px rgba(15,23,42,.04);
	}
	.fa-prevbox-summary-box::before{
		content:"";
		position:absolute;
		top:0;
		right:0;
		left:0;
		height:3px;
		opacity:.95;
	}
	.fa-prevbox-summary-box--count{
		background:linear-gradient(180deg,#ffffff 0%,#faf5ff 100%);
	}
	.fa-prevbox-summary-box--count::before{
		background:linear-gradient(90deg,#6d28d9,#8b5cf6);
	}
	.fa-prevbox-summary-box--spent{
		background:linear-gradient(180deg,#ffffff 0%,#f0fdfa 100%);
	}
	.fa-prevbox-summary-box--spent::before{
		background:linear-gradient(90deg,#0891b2,#22c55e);
	}
	.fa-prevbox-summary-box--last{
		background:linear-gradient(180deg,#ffffff 0%,#fff7ed 100%);
	}
	.fa-prevbox-summary-box--last::before{
		background:linear-gradient(90deg,#f59e0b,#ef4444);
	}
	.fa-prevbox-summary-box--gap{
		background:linear-gradient(180deg,#ffffff 0%,#eff6ff 100%);
	}
	.fa-prevbox-summary-box--gap::before{
		background:linear-gradient(90deg,#2563eb,#06b6d4);
	}
	.fa-prevbox-summary-label{
		display:block;
		font-size:10px;
		font-weight:700;
		color:#6b7280;
		margin-bottom:5px;
		line-height:1.6;
	}
	.fa-prevbox-summary-value{
		display:block;
		font-size:12px;
		font-weight:800;
		color:#111827;
		line-height:1.8;
	}
	.fa-prevbox-list{
		display:grid;
		grid-template-columns:repeat(2, minmax(0, 1fr));
		gap:10px;
		padding-bottom:6px;
	}
	.fa-prevbox-card{
		background:#fff;
		border:1px solid #e5e7eb;
		border-radius:12px;
		padding:10px;
		display:flex;
		flex-direction:column;
		gap:8px;
		min-width:0;
	}
	.fa-prevbox-card-top{
		display:flex;
		align-items:flex-start;
		justify-content:space-between;
		gap:8px;
	}
	.fa-prevbox-card-title{
		margin:0;
		font-size:13px;
		font-weight:800;
		line-height:1.5;
		color:#111827;
	}
	.fa-prevbox-card-meta{
		display:flex;
		flex-direction:column;
		gap:4px;
		font-size:11px;
		color:#6b7280;
		line-height:1.7;
	}
	.fa-prevbox-card-items{
		font-size:11px;
		line-height:1.9;
		color:#374151;
		background:#f9fafb;
		border:1px solid #f1f5f9;
		border-radius:10px;
		padding:7px 8px;
		cursor:help;
	}
	.fa-prevbox-card-items strong{
		color:#111827;
		font-weight:700;
	}
	.fa-prevbox-card-actions{
		display:flex;
		align-items:center;
		justify-content:space-between;
		gap:8px;
		margin-top:2px;
	}
	.fa-prevbox-view-link{
		display:inline-flex;
		align-items:center;
		justify-content:center;
		height:30px;
		padding:0 10px;
		border-radius:9px;
		background:#6d28d9;
		border:1px solid #6d28d9;
		color:#fff !important;
		text-decoration:none;
		font-size:11px;
		font-weight:700;
	}
	.fa-prevbox-view-link:hover{
		background:#5b21b6;
		border-color:#5b21b6;
		color:#fff !important;
	}
	.fa-prevbox-icon-btn{
		width:30px;
		height:30px;
		border:none;
		border-radius:9px;
		background:#f3f4f6;
		color:#374151;
		cursor:pointer;
		display:inline-flex;
		align-items:center;
		justify-content:center;
		padding:0;
		flex:0 0 auto;
	}
	.fa-prevbox-icon-btn:hover{
		background:#e5e7eb;
		color:#111827;
	}
	.fa-prevbox-icon-btn .dashicons{
		font-size:15px;
		width:15px;
		height:15px;
	}
	.fa-prevbox-copy-ok{
		background:#ecfdf5 !important;
		color:#047857 !important;
	}
	@media (max-width:782px){
		.fa-prevbox-summary,
		.fa-prevbox-summary.fa-prevbox-summary--four{
			grid-template-columns:1fr;
		}
		.fa-prevbox-list{
			grid-template-columns:1fr;
		}
	}';

	wp_add_inline_style( 'fa-prevbox-inline-style', $css );

	wp_register_script( 'fa-prevbox-inline-script', false, array( 'jquery' ), '1.0.0', true );
	wp_enqueue_script( 'fa-prevbox-inline-script' );

	wp_localize_script(
		'fa-prevbox-inline-script',
		'faPrevboxData',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fa_prevbox_nonce' ),
		)
	);

	$js = <<<'JS'
jQuery(function($){
	var modal = null;

	function ensureModal(){
		if ($('#fa-prevbox-modal').length) {
			modal = $('#fa-prevbox-modal');
			return modal;
		}

		var html = '' +
		'<div id="fa-prevbox-modal" class="fa-prevbox-modal" aria-hidden="true">' +
			'<div class="fa-prevbox-modal-dialog" role="dialog" aria-modal="true" aria-label="سفارشات قبلی مشتری">' +
				'<div class="fa-prevbox-modal-header">' +
					'<div>' +
						'<h3 class="fa-prevbox-modal-title">سفارشات قبلی مشتری</h3>' +
						'<p class="fa-prevbox-modal-subtitle">در حال بارگذاری...</p>' +
					'</div>' +
					'<button type="button" class="fa-prevbox-modal-close" aria-label="بستن">×</button>' +
				'</div>' +
				'<div class="fa-prevbox-modal-body"><div class="fa-prevbox-loader">در حال دریافت اطلاعات...</div></div>' +
			'</div>' +
		'</div>';

		$('body').append(html);
		modal = $('#fa-prevbox-modal');
		return modal;
	}

	function openModal(){
		ensureModal().addClass('is-open').attr('aria-hidden', 'false');
		$('body').css('overflow', 'hidden');
	}

	function closeModal(){
		ensureModal().removeClass('is-open').attr('aria-hidden', 'true');
		$('body').css('overflow', '');
	}

	function setLoading(orderNumber){
		var m = ensureModal();
		m.find('.fa-prevbox-modal-title').text('سفارشات قبلی مشتری');
		m.find('.fa-prevbox-modal-subtitle').text('سفارش جاری: #' + orderNumber);
		m.find('.fa-prevbox-modal-body').scrollTop(0).html('<div class="fa-prevbox-loader">در حال دریافت اطلاعات...</div>');
	}

	function copyText(text, button){
		if (!text) return;

		function markDone(){
			var btn = $(button);
			btn.addClass('fa-prevbox-copy-ok');
			setTimeout(function(){
				btn.removeClass('fa-prevbox-copy-ok');
			}, 1000);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(markDone);
		 } else {
			var temp = $('<input>');
			$('body').append(temp);
			temp.val(text).select();
			document.execCommand('copy');
			temp.remove();
			markDone();
		}
	}

	function loadBatchStats(){
		var buttons = $('.fa-prevbox-open-modal[data-user-id]');
		if (!buttons.length) return;

		var userIds = [];

		buttons.each(function(){
			var uid = parseInt($(this).attr('data-user-id'), 10);
			if (uid && $.inArray(uid, userIds) === -1) {
				userIds.push(uid);
			}
		});

		if (!userIds.length) return;

		$.ajax({
			url: faPrevboxData.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'fa_prevbox_batch_stats',
				nonce: faPrevboxData.nonce,
				user_ids: userIds
			}
		}).done(function(response){
			if (!(response && response.success && response.data && response.data.stats)) {
				return;
			}

			var stats = response.data.stats;

			buttons.each(function(){
				var btn = $(this);
				var uid = String(btn.attr('data-user-id'));

				if (!stats[uid]) return;

				var item = stats[uid];
				var count = parseInt(item.previous_count, 10) || 0;

				btn.removeClass('is-loading');

				if (count > 0) {
					btn.prop('disabled', false)
						.removeClass('is-disabled')
						.text('سفارشات قبلی: ' + String(count));
				} else {
					btn.prop('disabled', true)
						.addClass('is-disabled')
						.removeClass('fa-prevbox-open-modal')
						.text('بدون سفارش قبلی');
				}

				if (item.tooltip) {
					btn.attr('data-tooltip', item.tooltip);
				}
			});
		});
	}

	$(document).on('click', '.fa-prevbox-icon-btn', function(e){
		e.preventDefault();
		copyText($(this).data('copy-text'), this);
	});

	$(document).on('click', '.fa-prevbox-open-modal', function(e){
		e.preventDefault();

		var btn         = $(this);
		if (btn.prop('disabled')) return;

		var userId      = btn.data('user-id');
		var orderId     = btn.data('order-id');
		var orderNumber = btn.data('order-number') || orderId;

		setLoading(orderNumber);
		openModal();

		$.ajax({
			url: faPrevboxData.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'fa_prevbox_get_modal',
				nonce: faPrevboxData.nonce,
				user_id: userId,
				order_id: orderId
			}
		}).done(function(response){
			var m = ensureModal();

			if (response && response.success && response.data) {
				m.find('.fa-prevbox-modal-body').html(response.data.html).scrollTop(0);
				m.find('.fa-prevbox-modal-subtitle').text(response.data.subtitle);
			} else {
				m.find('.fa-prevbox-modal-body').html('<div class="fa-prevbox-empty">اطلاعاتی پیدا نشد.</div>').scrollTop(0);
			}
		}).fail(function(){
			var m = ensureModal();
			m.find('.fa-prevbox-modal-body').html('<div class="fa-prevbox-empty">خطا در دریافت اطلاعات. دوباره تلاش کنید.</div>').scrollTop(0);
		});
	});

	$(document).on('click', '.fa-prevbox-modal-close', function(){
		closeModal();
	});

	$(document).on('click', '#fa-prevbox-modal', function(e){
		if ($(e.target).is('#fa-prevbox-modal')) {
			closeModal();
		}
	});

	$(document).on('keydown', function(e){
		if (e.key === 'Escape' && $('#fa-prevbox-modal').hasClass('is-open')) {
			closeModal();
		}
	});

	loadBatchStats();
});
JS;

	wp_add_inline_script( 'fa-prevbox-inline-script', $js );
}
add_action( 'admin_enqueue_scripts', 'fa_prevbox_admin_assets', 20 );
