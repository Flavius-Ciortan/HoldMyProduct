<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin functionality
 */
class HMP_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize admin hooks
     */
    private function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'init_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Product settings
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_reservation_option' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_reservation_option' ) );
        
        // Products list customization
        add_action( 'admin_init', array( $this, 'init_products_list' ) );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'HoldMyProduct Settings',
            'HoldMyProduct',
            'manage_options',
            'holdmyproduct-settings',
            array( $this, 'settings_page' ),
            HMP_PLUGIN_URL . 'HMP-menu-icon.png',
            80
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting( 'holdmyproduct_options_group', 'holdmyproduct_options' );
        
        add_settings_section(
            'holdmyproduct_settings_section',
            'General Settings',
            array( $this, 'settings_section_callback' ),
            'holdmyproduct-settings'
        );
        
        $fields = array(
            'holdmyproduct_enable_reservation' => 'Enable Reservation',
            'holdmyproduct_max_reservations' => 'Max Reservations Per User',
            'holdmyproduct_enable_guest_reservation' => 'Enable Guest Reservations',
            'holdmyproduct_show_admin_toggle' => 'Show Admin Toggle (Products list)'
        );
        
        foreach ( $fields as $id => $title ) {
            add_settings_field(
                $id,
                $title,
                array( $this, $id . '_callback' ),
                'holdmyproduct-settings',
                'holdmyproduct_settings_section'
            );
        }
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Configure the HoldMyProduct plugin settings below.</p>';
    }
    
    /**
     * Enable reservation field callback
     */
    public function holdmyproduct_enable_reservation_callback() {
        $options = get_option( 'holdmyproduct_options' );
        $checked = ! empty( $options['enable_reservation'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdmyproduct_options[enable_reservation]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>';
    }
    
    /**
     * Max reservations field callback
     */
    public function holdmyproduct_max_reservations_callback() {
        $options = get_option( 'holdmyproduct_options' );
        $value = isset( $options['max_reservations'] ) ? absint( $options['max_reservations'] ) : 1;
        echo '<div id="holdmyproduct-max-reservations-wrapper">
                <input type="number" min="1" name="holdmyproduct_options[max_reservations]" value="' . esc_attr( $value ) . '" class="holdmyproduct-small-input" />
              </div>';
    }
    
    /**
     * Enable guest reservation field callback
     */
    public function holdmyproduct_enable_guest_reservation_callback() {
        $options = get_option( 'holdmyproduct_options' );
        $checked = ! empty( $options['enable_guest_reservation'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdmyproduct_options[enable_guest_reservation]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>
              <p class="description">Allow users without an account to reserve products using their email address.</p>';
    }
    
    /**
     * Show admin toggle field callback
     */
    public function holdmyproduct_show_admin_toggle_callback() {
        $options = get_option( 'holdmyproduct_options' );
        $checked = ! empty( $options['show_admin_toggle'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdmyproduct_options[show_admin_toggle]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>';
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>HoldMyProduct Settings</h1>
            <h2 class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active">General Settings</a>
                <a href="#logged-in" class="nav-tab">Logged In Users</a>
                <a href="#logged-out" class="nav-tab">Logged Out Users</a>
            </h2>

            <div id="general" class="tab-content active">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'holdmyproduct_options_group' );
                    do_settings_sections( 'holdmyproduct-settings' );
                    submit_button();
                    ?>
                </form>
            </div>

            <div id="logged-in" class="tab-content">
                <p><strong>Coming soon:</strong> Settings for logged-in users.</p>
            </div>

            <div id="logged-out" class="tab-content">
                <p><strong>Coming soon:</strong> Settings for guests (logged-out users).</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        // Settings page scripts
        if ( $hook === 'toplevel_page_holdmyproduct-settings' ) {
            wp_enqueue_style( 'wp-components' );
            wp_enqueue_script( 'wp-components' );
            wp_enqueue_style( 'holdmyproduct-admin-style', HMP_PLUGIN_URL . 'admin-style.css', array(), HMP_VERSION );
            
            wp_add_inline_script( 'wp-components', $this->get_admin_inline_script() );
        }
        
        // Product list scripts
        if ( $hook === 'edit.php' && ( $_GET['post_type'] ?? '' ) === 'product' && $this->show_admin_toggle_enabled() ) {
            wp_enqueue_script(
                'hmp-res-toggle',
                HMP_PLUGIN_URL . 'hmp-res-toggle.js',
                array( 'jquery' ),
                HMP_VERSION,
                true
            );
            
            wp_localize_script( 'hmp-res-toggle', 'hmpResToggle', array(
                'ajax'    => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'hmp_toggle_res' ),
                'enable'  => __( 'Enable', 'hold-my-product' ),
                'disable' => __( 'Disable', 'hold-my-product' ),
            ) );
            
            wp_enqueue_style(
                'holdmyproduct-admin-style',
                HMP_PLUGIN_URL . 'admin-style.css',
                array(),
                HMP_VERSION
            );
        }
    }
    
    /**
     * Get admin inline script
     */
    private function get_admin_inline_script() {
        return "
            jQuery(document).ready(function($) {
                $('.nav-tab').click(function(e) {
                    e.preventDefault();
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.tab-content').removeClass('active');
                    $(this).addClass('nav-tab-active');
                    $($(this).attr('href')).addClass('active');
                });

                function toggleMaxReservations() {
                    $('#holdmyproduct-max-reservations-wrapper').toggle(
                        $('input[name=\"holdmyproduct_options[enable_reservation]\"]').is(':checked')
                    );
                }
                
                function toggleGuestReservation() {
                    var isMainEnabled = $('input[name=\"holdmyproduct_options[enable_reservation]\"]').is(':checked');
                    var guestField = $('input[name=\"holdmyproduct_options[enable_guest_reservation]\"]').closest('tr');
                    
                    if (isMainEnabled) {
                        guestField.show();
                    } else {
                        guestField.hide();
                        $('input[name=\"holdmyproduct_options[enable_guest_reservation]\"]').prop('checked', false);
                    }
                }
                
                toggleMaxReservations();
                toggleGuestReservation();
                
                $('input[name=\"holdmyproduct_options[enable_reservation]\"]').on('change', function() {
                    toggleMaxReservations();
                    toggleGuestReservation();
                });
            });
        ";
    }
    
    /**
     * Add product reservation option
     */
    public function add_product_reservation_option() {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox( array(
            'id'          => '_hmp_reservations_enabled',
            'label'       => __( 'Enable reservations', 'hold-my-product' ),
            'desc_tip'    => true,
            'description' => __( 'Allow this product to be reserved via HoldMyProduct.', 'hold-my-product' ),
        ) );
        echo '</div>';
    }
    
    /**
     * Save product reservation option
     */
    public function save_product_reservation_option( WC_Product $product ) {
        $enabled = isset( $_POST['_hmp_reservations_enabled'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_hmp_reservations_enabled', $enabled );
    }
    
    /**
     * Initialize products list modifications
     */
    public function init_products_list() {
        if ( ! $this->show_admin_toggle_enabled() ) {
            return;
        }
        
        add_filter( 'manage_edit-product_columns', array( $this, 'add_reservations_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'display_reservations_column' ), 10, 2 );
        add_action( 'wp_ajax_hmp_toggle_res', array( $this, 'ajax_toggle_reservation' ) );
    }
    
    /**
     * Add reservations column to products list
     */
    public function add_reservations_column( $columns ) {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[$key] = $label;
            if ( $key === 'sku' ) {
                $new['hmp_reservations'] = __( 'Reservations', 'hold-my-product' );
            }
        }
        if ( ! isset( $new['hmp_reservations'] ) ) {
            $new['hmp_reservations'] = __( 'Reservations', 'hold-my-product' );
        }
        return $new;
    }
    
    /**
     * Display reservations column content
     */
    public function display_reservations_column( $column, $post_id ) {
        if ( $column !== 'hmp_reservations' ) {
            return;
        }
        
        $value = get_post_meta( $post_id, '_hmp_reservations_enabled', true );
        $is_enabled = ( $value === 'yes' );
        $state = $is_enabled ? 'on' : 'off';
        $label = $is_enabled ? __( 'Disable', 'hold-my-product' ) : __( 'Enable', 'hold-my-product' );
        
        printf(
            '<button type="button" class="button hmp-res-toggle %1$s" aria-pressed="%2$s" data-product-id="%3$d" data-state="%1$s">
                <span class="hmp-res-toggle-label">%4$s</span>
            </button>
            <span class="spinner" style="float:none;"></span>',
            esc_attr( $state ),
            $is_enabled ? 'true' : 'false',
            (int) $post_id,
            esc_html( $label )
        );
    }
    
    /**
     * Handle AJAX toggle reservation
     */
    public function ajax_toggle_reservation() {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'Forbidden', 'hold-my-product' ) ), 403 );
        }
        
        check_ajax_referer( 'hmp_toggle_res', 'nonce' );
        
        $product_id = absint( $_POST['product_id'] ?? 0 );
        $new_status = ( ( $_POST['new'] ?? '' ) === 'yes' ) ? 'yes' : 'no';
        
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product', 'hold-my-product' ) ), 400 );
        }
        
        update_post_meta( $product_id, '_hmp_reservations_enabled', $new_status );
        
        wp_send_json_success( array(
            'new'   => $new_status,
            'label' => ( $new_status === 'yes' ) ? __( 'Enabled', 'hold-my-product' ) : __( 'Disabled', 'hold-my-product' ),
        ) );
    }
    
    /**
     * Check if admin toggle is enabled
     */
    private function show_admin_toggle_enabled() {
        $options = get_option( 'holdmyproduct_options' );
        return ! empty( $options['show_admin_toggle'] );
    }
}
