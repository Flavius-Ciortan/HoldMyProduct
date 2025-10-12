<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reservation analytics and reporting
 */
class HMP_Analytics {
    
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
    }
    
    /**
     * Add analytics submenu
     */
    public function add_analytics_submenu() {
        add_submenu_page(
            'holdmyproduct-settings',
            'Reservation Analytics',
            'Analytics',
            'manage_options',
            'holdmyproduct-analytics',
            array( $this, 'analytics_page' )
        );
    }
    
    /**
     * Display analytics page
     */
    public function analytics_page() {
        $stats = $this->get_reservation_stats();
        ?>
        <div class="wrap">
            <h1>Reservation Analytics</h1>
            
            <div class="hmp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="hmp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Total Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #0073aa;"><?php echo esc_html( $stats['total'] ); ?></p>
                </div>
                
                <div class="hmp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Active Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #46b450;"><?php echo esc_html( $stats['active'] ); ?></p>
                </div>
                
                <div class="hmp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Fulfilled Reservations</h3>
                    <p style="font-size: 32px; margin: 0; color: #00a32a;"><?php echo esc_html( $stats['fulfilled'] ); ?></p>
                </div>
                
                <div class="hmp-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
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
     * Get reservation statistics
     */
    private function get_reservation_stats() {
        global $wpdb;
        
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'hmp_reservation' AND post_status = 'publish'
        ");
        
        $active = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'hmp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_hmp_status' 
            AND pm.meta_value = 'active'
        ");
        
        $fulfilled = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'hmp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_hmp_status' 
            AND pm.meta_value = 'fulfilled'
        ");
        
        $conversion_rate = $total > 0 ? round( ( $fulfilled / $total ) * 100, 1 ) : 0;
        
        return array(
            'total' => $total,
            'active' => $active,
            'fulfilled' => $fulfilled,
            'conversion_rate' => $conversion_rate
        );
    }
    
    /**
     * Display recent reservations table
     */
    private function display_recent_reservations() {
        $reservations = get_posts( array(
            'post_type' => 'hmp_reservation',
            'posts_per_page' => 20,
            'meta_key' => '_hmp_status',
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
            $product_id = get_post_meta( $reservation->ID, '_hmp_product_id', true );
            $status = get_post_meta( $reservation->ID, '_hmp_status', true );
            $email = get_post_meta( $reservation->ID, '_hmp_email', true );
            $expires_ts = get_post_meta( $reservation->ID, '_hmp_expires_at', true );
            
            $product = wc_get_product( $product_id );
            $product_name = $product ? $product->get_name() : 'Unknown Product';
            
            $customer = $reservation->post_author ? get_userdata( $reservation->post_author )->display_name : $email;
            $expires = $expires_ts ? date_i18n( 'Y-m-d H:i', $expires_ts ) : '—';
            
            echo '<tr>';
            echo '<td>' . esc_html( $product_name ) . '</td>';
            echo '<td>' . esc_html( $customer ) . '</td>';
            echo '<td><span class="status-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span></td>';
            echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $reservation ) ) . '</td>';
            echo '<td>' . esc_html( $expires ) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}
