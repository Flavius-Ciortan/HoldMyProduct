<?php
if ( ! defined( 'HOLD_THIS_PRODUCT_INTEGRATION_TEST' ) ) {
	exit;
}

require '/wordpress/wp-load.php';

$htp_failures = array();
function htp_assert( $condition, $message ) {
	global $htp_failures;
	if ( ! $condition ) {
		$htp_failures[] = $message;
		echo esc_html( "FAIL: {$message}\n" );
	} else {
		echo esc_html( "PASS: {$message}\n" );
	}
}

$htp_plugin = HoldThisProduct::get_instance();
htp_assert( $htp_plugin->reservations instanceof Hold_This_Product_Reservations, 'Reservation service initialized.' );

require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/admin/class-htp-admin-reservations.php';
require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/admin/class-htp-admin.php';
$htp_admin = new Hold_This_Product_Admin( $htp_plugin->reservations );
$htp_sanitized = $htp_admin->sanitize_options( array( 'max_reservations' => 999, 'reservation_duration' => -2, 'popup_customization_logged_in' => array( 'font_family' => 'Arial;background:url(x)', 'background_color' => 'bad' ) ) );
htp_assert( 100 === $htp_sanitized['max_reservations'], 'Reservation limit is bounded.' );
htp_assert( 1 === $htp_sanitized['reservation_duration'], 'Duration is bounded.' );
htp_assert( 'Arial, Helvetica, sans-serif' === $htp_sanitized['popup_customization_logged_in']['font_family'], 'Font value is allowlisted.' );

update_option( 'holdthisproduct_options', array( 'enable_reservation' => 1, 'max_reservations' => 3, 'reservation_duration' => 24, 'pending_duration' => 1, 'require_admin_approval' => 1, 'enable_email_notifications' => 0 ) );
$htp_user_id = wp_insert_user( array( 'user_login' => 'htp-test-user', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'htp@example.test', 'role' => 'customer' ) );
wp_set_current_user( $htp_user_id );
$htp_product = new WC_Product_Simple();
$htp_product->set_name( 'Reservation test product' );
$htp_product->set_status( 'publish' );
$htp_product->set_regular_price( '10' );
$htp_product->set_manage_stock( true );
$htp_product->set_stock_quantity( 1 );
$htp_product_id = $htp_product->save();

// Existing development-build identifiers migrate without losing reservation data.
$htp_legacy_reservation = wp_insert_post( array( 'post_type' => 'htp_reservation', 'post_status' => 'publish', 'post_author' => $htp_user_id, 'post_title' => 'Legacy reservation' ) );
update_post_meta( $htp_legacy_reservation, '_htp_product_id', $htp_product_id );
update_post_meta( $htp_legacy_reservation, '_htp_status', 'cancelled' );
$htp_migration = new ReflectionMethod( $htp_plugin, 'migrate_legacy_identifiers' );
$htp_migration->setAccessible( true );
$htp_migration->invoke( $htp_plugin );
htp_assert( 'holdthisproduct_res' === get_post_type( $htp_legacy_reservation ), 'Legacy reservation post type migrates.' );
htp_assert( $htp_product_id === (int) get_post_meta( $htp_legacy_reservation, '_hold_this_product_product_id', true ), 'Legacy reservation metadata migrates.' );
htp_assert( ! metadata_exists( 'post', $htp_legacy_reservation, '_htp_product_id' ), 'Legacy metadata key is removed after migration.' );

function htp_test_reservation( $product_id, $user_id, $status, $expires ) {
	$id = wp_insert_post( array( 'post_type' => 'holdthisproduct_res', 'post_status' => 'publish', 'post_author' => $user_id, 'post_title' => 'Test reservation' ) );
	update_post_meta( $id, '_hold_this_product_product_id', $product_id );
	update_post_meta( $id, '_hold_this_product_status', $status );
	update_post_meta( $id, '_hold_this_product_expires_at', $expires );
	update_post_meta( $id, '_hold_this_product_qty', 1 );
	update_post_meta( $id, '_hold_this_product_email', 'htp@example.test' );
	return $id;
}

$htp_pending_id = htp_test_reservation( $htp_product_id, $htp_user_id, 'pending_approval', time() + HOUR_IN_SECONDS );
htp_assert( true === $htp_plugin->reservations->approve_reservation( $htp_pending_id ), 'Pending reservation approves.' );
$htp_product = wc_get_product( $htp_product_id );
htp_assert( 0 === (int) $htp_product->get_stock_quantity( 'edit' ), 'Approval holds physical stock once.' );
htp_assert( 1 === (int) $htp_product->get_stock_quantity(), 'Owner can purchase the held last unit.' );
htp_assert( is_wp_error( $htp_plugin->reservations->approve_reservation( $htp_pending_id ) ), 'Repeated approval is rejected.' );
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_pending_id ), 'Active reservation cancels.' );
htp_assert( false === $htp_plugin->reservations->cancel_reservation( $htp_pending_id ), 'Repeated cancellation is rejected.' );
$htp_product = wc_get_product( $htp_product_id );
htp_assert( 1 === (int) $htp_product->get_stock_quantity( 'edit' ), 'Cancellation restores stock exactly once.' );

$htp_expired_pending = htp_test_reservation( $htp_product_id, $htp_user_id, 'pending_approval', time() - 1 );
$htp_plugin->reservations->expire_old_reservations();
htp_assert( 'expired' === get_post_meta( $htp_expired_pending, '_hold_this_product_status', true ), 'Pending requests expire.' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Pending expiry does not change stock.' );

// The customer may add to cart before creating the reservation.
wc_load_cart();
WC()->cart->empty_cart();
$htp_preexisting_cart_key = WC()->cart->add_to_cart( $htp_product_id, 1 );
$htp_cart_first_reservation = htp_test_reservation( $htp_product_id, $htp_user_id, 'active', time() + HOUR_IN_SECONDS );
wc_update_product_stock( wc_get_product( $htp_product_id ), 1, 'decrease' );
$htp_plugin->reservations->sync_owned_reservations_to_cart();
$htp_synced_cart = WC()->cart->get_cart();
htp_assert(
	isset( $htp_synced_cart[ $htp_preexisting_cart_key ]['_hold_this_product_reservation_id'] )
	&& $htp_cart_first_reservation === (int) $htp_synced_cart[ $htp_preexisting_cart_key ]['_hold_this_product_reservation_id'],
	'Cart item added before reservation is linked to the active hold.'
);
$htp_cart_stock_valid = true;
try {
	WC()->cart->check_cart_items();
} catch ( Throwable $htp_cart_error ) {
	$htp_cart_stock_valid = false;
}
htp_assert( $htp_cart_stock_valid && 0 === wc_notice_count( 'error' ), 'Cart added before reservation remains valid for checkout.' );
wc_clear_notices();
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_cart_first_reservation ), 'Cart-first test reservation cancels.' );
WC()->cart->empty_cart();

$htp_active_id = htp_test_reservation( $htp_product_id, $htp_user_id, 'active', time() + HOUR_IN_SECONDS );
wc_update_product_stock( wc_get_product( $htp_product_id ), 1, 'decrease' );
$htp_order = wc_create_order( array( 'customer_id' => $htp_user_id ) );
$htp_item_id = $htp_order->add_product( wc_get_product( $htp_product_id ), 1 );
$htp_item = $htp_order->get_item( $htp_item_id );
$htp_item->add_meta_data( '_hold_this_product_reservation_id', $htp_active_id, true );
$htp_item->save();
$htp_order->save();
$htp_reserve_stock_succeeded = true;
$htp_reserve_stock_message = '';
try {
	wc_reserve_stock_for_order( $htp_order );
} catch ( Throwable $htp_stock_error ) {
	$htp_reserve_stock_succeeded = false;
	$htp_reserve_stock_message = $htp_stock_error->getMessage();
}
htp_assert( $htp_reserve_stock_succeeded, 'WooCommerce checkout can reserve stock when the last unit is already held. ' . $htp_reserve_stock_message );
do_action( 'woocommerce_store_api_checkout_order_processed', $htp_order );
htp_assert( 'fulfilled' === get_post_meta( $htp_active_id, '_hold_this_product_status', true ), 'Order fulfills the exact linked reservation.' );
htp_assert( 0 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Checkout does not decrement the held unit twice.' );
$htp_order->update_status( 'cancelled' );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Cancelled order restores stock exactly once.' );
$htp_plugin->reservations->restore_transferred_order_stock( $htp_order->get_id() );
htp_assert( 1 === (int) wc_get_product( $htp_product_id )->get_stock_quantity( 'edit' ), 'Repeated order restoration is idempotent.' );

// Erasing a shrinking result set must not skip the second batch.
$htp_privacy_email = 'privacy-batch@example.test';
$htp_privacy_ids = array();
for ( $htp_i = 0; $htp_i < 101; $htp_i++ ) {
	$htp_privacy_id = htp_test_reservation( $htp_product_id, 0, 'cancelled', time() - 1 );
	update_post_meta( $htp_privacy_id, '_hold_this_product_email', $htp_privacy_email );
	$htp_privacy_ids[] = $htp_privacy_id;
}
$htp_erase_first = $htp_plugin->reservations->erase_personal_data( $htp_privacy_email, 1 );
$htp_erase_second = $htp_plugin->reservations->erase_personal_data( $htp_privacy_email, 2 );
htp_assert( false === $htp_erase_first['done'] && true === $htp_erase_second['done'], 'Privacy eraser completes a shrinking result set without skipping a batch.' );
$htp_remaining_private = get_posts( array( 'post_type' => 'holdthisproduct_res', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_hold_this_product_email', 'meta_value' => $htp_privacy_email ) );
htp_assert( empty( $htp_remaining_private ), 'Privacy eraser leaves no matching eligible email records.' );

// Invalid/no-result searches must still return a WP_Query-compatible object.
$hold_this_product_admin_reservations = new Hold_This_Product_Admin_Reservations( $htp_plugin->reservations );
$htp_search_method = new ReflectionMethod( $hold_this_product_admin_reservations, 'get_filtered_reservations' );
$htp_search_method->setAccessible( true );
$htp_invalid_search = $htp_search_method->invoke( $hold_this_product_admin_reservations, 'all', 'not-a-number', 'product_id', 1 );
$htp_missing_product = $htp_search_method->invoke( $hold_this_product_admin_reservations, 'all', 'no-such-product-htp-test', 'product', 1 );
htp_assert( $htp_invalid_search instanceof WP_Query && $htp_missing_product instanceof WP_Query, 'Invalid and no-result product searches preserve the WP_Query return contract.' );

// Reservation posts must survive customer deletion so held inventory is not stranded.
$htp_delete_user_id = wp_insert_user( array( 'user_login' => 'htp-delete-user', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'delete@example.test', 'role' => 'customer' ) );
$htp_delete_reservation = htp_test_reservation( $htp_product_id, $htp_delete_user_id, 'active', time() + HOUR_IN_SECONDS );
wc_update_product_stock( wc_get_product( $htp_product_id ), 1, 'decrease' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $htp_delete_user_id );
htp_assert( 'holdthisproduct_res' === get_post_type( $htp_delete_reservation ), 'Deleting a customer does not delete their reservation record.' );
htp_assert( true === $htp_plugin->reservations->cancel_reservation( $htp_delete_reservation ), 'Preserved reservation can release its held stock.' );

wp_delete_post( $htp_pending_id, true );
wp_delete_post( $htp_expired_pending, true );
wp_delete_post( $htp_active_id, true );
wp_delete_post( $htp_legacy_reservation, true );
foreach ( $htp_privacy_ids as $htp_privacy_id ) wp_delete_post( $htp_privacy_id, true );
wp_delete_post( $htp_delete_reservation, true );
wp_delete_post( $htp_product_id, true );
wp_delete_user( $htp_user_id );
if ( $htp_failures ) exit( 1 );
echo esc_html( "All integration assertions passed.\n" );
