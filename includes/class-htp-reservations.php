<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core reservations functionality
 */
class HTP_Reservations {

    const POST_TYPE = 'htp_reservation';
    const QUERY_VAR = 'htp-reservations';
    const CRON_HOOK = 'holdthisproduct_expire_reservations';
    const CACHE_GROUP = 'holdthisproduct';
    const STATUS_META_KEY = '_htp_status';
    const PRODUCT_META_KEY = '_htp_product_id';
    const EXPIRES_META_KEY = '_htp_expires_at';
    const EMAIL_META_KEY = '_htp_email';
    const QTY_META_KEY = '_htp_qty';
    const STOCK_HELD_META_KEY = '_htp_stock_held';
    const DENIAL_REASON_META_KEY = '_htp_denial_reason';
    const LAST_EXPIRATION_RUN_OPTION = 'htp_last_expiration_run';

    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_DENIED = 'denied';

    /**
     * Singleton instance.
     *
     * @var HTP_Reservations|null
     */
    private static $instance = null;

    /**
     * Whether hooks were already registered.
     *
     * @var bool
     */
    private static $hooks_registered = false;

    /**
     * Get the shared reservations service.
     *
     * @return HTP_Reservations
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize hooks.
     */
    private function init() {
        if ( self::$hooks_registered ) {
            return;
        }

        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_endpoints' ) );
        add_action( 'init', array( $this, 'ensure_scheduled_events' ) );
        add_action( 'init', array( $this, 'maybe_process_expirations_fallback' ) );

        add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
        add_action( self::CRON_HOOK, array( $this, 'process_expired_reservations' ) );

        // WooCommerce account integration.
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
        add_action( 'woocommerce_account_htp-reservations_endpoint', array( $this, 'reservations_endpoint_content' ) );
        add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_reservation_actions' ) );
        add_filter( 'woocommerce_endpoint_htp-reservations_title', array( $this, 'reservations_endpoint_title' ) );
        add_filter( 'woocommerce_page_title', array( $this, 'change_reservations_page_title' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_holdthisproduct_reserve', array( $this, 'handle_reservation_ajax' ) );
        add_action( 'wp_ajax_nopriv_holdthisproduct_reserve', array( $this, 'handle_reservation_ajax' ) );

        // Auto-fulfill reservations on purchase.
        add_action( 'woocommerce_order_status_completed', array( $this, 'fulfill_reservation_on_purchase' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'fulfill_reservation_on_purchase' ) );

        self::$hooks_registered = true;
    }

    /**
     * Register custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public function register_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['holdthisproduct_five_minutes'] ) ) {
            $schedules['holdthisproduct_five_minutes'] = array(
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every 5 minutes', 'hold-this-product' ),
            );
        }

        return $schedules;
    }

    /**
     * Schedule expiration processing.
     */
    public static function schedule_events() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'holdthisproduct_five_minutes', self::CRON_HOOK );
        }
    }

    /**
     * Ensure the scheduled expiration event exists.
     */
    public function ensure_scheduled_events() {
        self::schedule_events();
    }

    /**
     * Clear scheduled expiration processing.
     */
    public static function clear_scheduled_events() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
        }
    }

    /**
     * Register custom post type for reservations.
     */
    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels'          => array( 'name' => 'Reservations' ),
            'public'          => false,
            'show_ui'         => false,
            'supports'        => array( 'title', 'author' ),
            'capability_type' => 'post',
        ) );
    }

    /**
     * Register WooCommerce endpoints.
     */
    public function register_endpoints() {
        add_rewrite_endpoint( self::QUERY_VAR, EP_ROOT | EP_PAGES );
    }

    /**
     * Add query vars for WooCommerce.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[ self::QUERY_VAR ] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Flush rewrite rules.
     */
    public function flush_rewrite_rules() {
        $this->register_endpoints();
        self::schedule_events();
        flush_rewrite_rules();
    }

    /**
     * Process expired reservations via cron.
     *
     * @param int $limit Maximum number of reservations to process in one run.
     * @return int
     */
    public function process_expired_reservations( $limit = 100 ) {
        $expired = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => max( 1, (int) $limit ),
            'meta_query'     => array(
                array( 'key' => self::STATUS_META_KEY, 'value' => self::STATUS_ACTIVE ),
                array( 'key' => self::EXPIRES_META_KEY, 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '<' ),
            ),
        ) );

        foreach ( $expired as $reservation_id ) {
            $this->expire_reservation( $reservation_id );
        }

        update_option( self::LAST_EXPIRATION_RUN_OPTION, current_time( 'timestamp' ), false );

        return count( $expired );
    }

    /**
     * Fallback expiration processing for environments where WP-Cron is delayed.
     */
    public function maybe_process_expirations_fallback() {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        if ( wp_cache_get( 'htp_expiration_fallback_running', self::CACHE_GROUP ) ) {
            return;
        }

        $last_run = (int) get_option( self::LAST_EXPIRATION_RUN_OPTION, 0 );
        $now      = current_time( 'timestamp' );

        if ( $last_run > 0 && ( $now - $last_run ) < ( 10 * MINUTE_IN_SECONDS ) ) {
            return;
        }

        wp_cache_set( 'htp_expiration_fallback_running', true, self::CACHE_GROUP, MINUTE_IN_SECONDS );
        $this->process_expired_reservations( 25 );
    }

    /**
     * Handle reservation AJAX request.
     */
    public function handle_reservation_ajax() {
        check_ajax_referer( 'holdthisproduct_nonce', 'security' );

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Invalid product ID.' );
        }

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to reserve products.' );
        }

        $user_id = get_current_user_id();

        if ( ! $this->is_product_reservable( $product_id ) ) {
            wp_send_json_error( 'Reservations are disabled for this product.' );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->managing_stock() ) {
            wp_send_json_error( 'Product stock is not managed.' );
        }

        if ( $this->get_available_stock( $product ) <= 0 ) {
            wp_send_json_error( 'No stock available.' );
        }

        $options          = get_option( 'holdthisproduct_options' );
        $require_approval = ! empty( $options['require_admin_approval'] );
        $limit            = $this->get_max_reservations_per_user();
        $active           = $require_approval ? $this->count_open_reservations( $user_id ) : $this->count_active_reservations( $user_id );

        if ( $active >= $limit ) {
            wp_send_json_error(
                sprintf(
                    $require_approval
                        ? 'You have reached the maximum of %d open reservations (pending + active).'
                        : 'You have reached the maximum of %d active reservations.',
                    $limit
                )
            );
        }

        $has_open = $require_approval
            ? $this->user_has_open_reservation_for_product( $product_id, $user_id )
            : $this->user_has_active_reservation_for_product( $product_id, $user_id );

        if ( $has_open ) {
            wp_send_json_error( $require_approval ? 'You already have a pending or active reservation request for this product.' : 'You already have an active reservation for this product.' );
        }

        $reservation_id = $this->create_reservation( $product_id, $user_id );
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Could not create reservation.' );
        }

        if ( ! $require_approval ) {
            $activation_result = $this->activate_reservation( $reservation_id );
            if ( is_wp_error( $activation_result ) ) {
                wp_delete_post( $reservation_id, true );
                wp_send_json_error( $activation_result->get_error_message() );
            }
        }

        wp_send_json_success( $require_approval ? 'Reservation request submitted for approval.' : 'Reservation created successfully.' );
    }

    /**
     * Create a new reservation.
     *
     * Pending reservations receive an initial expiry timestamp, but that window is reset on approval.
     *
     * @param int    $product_id Product ID.
     * @param int    $user_id User ID.
     * @param string $guest_email Guest email.
     * @return int|false
     */
    public function create_reservation( $product_id, $user_id = 0, $guest_email = '' ) {
        $options        = get_option( 'holdthisproduct_options' );
        $duration_hours = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
        $expires_at     = current_time( 'timestamp' ) + ( max( 1, $duration_hours ) * HOUR_IN_SECONDS );
        $require_approval = ! empty( $options['require_admin_approval'] );
        $initial_status   = $require_approval ? self::STATUS_PENDING_APPROVAL : self::STATUS_ACTIVE;

        $reservation_id = wp_insert_post( array(
            'post_type'   => self::POST_TYPE,
            'post_title'  => 'Reservation for product ' . absint( $product_id ),
            'post_status' => 'publish',
            'post_author' => $user_id ?: 0,
        ) );

        if ( is_wp_error( $reservation_id ) ) {
            return false;
        }

        $meta_data = array(
            self::PRODUCT_META_KEY => absint( $product_id ),
            self::STATUS_META_KEY  => $initial_status,
            self::EXPIRES_META_KEY => $expires_at,
            self::QTY_META_KEY     => 1,
            self::STOCK_HELD_META_KEY => 0,
        );

        $notification_email = '';
        if ( $user_id ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $notification_email            = $user->user_email;
                $meta_data[ self::EMAIL_META_KEY ] = $notification_email;
            }
        } elseif ( $guest_email !== '' ) {
            $notification_email            = sanitize_email( $guest_email );
            $meta_data[ self::EMAIL_META_KEY ] = $notification_email;
        }

        foreach ( $meta_data as $key => $value ) {
            update_post_meta( $reservation_id, $key, $value );
        }

        if ( $notification_email ) {
            if ( $require_approval ) {
                do_action( 'htp_reservation_pending_approval', $reservation_id, $notification_email );
            } else {
                do_action( 'htp_reservation_created', $reservation_id, $notification_email );
            }
        }

        return $reservation_id;
    }

    /**
     * Check if product is reservable.
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public function is_product_reservable( $product_id ) {
        if ( ! $this->are_reservations_globally_enabled() ) {
            return false;
        }

        if ( ! is_user_logged_in() ) {
            return false;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->managing_stock() ) {
            return false;
        }

        return true;
    }

    /**
     * Check if reservations are globally enabled.
     *
     * @return bool
     */
    public function are_reservations_globally_enabled() {
        $options = get_option( 'holdthisproduct_options' );
        return ! empty( $options['enable_reservation'] );
    }

    /**
     * Get max reservations per user.
     *
     * @return int
     */
    public function get_max_reservations_per_user() {
        $options = get_option( 'holdthisproduct_options' );
        return max( 1, absint( $options['max_reservations'] ?? 1 ) );
    }

    /**
     * Count active reservations for a user.
     *
     * @param int    $user_id User ID.
     * @param string $email Email.
     * @return int
     */
    public function count_active_reservations( $user_id = 0, $email = '' ) {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array( 'key' => self::STATUS_META_KEY, 'value' => self::STATUS_ACTIVE ),
                array( 'key' => self::EXPIRES_META_KEY, 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' ),
            ),
        );

        if ( $user_id > 0 ) {
            $args['author'] = $user_id;
        } elseif ( $email !== '' ) {
            $args['meta_query'][] = array( 'key' => self::EMAIL_META_KEY, 'value' => $email );
        } else {
            return 0;
        }

        return count( get_posts( $args ) );
    }

    /**
     * Check if user has an active reservation for a product.
     *
     * @param int    $product_id Product ID.
     * @param int    $user_id User ID.
     * @param string $email Email.
     * @return bool
     */
    public function user_has_active_reservation_for_product( $product_id, $user_id = 0, $email = '' ) {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array( 'key' => self::STATUS_META_KEY, 'value' => self::STATUS_ACTIVE ),
                array( 'key' => self::PRODUCT_META_KEY, 'value' => absint( $product_id ) ),
                array( 'key' => self::EXPIRES_META_KEY, 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' ),
            ),
        );

        if ( $user_id > 0 ) {
            $args['author'] = $user_id;
        } elseif ( $email !== '' ) {
            $args['meta_query'][] = array( 'key' => self::EMAIL_META_KEY, 'value' => $email );
        } else {
            return false;
        }

        return ! empty( get_posts( $args ) );
    }

    /**
     * Expire a single reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return bool
     */
    public function expire_reservation( $reservation_id ) {
        $reservation_id   = absint( $reservation_id );
        $previous_status  = $this->get_reservation_status( $reservation_id );

        if ( self::STATUS_ACTIVE !== $previous_status || ! $this->is_stock_held_for_reservation( $reservation_id ) ) {
            return false;
        }

        $this->set_reservation_status( $reservation_id, self::STATUS_EXPIRED );
        $this->release_stock_for_reservation( $reservation_id );

        $email = get_post_meta( $reservation_id, self::EMAIL_META_KEY, true );
        if ( $email ) {
            do_action( 'htp_reservation_expired', $reservation_id, $email );
        }

        return true;
    }

    /**
     * Cancel a reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return bool
     */
    public function cancel_reservation( $reservation_id ) {
        $reservation_id  = absint( $reservation_id );
        $current_status  = $this->get_reservation_status( $reservation_id );

        if ( ! in_array( $current_status, array( self::STATUS_ACTIVE, self::STATUS_PENDING_APPROVAL ), true ) ) {
            return false;
        }

        $this->set_reservation_status( $reservation_id, self::STATUS_CANCELLED );

        if ( self::STATUS_ACTIVE === $current_status && $this->is_stock_held_for_reservation( $reservation_id ) ) {
            $this->release_stock_for_reservation( $reservation_id );
        }

        return true;
    }

    /**
     * Add reservations to WooCommerce account menu.
     *
     * @param array $items Menu items.
     * @return array
     */
    public function add_account_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            if ( 'customer-logout' === $key ) {
                $new[ self::QUERY_VAR ] = __( 'Reserved products', 'hold-this-product' );
            }
            $new[ $key ] = $label;
        }
        if ( ! isset( $new[ self::QUERY_VAR ] ) ) {
            $new[ self::QUERY_VAR ] = __( 'Reserved products', 'hold-this-product' );
        }
        return $new;
    }

    /**
     * Change endpoint title for reservations.
     *
     * @param string $title Current title.
     * @return string
     */
    public function reservations_endpoint_title( $title ) {
        return __( 'Reserved products', 'hold-this-product' );
    }

    /**
     * Change page title on reservations page.
     *
     * @param string $title Current title.
     * @return string
     */
    public function change_reservations_page_title( $title ) {
        global $wp_query;

        if ( ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
            if ( isset( $wp_query->query_vars[ self::QUERY_VAR ] ) || ( function_exists( 'wc_get_page_id' ) && is_wc_endpoint_url( self::QUERY_VAR ) ) ) {
                return __( 'Reservations', 'hold-this-product' );
            }
        }

        return $title;
    }

    /**
     * Display reservations in My Account.
     */
    public function reservations_endpoint_content() {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;

        if ( ! is_user_logged_in() ) {
            wc_print_notice( __( 'Please log in to see your reservations.', 'hold-this-product' ), 'notice' );
            return;
        }

        $reservations = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'author'         => get_current_user_id(),
            'posts_per_page' => 50,
            'meta_query'     => array(
                array(
                    'key'     => self::STATUS_META_KEY,
                    'compare' => 'EXISTS',
                ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        ) );

        if ( empty( $reservations ) ) {
            wc_print_notice( __( 'You have no reservations.', 'hold-this-product' ), 'notice' );
            return;
        }

        wc_get_template( 'myaccount/my-reservations.php', array(
            'reservations' => $reservations,
        ), '', HTP_PLUGIN_PATH . 'templates/' );
    }

    /**
     * Handle reservation actions from My Account.
     */
    public function handle_reservation_actions() {
        if ( ! is_user_logged_in() || ! isset( $_GET['htp_cancel_res'] ) ) {
            return;
        }

        $reservation_id = absint( $_GET['htp_cancel_res'] );
        if ( ! $reservation_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'htp_cancel_res_' . $reservation_id ) ) {
            return;
        }

        $post = get_post( $reservation_id );
        if ( ! $post || (int) $post->post_author !== get_current_user_id() ) {
            return;
        }

        $this->cancel_reservation( $reservation_id );
        wp_safe_redirect( wc_get_account_endpoint_url( self::QUERY_VAR ) );
        exit;
    }

    /**
     * Auto-fulfill reservations when order is completed.
     *
     * @param int $order_id Order ID.
     */
    public function fulfill_reservation_on_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $customer_email = $order->get_billing_email();

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();

            $reservations = get_posts( array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array( 'key' => self::STATUS_META_KEY, 'value' => self::STATUS_ACTIVE ),
                    array( 'key' => self::PRODUCT_META_KEY, 'value' => $product_id ),
                    array( 'key' => self::EMAIL_META_KEY, 'value' => $customer_email ),
                ),
            ) );

            if ( ! empty( $reservations ) ) {
                $this->fulfill_reservation( $reservations[0]->ID );
            }
        }
    }

    /**
     * Approve a pending reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return true|WP_Error
     */
    public function approve_reservation( $reservation_id ) {
        if ( self::STATUS_PENDING_APPROVAL !== $this->get_reservation_status( $reservation_id ) ) {
            return new WP_Error( 'htp_not_pending', 'Reservation is not pending approval.' );
        }

        return $this->activate_reservation( $reservation_id );
    }

    /**
     * Deny a pending reservation.
     *
     * @param int    $reservation_id Reservation ID.
     * @param string $reason Optional denial reason.
     * @return bool
     */
    public function deny_reservation( $reservation_id, $reason = '' ) {
        if ( self::STATUS_PENDING_APPROVAL !== $this->get_reservation_status( $reservation_id ) ) {
            return false;
        }

        $this->set_reservation_status( $reservation_id, self::STATUS_DENIED );

        if ( $reason ) {
            update_post_meta( $reservation_id, self::DENIAL_REASON_META_KEY, sanitize_text_field( $reason ) );
        }

        $email = get_post_meta( $reservation_id, self::EMAIL_META_KEY, true );
        if ( $email ) {
            do_action( 'htp_reservation_denied', $reservation_id, $email, $reason );
        }

        return true;
    }

    /**
     * Count "open" reservations for a user (active + pending approval).
     *
     * @param int $user_id User ID.
     * @return int
     */
    public function count_open_reservations( $user_id = 0 ) {
        if ( $user_id <= 0 ) {
            return 0;
        }

        $ids = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'author'         => $user_id,
            'meta_query'     => array(
                array( 'key' => self::STATUS_META_KEY, 'value' => array( self::STATUS_ACTIVE, self::STATUS_PENDING_APPROVAL ), 'compare' => 'IN' ),
            ),
        ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $now   = current_time( 'timestamp' );
        $count = 0;
        foreach ( $ids as $reservation_id ) {
            $status = $this->get_reservation_status( $reservation_id );
            if ( self::STATUS_PENDING_APPROVAL === $status ) {
                $count++;
                continue;
            }

            $expires = (int) get_post_meta( $reservation_id, self::EXPIRES_META_KEY, true );
            if ( $expires > $now ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Check if a user already has an open reservation request for a product.
     *
     * @param int $product_id Product ID.
     * @param int $user_id User ID.
     * @return bool
     */
    public function user_has_open_reservation_for_product( $product_id, $user_id = 0 ) {
        $product_id = absint( $product_id );
        $user_id    = absint( $user_id );
        if ( ! $product_id || ! $user_id ) {
            return false;
        }

        $ids = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 10,
            'author'         => $user_id,
            'meta_query'     => array(
                array( 'key' => self::PRODUCT_META_KEY, 'value' => $product_id ),
                array( 'key' => self::STATUS_META_KEY, 'value' => array( self::STATUS_ACTIVE, self::STATUS_PENDING_APPROVAL ), 'compare' => 'IN' ),
            ),
        ) );

        if ( empty( $ids ) ) {
            return false;
        }

        $now = current_time( 'timestamp' );
        foreach ( $ids as $reservation_id ) {
            $status = $this->get_reservation_status( $reservation_id );
            if ( self::STATUS_PENDING_APPROVAL === $status ) {
                return true;
            }

            $expires = (int) get_post_meta( $reservation_id, self::EXPIRES_META_KEY, true );
            if ( $expires > $now ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Activate a reservation by holding stock and resetting the expiry window.
     *
     * @param int $reservation_id Reservation ID.
     * @return true|WP_Error
     */
    private function activate_reservation( $reservation_id ) {
        $reservation_id = absint( $reservation_id );
        $status         = $this->get_reservation_status( $reservation_id );

        if ( ! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_PENDING_APPROVAL ), true ) ) {
            return new WP_Error( 'htp_invalid_transition', 'Reservation cannot be activated from its current status.' );
        }

        if ( ! $this->hold_stock_for_reservation( $reservation_id ) ) {
            return new WP_Error( 'htp_no_stock', 'No stock available to activate this reservation.' );
        }

        $options        = get_option( 'holdthisproduct_options' );
        $duration_hours = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
        $expires_at     = current_time( 'timestamp' ) + ( max( 1, $duration_hours ) * HOUR_IN_SECONDS );

        update_post_meta( $reservation_id, self::EXPIRES_META_KEY, $expires_at );
        $this->set_reservation_status( $reservation_id, self::STATUS_ACTIVE );

        if ( self::STATUS_PENDING_APPROVAL === $status ) {
            $email = get_post_meta( $reservation_id, self::EMAIL_META_KEY, true );
            if ( $email ) {
                do_action( 'htp_reservation_approved', $reservation_id, $email );
            }
        }

        return true;
    }

    /**
     * Mark a reservation as fulfilled and release the reservation hold.
     *
     * WooCommerce has already reduced stock for the order by the time these hooks run,
     * so we must release the reservation hold to avoid a double stock reduction.
     *
     * @param int $reservation_id Reservation ID.
     * @return bool
     */
    private function fulfill_reservation( $reservation_id ) {
        if ( self::STATUS_ACTIVE !== $this->get_reservation_status( $reservation_id ) ) {
            return false;
        }

        if ( $this->is_stock_held_for_reservation( $reservation_id ) ) {
            $this->release_stock_for_reservation( $reservation_id );
        }

        return $this->set_reservation_status( $reservation_id, self::STATUS_FULFILLED );
    }

    /**
     * Hold stock for a reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return bool
     */
    private function hold_stock_for_reservation( $reservation_id ) {
        if ( $this->is_stock_held_for_reservation( $reservation_id ) ) {
            return true;
        }

        $product = $this->get_reservation_product( $reservation_id );
        if ( ! $product || ! $product->managing_stock() ) {
            return false;
        }

        $available_stock = $this->get_available_stock( $product );
        if ( $available_stock <= 0 ) {
            return false;
        }

        $result = wc_update_product_stock( $product, 1, 'decrease' );

        if ( false === $result ) {
            return false;
        }

        update_post_meta( $reservation_id, self::STOCK_HELD_META_KEY, 1 );

        return true;
    }

    /**
     * Release stock held by a reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return bool
     */
    private function release_stock_for_reservation( $reservation_id ) {
        $product = $this->get_reservation_product( $reservation_id );
        if ( ! $product || ! $product->managing_stock() ) {
            return false;
        }

        $result = wc_update_product_stock( $product, 1, 'increase' );

        if ( false === $result ) {
            return false;
        }

        update_post_meta( $reservation_id, self::STOCK_HELD_META_KEY, 0 );

        return true;
    }

    /**
     * Get product object for a reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return WC_Product|false
     */
    private function get_reservation_product( $reservation_id ) {
        $product_id = (int) get_post_meta( $reservation_id, self::PRODUCT_META_KEY, true );
        if ( ! $product_id ) {
            return false;
        }

        return wc_get_product( $product_id );
    }

    /**
     * Get available stock for a product.
     *
     * @param WC_Product $product Product object.
     * @return int
     */
    private function get_available_stock( $product ) {
        $stock_quantity = $product->get_stock_quantity();

        if ( null === $stock_quantity ) {
            return 0;
        }

        return max( 0, (int) $stock_quantity );
    }

    /**
     * Get a reservation status.
     *
     * @param int $reservation_id Reservation ID.
     * @return string
     */
    public function get_reservation_status( $reservation_id ) {
        return (string) get_post_meta( $reservation_id, self::STATUS_META_KEY, true );
    }

    /**
     * Set a reservation status.
     *
     * @param int    $reservation_id Reservation ID.
     * @param string $status Status.
     * @return bool
     */
    public function set_reservation_status( $reservation_id, $status ) {
        if ( ! in_array( $status, $this->get_valid_statuses(), true ) ) {
            return false;
        }

        update_post_meta( $reservation_id, self::STATUS_META_KEY, $status );
        return true;
    }

    /**
     * Check whether stock is currently held for a reservation.
     *
     * @param int $reservation_id Reservation ID.
     * @return bool
     */
    private function is_stock_held_for_reservation( $reservation_id ) {
        return '1' === (string) get_post_meta( $reservation_id, self::STOCK_HELD_META_KEY, true );
    }

    /**
     * Get all valid statuses.
     *
     * @return string[]
     */
    public function get_valid_statuses() {
        return array(
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_ACTIVE,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
            self::STATUS_FULFILLED,
            self::STATUS_DENIED,
        );
    }
}
