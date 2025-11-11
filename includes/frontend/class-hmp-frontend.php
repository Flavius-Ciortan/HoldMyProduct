<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend functionality
 */
class HMP_Frontend {
    
    /**
     * Reservations instance
     */
    private $reservations;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->reservations = new HMP_Reservations();
        $this->init();
    }
    
    /**
     * Initialize frontend hooks
     */
    private function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'display_reservation_form' ) );
    }
    
    /**
     * Display reservation form on product pages
     */
    public function display_reservation_form() {
        if ( ! is_product() ) {
            return;
        }
        
        global $product;
        if ( ! $product ) {
            return;
        }
        
        if ( ! $this->reservations->is_product_reservable( $product->get_id() ) ) {
            // Show message for non-logged-in users or when reservations are disabled
            if ( ! is_user_logged_in() ) {
                echo '<p class="hmp-reserve-unavailable" style="margin-top:8px;">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> or <a href="' . esc_url( wp_registration_url() ) . '">create an account</a> to reserve this product.</p>';
            } else {
                echo '<p class="hmp-reserve-unavailable" style="margin-top:8px;">Reservations are not available for this product.</p>';
            }
            return;
        }
        
        $this->include_form_template();
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'holdmyproduct-style',
            HMP_PLUGIN_URL . 'assets/css/style.css',
            array(),
            HMP_VERSION
        );
        
        wp_enqueue_script(
            'holdmyproduct-js',
            HMP_PLUGIN_URL . 'assets/js/holdmyproduct.js',
            array( 'jquery' ),
            HMP_VERSION,
            true
        );
        
        wp_localize_script( 'holdmyproduct-js', 'holdmyproduct_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'holdmyproduct_nonce' ),
            'is_logged_in' => is_user_logged_in() ? 1 : 0,
        ) );
    }
    
    /**
     * Include the form template
     */
    private function include_form_template() {
        include HMP_PLUGIN_PATH . 'templates/form_template.php';
    }
}
