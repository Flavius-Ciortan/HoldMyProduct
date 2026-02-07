<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Frontend functionality
 */
class HTP_Frontend {
    
    /**
     * Reservations instance
     */
    private $reservations;

    /**
     * Prevent duplicate output when multiple hooks fire.
     */
    private $did_render_form = false;

    /**
     * Prevent duplicate modal output.
     */
    private $did_render_modal = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->reservations = new HTP_Reservations();
        $this->init();
    }
    
    /**
     * Initialize frontend hooks
     */
    private function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        // Render next to the Add to cart button on single product pages.
        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_reservation_form' ) );

        // Render modal markup outside WooCommerce's form.cart to avoid nested <form> issues.
        add_action( 'wp_footer', array( $this, 'display_reservation_modal' ) );
    }
    
    /**
     * Display reservation form on product pages
     */
    public function display_reservation_form() {
        if ( $this->did_render_form ) {
            return;
        }

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
                echo '<p class="htp-reserve-unavailable" style="margin-top:8px;">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> or <a href="' . esc_url( wp_registration_url() ) . '">create an account</a> to reserve this product.</p>';
            } else {
                echo '<p class="htp-reserve-unavailable" style="margin-top:8px;">Reservations are not available for this product.</p>';
            }
            return;
        }

        $this->did_render_form = true;
        $this->include_form_template();
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'holdthisproduct-style',
            HTP_PLUGIN_URL . 'assets/css/style.css',
            array(),
            HTP_VERSION
        );
        
        wp_enqueue_script(
            'holdthisproduct-js',
            HTP_PLUGIN_URL . 'assets/js/holdthisproduct.js',
            array( 'jquery' ),
            HTP_VERSION,
            true
        );
        
        wp_localize_script( 'holdthisproduct-js', 'holdthisproduct_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'holdthisproduct_nonce' ),
            'is_logged_in' => is_user_logged_in() ? 1 : 0,
        ) );
    }
    
    /**
     * Include the form template
     */
    private function include_form_template() {
        include HTP_PLUGIN_PATH . 'templates/form_template.php';
    }

    /**
     * Display reservation modal on product pages (footer output).
     */
    public function display_reservation_modal() {
        if ( $this->did_render_modal ) {
            return;
        }

        if ( ! is_product() ) {
            return;
        }

        global $product;
        if ( ! $product ) {
            return;
        }

        if ( ! $this->reservations->is_product_reservable( $product->get_id() ) ) {
            return;
        }

        $this->did_render_modal = true;
        include HTP_PLUGIN_PATH . 'templates/modal_template.php';
    }
}
