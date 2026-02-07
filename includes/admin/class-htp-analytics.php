<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reservation analytics and reporting
 */
class HTP_Analytics {
    
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
        add_action( 'admin_menu', array( $this, 'add_analytics_submenu' ), 11 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analytics_scripts' ) );
    }
    
    /**
     * Add analytics submenu
     */
    public function add_analytics_submenu() {
        add_submenu_page(
            'holdthisproduct-settings',
            'Reservation Analytics',
            'Analytics',
            'manage_options',
            'holdthisproduct-analytics',
            array( $this, 'analytics_page' )
        );
    }
    
    /**
     * Enqueue analytics page scripts and styles
     */
    public function enqueue_analytics_scripts( $hook ) {
        if ( $hook === 'holdthisproduct_page_holdthisproduct-analytics' ) {
            wp_enqueue_style(
                'holdthisproduct-admin-style',
                HTP_PLUGIN_URL . 'admin-style.css',
                array(),
                HTP_VERSION
            );
        }
    }
    
    /**
     * Display analytics page
     */
    public function analytics_page() {
        // First, expire old reservations to get accurate stats
        $this->expire_old_reservations_for_analytics();
        
        $stats = $this->get_reservation_stats();
        ?>
        <div class="wrap">
            <h1>Reservation Analytics</h1>
            
            <div class="htp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Total Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #0073aa;"><?php echo esc_html( $stats['total'] ); ?></p>
                </div>
                
                <div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Active Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #46b450;"><?php echo esc_html( $stats['active'] ); ?></p>
                </div>
                
                <div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Expired Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #ff8c00;"><?php echo esc_html( $stats['expired'] ); ?></p>
                </div>
                
                <div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Cancelled Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #d63638;"><?php echo esc_html( $stats['cancelled'] ); ?></p>
                </div>
                
                <div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Fulfilled Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #00a32a;"><?php echo esc_html( $stats['fulfilled'] ); ?></p>
                </div>
                
                <div class="htp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Conversion Rate</h3>
                    <p style="font-size: 32px; margin: 0; color: #0073aa;"><?php echo esc_html( $stats['conversion_rate'] ); ?>%</p>
                </div>
            </div>
            
            <h2>Recent Reservations</h2>
            <?php $this->display_recent_reservations(); ?>
        </div>
        <?php
    }
    
    /**
     * Expire old reservations for analytics accuracy
     */
    private function expire_old_reservations_for_analytics() {
        global $wpdb;
        
        // Find reservations that are marked as 'active' but have passed their expiration time
        $expired_reservations = $wpdb->get_col("
            SELECT p.ID FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_htp_status' AND pm1.meta_value = 'active'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_htp_expires_at'
            WHERE p.post_type = 'htp_reservation' 
            AND p.post_status = 'publish'
            AND CAST(pm2.meta_value AS UNSIGNED) < UNIX_TIMESTAMP()
        ");
        
        // Update expired reservations
        if ( ! empty( $expired_reservations ) ) {
            // Load the reservations class to use its expire method
            if ( class_exists( 'HTP_Reservations' ) ) {
                $reservations_handler = new HTP_Reservations();
                foreach ( $expired_reservations as $reservation_id ) {
                    $reservations_handler->expire_reservation( $reservation_id );
                }
            } else {
                // Fallback: update status directly
                foreach ( $expired_reservations as $reservation_id ) {
                    update_post_meta( $reservation_id, '_htp_status', 'expired' );
                    
                    // Restore stock
                    $product_id = (int) get_post_meta( $reservation_id, '_htp_product_id', true );
                    if ( $product_id ) {
                        $product = wc_get_product( $product_id );
                        if ( $product && $product->managing_stock() ) {
                            $product->set_stock_quantity( $product->get_stock_quantity() + 1 );
                            $product->save();
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Get reservation statistics
     */
    private function get_reservation_stats() {
        global $wpdb;
        
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'htp_reservation' AND post_status = 'publish'
        ");
        
        $active = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'htp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_htp_status' 
            AND pm.meta_value = 'active'
        ");
        
        $expired = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'htp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_htp_status' 
            AND pm.meta_value = 'expired'
        ");
        
        $fulfilled = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'htp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_htp_status' 
            AND pm.meta_value = 'fulfilled'
        ");
        
        $cancelled = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'htp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_htp_status' 
            AND pm.meta_value = 'cancelled'
        ");
        
        $conversion_rate = $total > 0 ? round( ( $fulfilled / $total ) * 100, 1 ) : 0;
        
        return array(
            'total' => (int) $total,
            'active' => (int) $active,
            'expired' => (int) $expired,
            'fulfilled' => (int) $fulfilled,
            'cancelled' => (int) $cancelled,
            'conversion_rate' => $conversion_rate
        );
    }
    
    /**
     * Display recent reservations table
     */
    private function display_recent_reservations() {
        $reservations = get_posts( array(
            'post_type' => 'htp_reservation',
            'posts_per_page' => 20,
            'meta_key' => '_htp_status',
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
        
        if ( empty( $reservations ) ) {
            echo '<p>No reservations found.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Product</th><th>Customer</th><th>Status</th><th>Created</th><th>Expires</th></tr></thead>';
        echo '<tbody>';
        
        foreach ( $reservations as $reservation ) {
            $product_id = get_post_meta( $reservation->ID, '_htp_product_id', true );
            $status = get_post_meta( $reservation->ID, '_htp_status', true );
            $email = get_post_meta( $reservation->ID, '_htp_email', true );
            $expires_ts = get_post_meta( $reservation->ID, '_htp_expires_at', true );
            
            $product = wc_get_product( $product_id );
            $product_name = $product ? $product->get_name() : 'Unknown Product';
            
            // Determine customer display name
            if ( $reservation->post_author ) {
                $user = get_userdata( $reservation->post_author );
                $customer = $user ? $user->display_name : $email;
            } else {
                $name = get_post_meta( $reservation->ID, '_htp_name', true );
                $surname = get_post_meta( $reservation->ID, '_htp_surname', true );
                $full_name = trim( $name . ' ' . $surname );
                $customer = ! empty( $full_name ) ? $full_name : $email;
            }
            
            $expires = $expires_ts ? date_i18n( 'Y-m-d H:i', $expires_ts ) : '—';
            
            // Add CSS class for status styling with proper fallback
            $status_class = 'status-' . esc_attr( $status ?: 'unknown' );
            $status_display = ucfirst( $status ?: 'Unknown' );
            
            echo '<tr>';
            echo '<td>' . esc_html( $product_name ) . '</td>';
            echo '<td>' . esc_html( $customer ) . '</td>';
            echo '<td><span class="' . $status_class . '">' . esc_html( $status_display ) . '</span></td>';
            echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $reservation ) ) . '</td>';
            echo '<td>' . esc_html( $expires ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}
