<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core reservations functionality
 */
class HMP_Reservations {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_endpoints' ) );
        add_action( 'init', array( $this, 'expire_old_reservations' ) );
        
        // WooCommerce account integration
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
        add_action( 'woocommerce_account_hmp-reservations_endpoint', array( $this, 'reservations_endpoint_content' ) );
        add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_reservation_actions' ) );
        add_filter( 'woocommerce_endpoint_hmp-reservations_title', array( $this, 'reservations_endpoint_title' ) );
        add_filter( 'woocommerce_page_title', array( $this, 'change_reservations_page_title' ) );
        
        // Flush rewrite rules on activation
        register_activation_hook( HMP_PLUGIN_PATH . 'HoldMyProduct.php', array( $this, 'flush_rewrite_rules' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_holdmyproduct_reserve', array( $this, 'handle_reservation_ajax' ) );
        add_action( 'wp_ajax_nopriv_holdmyproduct_reserve', array( $this, 'handle_reservation_ajax' ) );
        
        // Auto-fulfill reservations on purchase
        add_action( 'woocommerce_order_status_completed', array( $this, 'fulfill_reservation_on_purchase' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'fulfill_reservation_on_purchase' ) );
    }
    
    /**
     * Register custom post type for reservations
     */
    public function register_post_type() {
        register_post_type( 'hmp_reservation', array(
            'labels' => array( 'name' => 'Reservations' ),
            'public' => false,
            'show_ui' => false,
            'supports' => array( 'title', 'author' ),
            'capability_type' => 'post',
        ) );
    }
    
    /**
     * Register WooCommerce endpoints
     */
    public function register_endpoints() {
        add_rewrite_endpoint( 'hmp-reservations', EP_ROOT | EP_PAGES );
    }
    
    /**
     * Add query vars for WooCommerce
     */
    public function add_query_vars( $vars ) {
        $vars['hmp-reservations'] = 'hmp-reservations';
        return $vars;
    }
    
    /**
     * Flush rewrite rules (call this on plugin activation)
     */
    public function flush_rewrite_rules() {
        $this->register_endpoints();
        flush_rewrite_rules();
    }
    
    /**
     * Handle reservation AJAX request
     */
    public function handle_reservation_ajax() {
        check_ajax_referer( 'holdmyproduct_nonce', 'security' );
        
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( 'Invalid product ID.' );
        }
        
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to reserve products.' );
        }
        
        $user_id = get_current_user_id();
        
        // Validation
        if ( ! $this->is_product_reservable( $product_id ) ) {
            wp_send_json_error( 'Reservations are disabled for this product.' );
        }
        
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->managing_stock() ) {
            wp_send_json_error( 'Product stock is not managed.' );
        }
        
        $stock_quantity = (int) $product->get_stock_quantity();
        if ( $stock_quantity <= 0 ) {
            wp_send_json_error( 'No stock available.' );
        }
        
        // Check limits
        $limit = $this->get_max_reservations_per_user();
        $active = $this->count_active_reservations( $user_id );
        
        if ( $active >= $limit ) {
            wp_send_json_error( sprintf( 'You have reached the maximum of %d active reservations.', $limit ) );
        }
        
        if ( $this->user_has_active_reservation_for_product( $product_id, $user_id ) ) {
            wp_send_json_error( 'You already have an active reservation for this product.' );
        }
        
        // Create reservation
        $reservation_id = $this->create_reservation( $product_id, $user_id );
        
        if ( $reservation_id ) {
            // Update stock
            $product->set_stock_quantity( max( 0, $stock_quantity - 1 ) );
            $product->save();
            
            wp_send_json_success( 'Reservation created successfully.' );
        } else {
            wp_send_json_error( 'Could not create reservation.' );
        }
    }
    
    /**
     * Create a new reservation
     */
    public function create_reservation( $product_id, $user_id = 0, $guest_email = '' ) {
        $options = get_option( 'holdmyproduct_options' );
        $duration_hours = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
        $expires_at = current_time( 'timestamp' ) + ( $duration_hours * HOUR_IN_SECONDS );
        
        $reservation_id = wp_insert_post( array(
            'post_type'   => 'hmp_reservation',
            'post_title'  => 'Reservation for product ' . $product_id,
            'post_status' => 'publish',
            'post_author' => $user_id ?: 0,
        ) );
        
        if ( is_wp_error( $reservation_id ) ) {
            return false;
        }
        
        // Determine initial status based on admin approval setting
        $require_approval = ! empty( $options['require_admin_approval'] );
        $initial_status = $require_approval ? 'pending_approval' : 'active';
        
        // Save meta data
        $meta_data = array(
            '_hmp_product_id' => $product_id,
            '_hmp_status' => $initial_status,
            '_hmp_expires_at' => $expires_at,
            '_hmp_qty' => 1,
        );
        
        // Get logged-in user's email for notifications
        $notification_email = '';
        if ( $user_id ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $notification_email = $user->user_email;
                $meta_data['_hmp_email'] = $notification_email;
            }
        }
        
        foreach ( $meta_data as $key => $value ) {
            update_post_meta( $reservation_id, $key, $value );
        }
        
        // Trigger appropriate email notification
        if ( $notification_email ) {
            if ( $require_approval ) {
                do_action( 'hmp_reservation_pending_approval', $reservation_id, $notification_email );
            } else {
                do_action( 'hmp_reservation_created', $reservation_id, $notification_email );
            }
        }
        
        return $reservation_id;
    }
    
    /**
     * Check if product is reservable
     */
    public function is_product_reservable( $product_id ) {
        if ( ! $this->are_reservations_globally_enabled() ) {
            return false;
        }
        
        // Require user to be logged in
        if ( ! is_user_logged_in() ) {
            return false;
        }
        
        return get_post_meta( $product_id, '_hmp_reservations_enabled', true ) === 'yes';
    }
    
    /**
     * Check if reservations are globally enabled
     */
    public function are_reservations_globally_enabled() {
        $options = get_option( 'holdmyproduct_options' );
        return ! empty( $options['enable_reservation'] );
    }
    
    /**
     * Get max reservations per user
     */
    public function get_max_reservations_per_user() {
        $options = get_option( 'holdmyproduct_options' );
        return max( 1, absint( $options['max_reservations'] ?? 1 ) );
    }
    
    /**
     * Count active reservations for user
     */
    public function count_active_reservations( $user_id = 0, $email = '' ) {
        $args = array(
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array( 'key' => '_hmp_status', 'value' => 'active' ),
                array( 'key' => '_hmp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
        );
        
        if ( $user_id > 0 ) {
            $args['author'] = $user_id;
        } elseif ( $email !== '' ) {
            $args['meta_query'][] = array( 'key' => '_hmp_email', 'value' => $email );
        } else {
            return 0;
        }
        
        return count( get_posts( $args ) );
    }
    
    /**
     * Check if user has active reservation for specific product
     */
    public function user_has_active_reservation_for_product( $product_id, $user_id = 0, $email = '' ) {
        $args = array(
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array( 'key' => '_hmp_status', 'value' => 'active' ),
                array( 'key' => '_hmp_product_id', 'value' => $product_id ),
                array( 'key' => '_hmp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
        );
        
        if ( $user_id > 0 ) {
            $args['author'] = $user_id;
        } elseif ( $email !== '' ) {
            $args['meta_query'][] = array( 'key' => '_hmp_email', 'value' => $email );
        } else {
            return false;
        }
        
        return ! empty( get_posts( $args ) );
    }
    
    /**
     * Expire old reservations
     */
    public function expire_old_reservations() {
        // Use cache to prevent running too frequently
        if ( wp_cache_get( 'hmp_expired_check', 'holdmyproduct' ) ) {
            return;
        }
        
        $expired = get_posts( array(
            'post_type'     => 'hmp_reservation',
            'post_status'   => 'publish',
            'fields'        => 'ids',
            'posts_per_page'=> 100,
            'meta_query'    => array(
                array( 'key' => '_hmp_status', 'value' => 'active' ),
                array( 'key' => '_hmp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '<' )
            ),
        ) );
        
        foreach ( $expired as $reservation_id ) {
            $this->expire_reservation( $reservation_id );
        }
        
        wp_cache_set( 'hmp_expired_check', true, 'holdmyproduct', 300 );
    }
    
    /**
     * Expire a single reservation
     */
    public function expire_reservation( $reservation_id ) {
        update_post_meta( $reservation_id, '_hmp_status', 'expired' );
        
        // Get email for notification
        $email = get_post_meta( $reservation_id, '_hmp_email', true );
        
        // Restore stock
        $product_id = (int) get_post_meta( $reservation_id, '_hmp_product_id', true );
        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( $product && $product->managing_stock() ) {
                $product->set_stock_quantity( $product->get_stock_quantity() + 1 );
                $product->save();
            }
        }
        
        // Trigger expiration email notification
        if ( $email ) {
            do_action( 'hmp_reservation_expired', $reservation_id, $email );
        }
    }
    
    /**
     * Cancel a reservation
     */
    public function cancel_reservation( $reservation_id ) {
        update_post_meta( $reservation_id, '_hmp_status', 'cancelled' );
        
        // Restore stock
        $product_id = (int) get_post_meta( $reservation_id, '_hmp_product_id', true );
        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( $product && $product->managing_stock() ) {
                $product->set_stock_quantity( $product->get_stock_quantity() + 1 );
                $product->save();
            }
        }
    }
    
    /**
     * Add reservations to WooCommerce account menu
     */
    public function add_account_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            if ( $key === 'customer-logout' ) {
                $new['hmp-reservations'] = __( 'Reserved products', 'hold-my-product' );
            }
            $new[$key] = $label;
        }
        if ( ! isset( $new['hmp-reservations'] ) ) {
            $new['hmp-reservations'] = __( 'Reserved products', 'hold-my-product' );
        }
        return $new;
    }
    
    /**
     * Change endpoint title for reservations
     */
    public function reservations_endpoint_title( $title ) {
        return __( 'Reserved products', 'hold-my-product' );
    }
    
    /**
     * Change page title when on reservations page
     */
    public function change_reservations_page_title( $title ) {
        global $wp_query;
        
        if ( ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
            // Check if we're on the reservations endpoint
            if ( isset( $wp_query->query_vars['hmp-reservations'] ) || 
                 ( function_exists( 'wc_get_page_id' ) && is_wc_endpoint_url( 'hmp-reservations' ) ) ) {
                return __( 'Reservations', 'hold-my-product' );
            }
        }
        
        return $title;
    }
    
    /**
     * Display reservations in My Account
     */
    public function reservations_endpoint_content() {
        // Prevent double rendering
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;
        
        if ( ! is_user_logged_in() ) {
            wc_print_notice( __( 'Please log in to see your reservations.', 'hold-my-product' ), 'notice' );
            return;
        }
        
        $reservations = get_posts( array(
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'author'         => get_current_user_id(),
            'posts_per_page' => 20,
            'meta_query'     => array(
                array( 'key' => '_hmp_status', 'value' => 'active' ),
                array( 'key' => '_hmp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
        
        if ( empty( $reservations ) ) {
            wc_print_notice( __( 'You have no active reservations.', 'hold-my-product' ), 'notice' );
            return;
        }
        
        // Use WooCommerce's built-in table structure for consistency
        wc_get_template( 'myaccount/my-reservations.php', array(
            'reservations' => $reservations,
        ), '', HMP_PLUGIN_PATH . 'templates/' );
    }
    
    /**
     * Display single reservation row
     */
    private function display_reservation_row( $reservation ) {
        $product_id = (int) get_post_meta( $reservation->ID, '_hmp_product_id', true );
        $expires_ts = (int) get_post_meta( $reservation->ID, '_hmp_expires_at', true );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            return;
        }
        
        $expires_disp = $expires_ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_ts ) : '—';
        
        // Calculate time left
        $time_left = '';
        if ( $expires_ts ) {
            $diff = $expires_ts - current_time( 'timestamp' );
            if ( $diff > 0 ) {
                $days = floor( $diff / DAY_IN_SECONDS );
                $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
                
                if ( $days > 0 ) {
                    $time_left = sprintf( '%d days, %d hours', $days, $hours );
                } elseif ( $hours > 0 ) {
                    $time_left = sprintf( '%d hours, %d minutes', $hours, $minutes );
                } else {
                    $time_left = sprintf( '%d minutes', $minutes );
                }
                
                // Add urgency class for styling
                $urgency_class = '';
                if ( $diff < 2 * HOUR_IN_SECONDS ) {
                    $urgency_class = 'urgent';
                } elseif ( $diff < 6 * HOUR_IN_SECONDS ) {
                    $urgency_class = 'warning';
                }
            } else {
                $time_left = esc_html__( 'Expired', 'hold-my-product' );
                $urgency_class = 'expired';
            }
        }
        
        $add_to_cart_url = esc_url( wc_get_cart_url() . '?add-to-cart=' . $product_id );
        $cancel_url = wp_nonce_url(
            add_query_arg( array( 'hmp_cancel_res' => $reservation->ID ) ),
            'hmp_cancel_res_' . $reservation->ID
        );
        ?>
        
        <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-active order">
            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-product" data-title="<?php esc_attr_e( 'Product', 'hold-my-product' ); ?>">
                <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="woocommerce-LoopProduct-link">
                    <?php echo esc_html( $product->get_name() ); ?>
                </a>
            </td>
            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-expires" data-title="<?php esc_attr_e( 'Expires', 'hold-my-product' ); ?>">
                <time datetime="<?php echo esc_attr( date( 'c', $expires_ts ) ); ?>">
                    <?php echo esc_html( $expires_disp ); ?>
                </time>
            </td>
            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-time-left <?php echo esc_attr( $urgency_class ); ?>" data-title="<?php esc_attr_e( 'Time Left', 'hold-my-product' ); ?>">
                <span class="time-left <?php echo esc_attr( $urgency_class ); ?>">
                    <?php echo esc_html( $time_left ); ?>
                </span>
            </td>
            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="<?php esc_attr_e( 'Actions', 'hold-my-product' ); ?>">
                <a href="<?php echo $add_to_cart_url; ?>" class="woocommerce-button button add-to-cart">
                    <?php esc_html_e( 'Add to Cart', 'hold-my-product' ); ?>
                </a>
                <a href="<?php echo esc_url( $cancel_url ); ?>" class="woocommerce-button button cancel-reservation" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to cancel this reservation?', 'hold-my-product' ); ?>')">
                    <?php esc_html_e( 'Cancel', 'hold-my-product' ); ?>
                </a>
            </td>
        </tr>
        
        <?php
    }
    
    /**
     * Handle reservation actions (cancel, etc.)
     */
    public function handle_reservation_actions() {
        if ( ! is_user_logged_in() || ! isset( $_GET['hmp_cancel_res'] ) ) {
            return;
        }
        
        $reservation_id = absint( $_GET['hmp_cancel_res'] );
        if ( ! $reservation_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'hmp_cancel_res_' . $reservation_id ) ) {
            return;
        }
        
        $post = get_post( $reservation_id );
        if ( ! $post || (int) $post->post_author !== get_current_user_id() ) {
            return;
        }
        
        $this->cancel_reservation( $reservation_id );
        wp_safe_redirect( wc_get_account_endpoint_url( 'hmp-reservations' ) );
        exit;
    }
    
    /**
     * Auto-fulfill reservations when order is completed
     */
    public function fulfill_reservation_on_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        
        $customer_email = $order->get_billing_email();
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            
            // Check for active reservation
            $reservations = get_posts( array(
                'post_type'      => 'hmp_reservation',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array( 'key' => '_hmp_status', 'value' => 'active' ),
                    array( 'key' => '_hmp_product_id', 'value' => $product_id ),
                    array( 'key' => '_hmp_email', 'value' => $customer_email )
                ),
            ) );
            
            if ( ! empty( $reservations ) ) {
                update_post_meta( $reservations[0]->ID, '_hmp_status', 'fulfilled' );
                // Don't restore stock since it was purchased
            }
        }
    }
    
    /**
     * Approve a pending reservation
     */
    public function approve_reservation( $reservation_id ) {
        $current_status = get_post_meta( $reservation_id, '_hmp_status', true );
        
        if ( $current_status !== 'pending_approval' ) {
            return false;
        }
        
        // Update status to active
        update_post_meta( $reservation_id, '_hmp_status', 'active' );
        
        // Send confirmation email
        $email = get_post_meta( $reservation_id, '_hmp_email', true );
        if ( $email ) {
            do_action( 'hmp_reservation_approved', $reservation_id, $email );
        }
        
        return true;
    }
    
    /**
     * Deny a pending reservation
     */
    public function deny_reservation( $reservation_id, $reason = '' ) {
        $current_status = get_post_meta( $reservation_id, '_hmp_status', true );
        
        if ( $current_status !== 'pending_approval' ) {
            return false;
        }
        
        // Update status to denied
        update_post_meta( $reservation_id, '_hmp_status', 'denied' );
        
        // Store denial reason if provided
        if ( $reason ) {
            update_post_meta( $reservation_id, '_hmp_denial_reason', sanitize_text_field( $reason ) );
        }
        
        // Send denial email
        $email = get_post_meta( $reservation_id, '_hmp_email', true );
        if ( $email ) {
            do_action( 'hmp_reservation_denied', $reservation_id, $email, $reason );
        }
        
        return true;
    }
}
