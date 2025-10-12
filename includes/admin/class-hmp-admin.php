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
        
        // Product reservation management
        add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'add_product_reservations_list' ) );
        
        // Products list customization
        add_action( 'admin_init', array( $this, 'init_products_list' ) );
        
        // AJAX handlers for reservation management
        add_action( 'wp_ajax_hmp_cancel_admin_reservation', array( $this, 'handle_admin_cancel_reservation' ) );
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
        
        // Add reservations management submenu
        add_submenu_page(
            'holdmyproduct-settings',
            'Manage Reservations',
            'Manage Reservations',
            'manage_options',
            'holdmyproduct-manage-reservations',
            array( $this, 'manage_reservations_page' )
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
            'holdmyproduct_reservation_duration' => 'Reservation Duration (hours)',
            'holdmyproduct_enable_guest_reservation' => 'Enable Guest Reservations',
            'holdmyproduct_enable_email_notifications' => 'Enable Email Notifications',
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
     * Reservation duration field callback
     */
    public function holdmyproduct_reservation_duration_callback() {
        $options = get_option( 'holdmyproduct_options' );
        $value = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
        echo '<div id="holdmyproduct-duration-wrapper">
                <input type="number" min="1" max="168" name="holdmyproduct_options[reservation_duration]" value="' . esc_attr( $value ) . '" class="holdmyproduct-small-input" />
                <p class="description">How long reservations last (1-168 hours, default: 24)</p>
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
     * Enable email notifications field callback
     */
    public function holdmyproduct_enable_email_notifications_callback() {
        $options = get_option( 'holdmyproduct_options' );
        $checked = ! empty( $options['enable_email_notifications'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdmyproduct_options[enable_email_notifications]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>
              <p class="description">Send email confirmations and reminders to customers.</p>';
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
     * Add product reservations list in inventory tab
     */
    public function add_product_reservations_list() {
        global $post;
        
        if ( ! $post ) return;
        
        $reservations = $this->get_product_reservations( $post->ID );
        
        echo '<div class="options_group">';
        echo '<h4>' . __( 'Active Reservations', 'hold-my-product' ) . '</h4>';
        
        if ( empty( $reservations ) ) {
            echo '<p>' . __( 'No active reservations for this product.', 'hold-my-product' ) . '</p>';
        } else {
            echo '<table class="widefat striped" style="margin-top: 10px;">';
            echo '<thead><tr>';
            echo '<th>' . __( 'Customer', 'hold-my-product' ) . '</th>';
            echo '<th>' . __( 'Expires', 'hold-my-product' ) . '</th>';
            echo '<th>' . __( 'Action', 'hold-my-product' ) . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ( $reservations as $reservation ) {
                $this->display_product_reservation_row( $reservation );
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get active reservations for a specific product
     */
    private function get_product_reservations( $product_id ) {
        return get_posts( array(
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => array(
                array( 'key' => '_hmp_status', 'value' => 'active' ),
                array( 'key' => '_hmp_product_id', 'value' => $product_id ),
                array( 'key' => '_hmp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }
    
    /**
     * Display single product reservation row
     */
    private function display_product_reservation_row( $reservation ) {
        $email = get_post_meta( $reservation->ID, '_hmp_email', true );
        $name = get_post_meta( $reservation->ID, '_hmp_name', true );
        $surname = get_post_meta( $reservation->ID, '_hmp_surname', true );
        $expires_ts = (int) get_post_meta( $reservation->ID, '_hmp_expires_at', true );
        
        // Determine customer display name
        if ( $reservation->post_author ) {
            $user = get_userdata( $reservation->post_author );
            $customer = $user ? $user->display_name : 'Unknown User';
        } else {
            $customer = trim( $name . ' ' . $surname );
            if ( empty( $customer ) ) {
                $customer = $email;
            } else {
                $customer .= ' (' . $email . ')';
            }
        }
        
        $expires_disp = $expires_ts ? date_i18n( 'M j, Y @ H:i', $expires_ts ) : '—';
        
        echo '<tr>';
        echo '<td>' . esc_html( $customer ) . '</td>';
        echo '<td>' . esc_html( $expires_disp ) . '</td>';
        echo '<td>';
        echo '<button type="button" class="button hmp-cancel-reservation" ';
        echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
        echo 'data-customer="' . esc_attr( $customer ) . '">';
        echo __( 'Cancel', 'hold-my-product' );
        echo '</button>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Manage reservations page
     */
    public function manage_reservations_page() {
        $reservations = $this->get_all_active_reservations();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Manage Reservations', 'hold-my-product' ); ?></h1>
            
            <div class="hmp-reservations-stats">
                <p><strong><?php printf( __( 'Total Active Reservations: %d', 'hold-my-product' ), count( $reservations ) ); ?></strong></p>
            </div>
            
            <?php if ( empty( $reservations ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No active reservations found.', 'hold-my-product' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php esc_html_e( 'Product', 'hold-my-product' ); ?></th>
                            <th style="width: 25%;"><?php esc_html_e( 'Customer', 'hold-my-product' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Reserved', 'hold-my-product' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Expires', 'hold-my-product' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'Time Left', 'hold-my-product' ); ?></th>
                            <th style="width: 10%;"><?php esc_html_e( 'Actions', 'hold-my-product' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $reservations as $reservation ) : ?>
                            <?php $this->display_admin_reservation_row( $reservation ); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px;">
                    <p class="description">
                        <?php esc_html_e( 'Click "Cancel Reservation" to immediately cancel a customer\'s reservation and restore the product stock.', 'hold-my-product' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get all active reservations
     */
    private function get_all_active_reservations() {
        return get_posts( array(
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array( 'key' => '_hmp_status', 'value' => 'active' ),
                array( 'key' => '_hmp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }
    
    /**
     * Display admin reservation row
     */
    private function display_admin_reservation_row( $reservation ) {
        $product_id = (int) get_post_meta( $reservation->ID, '_hmp_product_id', true );
        $email = get_post_meta( $reservation->ID, '_hmp_email', true );
        $name = get_post_meta( $reservation->ID, '_hmp_name', true );
        $surname = get_post_meta( $reservation->ID, '_hmp_surname', true );
        $expires_ts = (int) get_post_meta( $reservation->ID, '_hmp_expires_at', true );
        
        $product = wc_get_product( $product_id );
        $product_name = $product ? $product->get_name() : 'Unknown Product';
        $product_edit_url = $product ? admin_url( 'post.php?post=' . $product_id . '&action=edit' ) : '#';
        
        // Determine customer display name
        if ( $reservation->post_author ) {
            $user = get_userdata( $reservation->post_author );
            $customer = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'Unknown User';
            $customer_short = $user ? $user->display_name : 'Unknown User';
        } else {
            $customer_full = trim( $name . ' ' . $surname );
            if ( empty( $customer_full ) ) {
                $customer = $email;
                $customer_short = $email;
            } else {
                $customer = $customer_full . ' (' . $email . ')';
                $customer_short = $customer_full;
            }
        }
        
        $reserved_date = get_the_date( 'M j, Y @ H:i', $reservation );
        $expires_disp = $expires_ts ? date_i18n( 'M j, Y @ H:i', $expires_ts ) : '—';
        
        // Calculate time left with color coding
        $time_left = '';
        $time_class = '';
        if ( $expires_ts ) {
            $diff = $expires_ts - current_time( 'timestamp' );
            if ( $diff > 0 ) {
                $hours = floor( $diff / 3600 );
                $minutes = floor( ( $diff % 3600 ) / 60 );
                $time_left = sprintf( '%dh %dm', $hours, $minutes );
                
                // Add warning colors
                if ( $hours < 2 ) {
                    $time_class = 'time-left-critical';
                } elseif ( $hours < 6 ) {
                    $time_class = 'time-left-warning';
                }
            } else {
                $time_left = 'Expired';
                $time_class = 'time-left-critical';
            }
        }
        
        echo '<tr>';
        echo '<td><a href="' . esc_url( $product_edit_url ) . '" target="_blank">' . esc_html( $product_name ) . '</a></td>';
        echo '<td title="' . esc_attr( $customer ) . '">' . esc_html( $customer ) . '</td>';
        echo '<td>' . esc_html( $reserved_date ) . '</td>';
        echo '<td>' . esc_html( $expires_disp ) . '</td>';
        echo '<td class="' . esc_attr( $time_class ) . '">' . esc_html( $time_left ) . '</td>';
        echo '<td>';
        echo '<button type="button" class="button button-small hmp-cancel-reservation" ';
        echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
        echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
        echo 'data-product="' . esc_attr( $product_name ) . '">';
        echo __( 'Cancel', 'hold-my-product' );
        echo '</button>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Handle admin reservation cancellation
     */
    public function handle_admin_cancel_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        check_ajax_referer( 'hmp_admin_cancel', 'nonce' );
        
        $reservation_id = absint( $_POST['reservation_id'] ?? 0 );
        
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }
        
        // Verify this is an active reservation
        $status = get_post_meta( $reservation_id, '_hmp_status', true );
        if ( $status !== 'active' ) {
            wp_send_json_error( 'Reservation is not active.' );
        }
        
        // Cancel the reservation using existing method
        $reservations_class = new HMP_Reservations();
        $reservations_class->cancel_reservation( $reservation_id );
        
        // Add admin note
        update_post_meta( $reservation_id, '_hmp_cancelled_by_admin', current_time( 'timestamp' ) );
        update_post_meta( $reservation_id, '_hmp_cancelled_by_user', get_current_user_id() );
        
        wp_send_json_success( 'Reservation cancelled successfully.' );
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
        
        // Manage reservations page scripts
        if ( $hook === 'holdmyproduct_page_holdmyproduct-manage-reservations' ) {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_style( 'holdmyproduct-admin-style', HMP_PLUGIN_URL . 'admin-style.css', array(), HMP_VERSION );
            wp_add_inline_script( 'jquery', $this->get_manage_reservations_inline_script() );
        }
        
        // Product edit page scripts
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            global $post;
            if ( $post && $post->post_type === 'product' ) {
                wp_enqueue_script( 'jquery' );
                wp_add_inline_script( 'jquery', $this->get_product_page_script() );
            }
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
     * Get manage reservations page inline script
     */
    private function get_manage_reservations_inline_script() {
        $nonce = wp_create_nonce( 'hmp_admin_cancel' );
        return "
            jQuery(document).ready(function($) {
                $('.hmp-cancel-reservation').on('click', function() {
                    var \$btn = $(this);
                    var reservationId = \$btn.data('reservation-id');
                    var customer = \$btn.data('customer');
                    var product = \$btn.data('product') || 'this product';
                    
                    if (confirm('Are you sure you want to cancel the reservation for ' + customer + ' on ' + product + '?')) {
                        \$btn.prop('disabled', true).text('Cancelling...');
                        
                        $.post(ajaxurl, {
                            action: 'hmp_cancel_admin_reservation',
                            reservation_id: reservationId,
                            nonce: '{$nonce}'
                        })
                        .done(function(response) {
                            if (response.success) {
                                \$btn.closest('tr').fadeOut(function() {
                                    $(this).remove();
                                });
                                
                                // Update counter
                                var currentCount = $('.hmp-reservations-stats p strong').text().match(/\d+/);
                                if (currentCount) {
                                    var newCount = parseInt(currentCount[0]) - 1;
                                    $('.hmp-reservations-stats p strong').html('Total Active Reservations: ' + newCount);
                                }
                                
                                // Show success message
                                if ($('.notice').length === 0) {
                                    $('<div class=\"notice notice-success is-dismissible\"><p>Reservation cancelled successfully.</p></div>')
                                        .insertAfter('.wrap h1');
                                }
                            } else {
                                alert('Error: ' + response.data);
                                \$btn.prop('disabled', false).text('Cancel');
                            }
                        })
                        .fail(function() {
                            alert('Request failed. Please try again.');
                            \$btn.prop('disabled', false).text('Cancel');
                        });
                    }
                });
            });
        ";
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
     * Get product page inline script for reservation management
     */
    private function get_product_page_script() {
        return "
            jQuery(document).ready(function($) {
                $('.hmp-cancel-reservation').on('click', function() {
                    var \$btn = $(this);
                    var reservationId = \$btn.data('reservation-id');
                    var customer = \$btn.data('customer');
                    
                    if (confirm('Are you sure you want to cancel the reservation for ' + customer + '?')) {
                        \$btn.prop('disabled', true).text('Cancelling...');
                        
                        $.post(ajaxurl, {
                            action: 'hmp_cancel_admin_reservation',
                            reservation_id: reservationId,
                            nonce: '" . wp_create_nonce( 'hmp_admin_cancel' ) . "'
                        })
                        .done(function(response) {
                            if (response.success) {
                                \$btn.closest('tr').fadeOut(function() {
                                    $(this).remove();
                                });
                                alert('Reservation cancelled successfully.');
                            } else {
                                alert('Error: ' + response.data);
                                \$btn.prop('disabled', false).text('Cancel');
                            }
                        })
                        .fail(function() {
                            alert('Request failed. Please try again.');
                            \$btn.prop('disabled', false).text('Cancel');
                        });
                    }
                });
            });
        ";
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