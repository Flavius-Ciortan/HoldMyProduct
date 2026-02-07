<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin functionality
 */
class HTP_Admin {
    
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
        
        // Product reservation management
        add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'add_product_reservations_list' ) );
        
        // Products list customization
        add_action( 'admin_init', array( $this, 'init_products_list' ) );
        
        // AJAX handlers for reservation management
        add_action( 'wp_ajax_htp_cancel_admin_reservation', array( $this, 'handle_admin_cancel_reservation' ) );
        add_action( 'wp_ajax_htp_delete_admin_reservation', array( $this, 'handle_admin_delete_reservation' ) );
        add_action( 'wp_ajax_htp_approve_reservation', array( $this, 'handle_approve_reservation' ) );
        add_action( 'wp_ajax_htp_deny_reservation', array( $this, 'handle_deny_reservation' ) );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Hold This Product Settings',
            'Hold This Product',
            'manage_options',
            'holdthisproduct-settings',
            array( $this, 'settings_page' ),
            HTP_PLUGIN_URL . 'assets/images/HTP-menu-icon.png',
            80
        );

        // Add Settings submenu (points to the same page as the main menu)
        add_submenu_page(
            'holdthisproduct-settings',
            'Settings',
            'Settings',
            'manage_options',
            'holdthisproduct-settings',
            array( $this, 'settings_page' )
        );

        // Add reservations management submenu
        add_submenu_page(
            'holdthisproduct-settings',
            'Reservations',
            'Reservations',
            'manage_options',
            'holdthisproduct-manage-reservations',
            array( $this, 'manage_reservations_page' )
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting( 'holdthisproduct_options_group', 'holdthisproduct_options' );
        
        add_settings_section(
            'holdthisproduct_settings_section',
            'General Settings',
            array( $this, 'settings_section_callback' ),
            'holdthisproduct-settings'
        );
        
        $fields = array(
            'holdthisproduct_enable_reservation' => 'Enable Reservation',
            'holdthisproduct_max_reservations' => 'Max Reservations Per User',
            'holdthisproduct_reservation_duration' => 'Reservation Duration (hours)',
            'holdthisproduct_enable_email_notifications' => 'Enable Email Notifications',
            'holdthisproduct_require_admin_approval' => 'Require Admin Approval for Reservations'
        );
        
        foreach ( $fields as $id => $title ) {
            add_settings_field(
                $id,
                $title,
                array( $this, $id . '_callback' ),
                'holdthisproduct-settings',
                'holdthisproduct_settings_section'
            );
        }
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>Configure the Hold This Product plugin settings below.</p>';
    }
    
    /**
     * Enable reservation field callback
     */
    public function holdthisproduct_enable_reservation_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $checked = ! empty( $options['enable_reservation'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdthisproduct_options[enable_reservation]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>';
    }
    
    /**
     * Max reservations field callback
     */
    public function holdthisproduct_max_reservations_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $value = isset( $options['max_reservations'] ) ? absint( $options['max_reservations'] ) : 1;
        echo '<div class="htp-input-right-align">
                <input type="number" min="1" name="holdthisproduct_options[max_reservations]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
              </div>';
    }
    
    /**
     * Reservation duration field callback
     */
    public function holdthisproduct_reservation_duration_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $value = isset( $options['reservation_duration'] ) ? absint( $options['reservation_duration'] ) : 24;
        echo '<div class="htp-input-right-align">
                <input type="number" min="1" max="168" name="holdthisproduct_options[reservation_duration]" value="' . esc_attr( $value ) . '" class="holdthisproduct-small-input" />
                <p class="description">How long reservations last (1-168 hours, default: 24)</p>
              </div>';
    }
    
    /**
     * Enable email notifications field callback
     */
    public function holdthisproduct_enable_email_notifications_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $checked = ! empty( $options['enable_email_notifications'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdthisproduct_options[enable_email_notifications]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>
              <p class="description">Send email confirmations and reminders to customers.</p>';
    }
    
    /**
     * Require admin approval field callback
     */
    public function holdthisproduct_require_admin_approval_callback() {
        $options = get_option( 'holdthisproduct_options' );
        $checked = ! empty( $options['require_admin_approval'] ) ? 'checked' : '';
        echo '<label class="toggle-switch">
                <input type="checkbox" name="holdthisproduct_options[require_admin_approval]" value="1" ' . $checked . '>
                <span class="slider"></span>
              </label>
              <p class="description">Reservations require admin approval before becoming active.</p>';
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        ?>
        <div class="htp-admin-wrapper">
            <!-- Header with Logo -->
            <div class="htp-admin-header">
                <div class="htp-header-content">
                    <div class="htp-title-section">
                        <h1 class="htp-main-title">Hold This Product Settings</h1>
                        <p class="htp-subtitle">Manage your product reservation system</p>
                    </div>
                    <div class="htp-logo-section">
                        <?php
                        $logo_files = array('logo-transparent.png', 'HTP-menu-icon.png');
                        $logo_src = '';
                        $found_file = '';
                        
                        foreach ($logo_files as $logo_file) {
                            $logo_path = HTP_PLUGIN_PATH . 'assets/images/' . $logo_file;
                            if (file_exists($logo_path)) {
                                $logo_src = HTP_PLUGIN_URL . 'assets/images/' . rawurlencode($logo_file);
                                $found_file = $logo_file;
                                break;
                            }
                        }
                        
                        if ($logo_src): ?>
                            <img src="<?php echo esc_url($logo_src); ?>" alt="Hold This Product Logo" class="htp-logo">
                        <?php else: ?>
                            <div class="htp-logo htp-logo-fallback" title="No logo file found. Checked: <?php echo implode(', ', $logo_files); ?>">HTP</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="htp-admin-content">
                <!-- Navigation Tabs -->
                <div class="htp-nav-wrapper">
                    <div class="htp-nav-tabs">
                        <button type="button" class="htp-nav-tab" data-target="general">
                            <span class="htp-tab-icon">⚙️</span>
                            <span class="htp-tab-text">General Settings</span>
                        </button>
                        <button type="button" class="htp-nav-tab" data-target="logged-in">
                            <span class="htp-tab-icon">🎨</span>
                            <span class="htp-tab-text">Pop-up Customization</span>
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <form method="post" action="options.php" class="htp-settings-form">
                    <?php settings_fields( 'holdthisproduct_options_group' ); ?>
                    <div class="htp-tab-container">
                        <!-- General Settings Tab -->
                        <div id="htp-general" class="htp-tab-content">
                            <div class="htp-settings-card">
                                <div class="htp-card-header">
                                    <h3>Configuration</h3>
                                    <p>Configure the basic settings for your reservation system</p>
                                </div>
                                <div class="htp-card-body">
                                    <?php do_settings_sections( 'holdthisproduct-settings' ); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Pop-up Customization Tab -->
                        <div id="htp-logged-in" class="htp-tab-content">
                            <div class="htp-settings-card">
                                <div class="htp-card-header">
                                    <h3>Pop-up Customization</h3>
                                    <p>Customize the appearance of the reservation pop-up modal</p>
                                </div>
                                <div class="htp-card-body">
                                    <!-- COMING SOON MESSAGE - UNCOMMENT FOR PAID VERSION
                                    <div class="htp-coming-soon-message">
                                        <h4>🚀 Coming Soon in Pro Version!</h4>
                                        <p>Advanced pop-up customization for logged-in users will be available in our premium version.</p>
                                        <p><strong>Features include:</strong></p>
                                        <ul>
                                            <li>Custom border radius settings</li>
                                            <li>Background color customization</li>
                                            <li>Font family selection</li>
                                            <li>Font size adjustment</li>
                                            <li>Text color customization</li>
                                        </ul>
                                        <p>Stay tuned for updates!</p>
                                    </div>
                                    END COMING SOON MESSAGE -->
                                    
                                    <?php
                                    $options = get_option('holdthisproduct_options');
                                    $enable_popup_customization_logged_in = isset($options['enable_popup_customization_logged_in']) ? (bool)$options['enable_popup_customization_logged_in'] : false;
                                    $popup_settings_logged_in = isset($options['popup_customization_logged_in']) ? $options['popup_customization_logged_in'] : [];
                                    ?>
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">Enable Pop-up Customization</th>
                                            <td>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="holdthisproduct_options[enable_popup_customization_logged_in]" value="1" <?php checked($enable_popup_customization_logged_in); ?>>
                                                    <span class="slider"></span>
                                                </label>
                                                <p class="description">Enable custom styling for the reservation pop-up modal.</p>
                                            </td>
                                        </tr>
                                    </table>
                                    <div class="htp-popup-customization-fields-logged-in" style="display:<?php echo $enable_popup_customization_logged_in ? 'block' : 'none'; ?>;margin-top:1rem;">
                                        <table class="form-table">
                                            <tr>
                                                <th scope="row">Border Radius (px)</th>
                                                <td><input type="number" name="holdthisproduct_options[popup_customization_logged_in][border_radius]" value="<?php echo esc_attr($popup_settings_logged_in['border_radius'] ?? '12'); ?>" min="0" max="50" class="htp-input-right-align"></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Background Color</th>
                                                <td><input type="color" name="holdthisproduct_options[popup_customization_logged_in][background_color]" value="<?php echo esc_attr($popup_settings_logged_in['background_color'] ?? '#ffffff'); ?>" class="htp-input-right-align"></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Font Family</th>
                                                <td>
                                                    <select name="holdthisproduct_options[popup_customization_logged_in][font_family]" class="htp-input-right-align">
                                                        <?php
                                                        $fonts = [
                                                            'Arial, Helvetica, sans-serif' => 'Arial',
                                                            'Verdana, Geneva, sans-serif' => 'Verdana',
                                                            'Georgia, serif' => 'Georgia',
                                                            'Times New Roman, Times, serif' => 'Times New Roman',
                                                            'Tahoma, Geneva, sans-serif' => 'Tahoma',
                                                            'Trebuchet MS, Helvetica, sans-serif' => 'Trebuchet MS',
                                                            'Courier New, Courier, monospace' => 'Courier New',
                                                            'Roboto, sans-serif' => 'Roboto (Google)',
                                                            'Open Sans, sans-serif' => 'Open Sans (Google)',
                                                            'Lato, sans-serif' => 'Lato (Google)',
                                                            'Montserrat, sans-serif' => 'Montserrat (Google)'
                                                        ];
                                                        $selected_font = $popup_settings_logged_in['font_family'] ?? 'Arial, Helvetica, sans-serif';
                                                        foreach ($fonts as $value => $label) {
                                                            echo '<option value="' . esc_attr($value) . '"' . selected($selected_font, $value, false) . '>' . esc_html($label) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Font Size (px)</th>
                                                <td><input type="number" name="holdthisproduct_options[popup_customization_logged_in][font_size]" value="<?php echo esc_attr($popup_settings_logged_in['font_size'] ?? '16'); ?>" min="10" max="40" class="htp-input-right-align"></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Text Color</th>
                                                <td><input type="color" name="holdthisproduct_options[popup_customization_logged_in][text_color]" value="<?php echo esc_attr($popup_settings_logged_in['text_color'] ?? '#222222'); ?>" class="htp-input-right-align"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="htp-form-actions">
                        <?php submit_button( 'Save Settings', 'primary htp-save-btn', 'submit', false ); ?>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Check if there's a saved active tab after form submission
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('active_tab') || localStorage.getItem('htp_active_tab') || 'general';
            
            // Immediately set the correct tab without animation to prevent flash
            $('.htp-nav-tab').removeClass('htp-nav-tab-active');
            $('.htp-tab-content').removeClass('htp-tab-active').hide();
            
            // Set the active tab
            if (activeTab && $('#htp-' + activeTab).length) {
                $('.htp-nav-tab[data-target="' + activeTab + '"]').addClass('htp-nav-tab-active');
                $('#htp-' + activeTab).addClass('htp-tab-active').show();
            } else {
                // Fallback to general tab
                $('.htp-nav-tab[data-target="general"]').addClass('htp-nav-tab-active');
                $('#htp-general').addClass('htp-tab-active').show();
            }
            
            // Show form actions after tab is properly set
            $('.htp-form-actions').addClass('htp-ready');
            
            // Tab switching functionality
            $('.htp-nav-tab').on('click', function() {
                const target = $(this).data('target');
                
                // Save the active tab to localStorage
                localStorage.setItem('htp_active_tab', target);
                
                // Update active tab button
                $('.htp-nav-tab').removeClass('htp-nav-tab-active');
                $(this).addClass('htp-nav-tab-active');
                
                // Update active tab content with proper show/hide
                $('.htp-tab-content').removeClass('htp-tab-active').hide();
                $('#htp-' + target).addClass('htp-tab-active').show();
            });
            
            // Add hidden field to form to preserve active tab on submission
            $('.htp-settings-form').append('<input type="hidden" name="active_tab" id="htp-active-tab-field" value="' + activeTab + '">');
            
            // Update the hidden field when tabs are switched
            $('.htp-nav-tab').on('click', function() {
                $('#htp-active-tab-field').val($(this).data('target'));
            });
            
            // Popup customization sub-tabs functionality
            $('.htp-popup-tab').on('click', function(){
                var tab = $(this).data('popup-tab');
                $('.htp-popup-tab').removeClass('htp-popup-tab-active');
                $(this).addClass('htp-popup-tab-active');
                $('.htp-popup-tab-content').hide();
                $('.htp-popup-tab-content-' + tab).show();
            });
            
            // Toggle fields for logged in users
            var $toggleLoggedIn = $('input[name="holdthisproduct_options[enable_popup_customization_logged_in]"]');
            var $fieldsLoggedIn = $('.htp-popup-customization-fields-logged-in');
            $toggleLoggedIn.on('change', function(){
                if($(this).is(':checked')){
                    $fieldsLoggedIn.slideDown();
                }else{
                    $fieldsLoggedIn.slideUp();
                }
            });
            
            // Force Save Settings button styling
            function styleHTPButtons() {
                $('.htp-form-actions input[type="submit"], .htp-form-actions .button-primary, #submit').each(function() {
                    $(this).addClass('htp-styled-button');
                    $(this).css({
                        'background': '#2F89F9',
                        'border': '2px solid #2F89F9',
                        'color': '#ffffff',
                        'padding': '10px 24px',
                        'border-radius': '6px',
                        'font-weight': '700',
                        'font-size': '13px',
                        'text-transform': 'uppercase',
                        'letter-spacing': '0.5px',
                        'cursor': 'pointer',
                        'transition': 'all 0.3s ease',
                        'box-shadow': '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                    });
                });
            }
            
            // Apply styling immediately and after any DOM changes
            styleHTPButtons();
            setTimeout(styleHTPButtons, 100);
            setTimeout(styleHTPButtons, 500);
            
            // Add hover effects
            $(document).on('mouseenter', '.htp-styled-button', function() {
                $(this).css({
                    'background': '#266DED',
                    'border-color': '#266DED',
                    'transform': 'translateY(-2px)',
                    'box-shadow': '0 10px 15px -3px rgba(0, 0, 0, 0.1)'
                });
            }).on('mouseleave', '.htp-styled-button', function() {
                $(this).css({
                    'background': '#2F89F9',
                    'border-color': '#2F89F9',
                    'transform': 'translateY(0)',
                    'box-shadow': '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add product reservations list in inventory tab
     */
    public function add_product_reservations_list() {
        global $post;
        
        if ( ! $post ) return;
        
        $reservations = $this->get_product_reservations( $post->ID );
        
        echo '<div class="options_group">';
        echo '<h4 style="padding-left: 12px;">' . __( 'Active Reservations', 'hold-this-product' ) . '</h4>';
        
        if ( empty( $reservations ) ) {
            echo '<p>' . __( 'No active reservations for this product.', 'hold-this-product' ) . '</p>';
        } else {
            echo '<table class="widefat striped" style="margin-top: 10px;">';
            echo '<thead><tr>';
            echo '<th>' . __( 'Customer', 'hold-this-product' ) . '</th>';
            echo '<th>' . __( 'Expires', 'hold-this-product' ) . '</th>';
            echo '<th>' . __( 'Action', 'hold-this-product' ) . '</th>';
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
            'post_type'      => 'htp_reservation',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => array(
                array( 'key' => '_htp_status', 'value' => 'active' ),
                array( 'key' => '_htp_product_id', 'value' => $product_id ),
                array( 'key' => '_htp_expires_at', 'value' => current_time( 'timestamp' ), 'type' => 'NUMERIC', 'compare' => '>' )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ) );
    }
    
    /**
     * Display single product reservation row
     */
    private function display_product_reservation_row( $reservation ) {
        $email = get_post_meta( $reservation->ID, '_htp_email', true );
        $name = get_post_meta( $reservation->ID, '_htp_name', true );
        $surname = get_post_meta( $reservation->ID, '_htp_surname', true );
        $expires_ts = (int) get_post_meta( $reservation->ID, '_htp_expires_at', true );
        
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
        echo '<button type="button" class="button htp-cancel-reservation" ';
        echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
        echo 'data-customer="' . esc_attr( $customer ) . '">';
        echo __( 'Cancel', 'hold-this-product' );
        echo '</button>';
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Manage reservations page
     */
    public function manage_reservations_page() {
        // Get filter parameters
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';
        $search_query = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
        $search_type = isset( $_GET['search_type'] ) ? sanitize_text_field( $_GET['search_type'] ) : 'email';
        
        // Get reservations based on filters
        $reservations = $this->get_filtered_reservations( $status_filter, $search_query, $search_type );
        $active_count = $this->count_reservations_by_status( 'active' );
        $stats = $this->get_reservations_summary();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Manage Reservations', 'hold-this-product' ); ?></h1>
            
            <!-- Summary Stats -->
            <div class="htp-reservations-stats">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <div><strong>Pending Approval:</strong> <?php echo esc_html( $stats['pending_approval'] ); ?></div>
                    <div><strong>Active:</strong> <?php echo esc_html( $stats['active'] ); ?></div>
                    <div><strong>Expired:</strong> <?php echo esc_html( $stats['expired'] ); ?></div>
                    <div><strong>Cancelled:</strong> <?php echo esc_html( $stats['cancelled'] ); ?></div>
                    <div><strong>Fulfilled:</strong> <?php echo esc_html( $stats['fulfilled'] ); ?></div>
                    <div><strong>Denied:</strong> <?php echo esc_html( $stats['denied'] ); ?></div>
                    <div><strong>Total:</strong> <?php echo esc_html( $stats['total'] ); ?></div>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="tablenav top" style="margin: 20px 0;">
                <div class="alignleft actions">
                    <!-- Status Filter -->
                    <select name="status_filter" id="status-filter">
                        <option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'hold-this-product' ); ?></option>
                        <option value="pending_approval" <?php selected( $status_filter, 'pending_approval' ); ?>><?php esc_html_e( 'Pending Approval', 'hold-this-product' ); ?></option>
                        <option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'hold-this-product' ); ?></option>
                        <option value="expired" <?php selected( $status_filter, 'expired' ); ?>><?php esc_html_e( 'Expired', 'hold-this-product' ); ?></option>
                        <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'hold-this-product' ); ?></option>
                        <option value="fulfilled" <?php selected( $status_filter, 'fulfilled' ); ?>><?php esc_html_e( 'Fulfilled', 'hold-this-product' ); ?></option>
                        <option value="denied" <?php selected( $status_filter, 'denied' ); ?>><?php esc_html_e( 'Denied', 'hold-this-product' ); ?></option>
                    </select>
                    
                    <!-- Search Type -->
                    <select name="search_type" id="search-type">
                        <option value="email" <?php selected( $search_type, 'email' ); ?>><?php esc_html_e( 'Email', 'hold-this-product' ); ?></option>
                        <option value="product" <?php selected( $search_type, 'product' ); ?>><?php esc_html_e( 'Product Name', 'hold-this-product' ); ?></option>
                        <option value="product_id" <?php selected( $search_type, 'product_id' ); ?>><?php esc_html_e( 'Product ID', 'hold-this-product' ); ?></option>
                        <option value="customer_name" <?php selected( $search_type, 'customer_name' ); ?>><?php esc_html_e( 'Customer Name', 'hold-this-product' ); ?></option>
                    </select>
                    
                    <!-- Search Input -->
                    <input type="search" id="reservation-search" placeholder="<?php esc_attr_e( 'Search reservations...', 'hold-this-product' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" style="width: 200px;">
                    
                    <button type="button" class="button" id="filter-reservations"><?php esc_html_e( 'Filter', 'hold-this-product' ); ?></button>
                    <button type="button" class="button" id="clear-filters"><?php esc_html_e( 'Clear', 'hold-this-product' ); ?></button>
                </div>
                
                <div class="alignright">
                    <span class="displaying-num"><?php printf( __( '%d reservations', 'hold-this-product' ), count( $reservations ) ); ?></span>
                </div>
            </div>
            
            <?php if ( empty( $reservations ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No reservations found matching your criteria.', 'hold-this-product' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 20%;"><?php esc_html_e( 'Product', 'hold-this-product' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Customer', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Status', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Reserved', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Expires', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Time Left', 'hold-this-product' ); ?></th>
                            <th style="width: 12%;"><?php esc_html_e( 'Actions', 'hold-this-product' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $reservations as $reservation ) : ?>
                            <?php $this->display_filterable_reservation_row( $reservation ); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px;">
                    <p class="description">
                        <?php esc_html_e( 'Use the filters above to find specific reservations. Only active reservations can be cancelled.', 'hold-this-product' ); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Filter functionality
            $('#filter-reservations').on('click', function() {
                var status = $('#status-filter').val();
                var searchType = $('#search-type').val();
                var search = $('#reservation-search').val();
                
                var url = new URL(window.location);
                url.searchParams.set('status', status);
                url.searchParams.set('search_type', searchType);
                if (search) {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }
                window.location.href = url.toString();
            });
            
            // Clear filters
            $('#clear-filters').on('click', function() {
                var url = new URL(window.location);
                url.searchParams.delete('status');
                url.searchParams.delete('search');
                url.searchParams.delete('search_type');
                window.location.href = url.toString();
            });
            
            // Enter key to filter
            $('#reservation-search').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#filter-reservations').click();
                }
            });
            
            // Reservation deletion (for non-active reservations)
            $(document).on('click', '.htp-delete-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';
                
                if (confirm('Are you sure you want to permanently delete the reservation for ' + customer + ' on ' + product + '? This action cannot be undone.')) {
                    $btn.prop('disabled', true).text('Deleting...');
                    
                    $.post(ajaxurl, {
                        action: 'htp_delete_admin_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo wp_create_nonce( 'htp_admin_delete' ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            // Remove the row completely
                            $btn.closest('tr').fadeOut(function() {
                                $(this).remove();
                                
                                // Update counter if visible
                                var $statsDiv = $('.htp-reservations-stats');
                                if ($statsDiv.length > 0) {
                                    var $displayNum = $('.displaying-num');
                                    if ($displayNum.length > 0) {
                                        var currentText = $displayNum.text();
                                        var currentNum = parseInt(currentText.match(/\d+/));
                                        if (currentNum > 0) {
                                            $displayNum.text((currentNum - 1) + ' reservations');
                                        }
                                    }
                                }
                            });
                            
                            // Show success message
                            if ($('.notice.notice-success').length === 0) {
                                $('<div class="notice notice-success is-dismissible"><p>Reservation deleted successfully.</p></div>')
                                    .insertAfter('.wrap h1');
                            }
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false).text('Delete');
                        }
                    })
                    .fail(function() {
                        alert('Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Delete');
                    });
                }
            });
            
            // Reservation approval
            $(document).on('click', '.htp-approve-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';
                
                if (confirm('Are you sure you want to approve the reservation for ' + customer + ' on ' + product + '?')) {
                    $btn.prop('disabled', true).text('Approving...');
                    
                    $.post(ajaxurl, {
                        action: 'htp_approve_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo wp_create_nonce( 'htp_admin_approve' ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            // Update buttons to show cancel option
                            var $row = $btn.closest('tr');
                            var $actionsCell = $row.find('td:last-child');
                            $actionsCell.html('<button type="button" class="button button-small htp-cancel-reservation" ' +
                                'data-reservation-id="' + reservationId + '" ' +
                                'data-customer="' + customer + '" ' +
                                'data-product="' + product + '">Cancel</button>');
                            
                            // Update the status display
                            var $statusCell = $row.find('td:nth-child(3) span');
                            $statusCell.removeClass('status-pending-approval').addClass('status-active').text('Active');
                            
                            // Show success message
                            if ($('.notice.notice-success').length === 0) {
                                $('<div class="notice notice-success is-dismissible"><p>Reservation approved successfully.</p></div>')
                                    .insertAfter('.wrap h1');
                            }
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false).text('Approve');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        alert('Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Approve');
                    });
                }
            });
            
            // Reservation denial
            $(document).on('click', '.htp-deny-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';
                
                var reason = prompt('Please provide a reason for denying this reservation (optional):');
                if (reason !== null) { // User didn't cancel the prompt
                    $btn.prop('disabled', true).text('Denying...');
                    
                    $.post(ajaxurl, {
                        action: 'htp_deny_reservation',
                        reservation_id: reservationId,
                        reason: reason,
                        nonce: '<?php echo wp_create_nonce( 'htp_admin_deny' ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            // Update buttons to show delete option
                            var $row = $btn.closest('tr');
                            var $actionsCell = $row.find('td:last-child');
                            $actionsCell.html('<button type="button" class="button button-small button-link-delete htp-delete-reservation" ' +
                                'data-reservation-id="' + reservationId + '" ' +
                                'data-customer="' + customer + '" ' +
                                'data-product="' + product + '">Delete</button>');
                            
                            // Update the status display
                            var $statusCell = $row.find('td:nth-child(3) span');
                            $statusCell.removeClass('status-pending-approval').addClass('status-denied').text('Denied');
                            
                            // Clear time left column
                            $row.find('td:nth-child(6)').text('—').removeClass('time-left-critical time-left-warning');
                            
                            // Show success message
                            if ($('.notice.notice-success').length === 0) {
                                $('<div class="notice notice-success is-dismissible"><p>Reservation denied successfully.</p></div>')
                                    .insertAfter('.wrap h1');
                            }
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false).text('Deny');
                        }
                    })
                    .fail(function() {
                        alert('Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Deny');
                    });
                }
            });

            // Reservation cancellation (active reservations)
            $(document).on('click', '.htp-cancel-reservation', function() {
                var $btn = $(this);
                var reservationId = $btn.data('reservation-id');
                var customer = $btn.data('customer');
                var product = $btn.data('product') || 'this product';

                if (!reservationId) {
                    alert('Missing reservation ID.');
                    return;
                }

                if (confirm('Are you sure you want to cancel the reservation for ' + customer + ' on ' + product + '?')) {
                    $btn.prop('disabled', true).text('Cancelling...');

                    $.post(ajaxurl, {
                        action: 'htp_cancel_admin_reservation',
                        reservation_id: reservationId,
                        nonce: '<?php echo wp_create_nonce( 'htp_admin_cancel' ); ?>'
                    })
                    .done(function(response) {
                        if (response.success) {
                            // Reload so counters/statuses stay consistent.
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            $btn.prop('disabled', false).text('Cancel');
                        }
                    })
                    .fail(function() {
                        alert('Request failed. Please try again.');
                        $btn.prop('disabled', false).text('Cancel');
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get filtered reservations
     */
    private function get_filtered_reservations( $status_filter = 'all', $search_query = '', $search_type = 'email' ) {
        global $wpdb;
        
        $meta_query = array();
        $where_clause = '';
        $join_clause = '';
        
        // Status filter
        if ( $status_filter !== 'all' ) {
            $meta_query[] = array(
                'key' => '_htp_status',
                'value' => $status_filter,
                'compare' => '='
            );
        }
        
        // Search functionality
        if ( ! empty( $search_query ) ) {
            switch ( $search_type ) {
                case 'email':
                    $meta_query[] = array(
                        'key' => '_htp_email',
                        'value' => $search_query,
                        'compare' => 'LIKE'
                    );
                    break;
                    
                case 'product':
                    // Search in product titles
                    $product_ids = $wpdb->get_col( $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE %s",
                        '%' . $wpdb->esc_like( $search_query ) . '%'
                    ) );
                    
                    if ( ! empty( $product_ids ) ) {
                        $meta_query[] = array(
                            'key' => '_htp_product_id',
                            'value' => $product_ids,
                            'compare' => 'IN'
                        );
                    } else {
                        // No products found, return empty
                        return array();
                    }
                    break;
                    
                case 'product_id':
                    if ( is_numeric( $search_query ) ) {
                        $meta_query[] = array(
                            'key' => '_htp_product_id',
                            'value' => absint( $search_query ),
                            'compare' => '='
                        );
                    } else {
                        return array();
                    }
                    break;
                    
                case 'customer_name':
                    $meta_query['relation'] = 'OR';
                    $meta_query[] = array(
                        'key' => '_htp_name',
                        'value' => $search_query,
                        'compare' => 'LIKE'
                    );
                    $meta_query[] = array(
                        'key' => '_htp_surname',
                        'value' => $search_query,
                        'compare' => 'LIKE'
                    );
                    break;
            }
        }
        
        $args = array(
            'post_type' => 'htp_reservation',
            'post_status' => 'publish',
            'posts_per_page' => 100, // Limit for performance
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }
        
        return get_posts( $args );
    }
    
    /**
     * Count reservations by status
     */
    private function count_reservations_by_status( $status ) {
        global $wpdb;
        
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'htp_reservation' 
            AND p.post_status = 'publish'
            AND pm.meta_key = '_htp_status' 
            AND pm.meta_value = %s",
            $status
        ) );
    }
    
    /**
     * Get reservations summary
     */
    private function get_reservations_summary() {
        global $wpdb;
        
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'htp_reservation' AND post_status = 'publish'"
        );
        
        return array(
            'total' => (int) $total,
            'pending_approval' => (int) $this->count_reservations_by_status( 'pending_approval' ),
            'active' => (int) $this->count_reservations_by_status( 'active' ),
            'expired' => (int) $this->count_reservations_by_status( 'expired' ),
            'cancelled' => (int) $this->count_reservations_by_status( 'cancelled' ),
            'fulfilled' => (int) $this->count_reservations_by_status( 'fulfilled' ),
            'denied' => (int) $this->count_reservations_by_status( 'denied' )
        );
    }
    
    /**
     * Display filterable reservation row (includes all statuses)
     */
    private function display_filterable_reservation_row( $reservation ) {
        $product_id = (int) get_post_meta( $reservation->ID, '_htp_product_id', true );
        $email = get_post_meta( $reservation->ID, '_htp_email', true );
        $name = get_post_meta( $reservation->ID, '_htp_name', true );
        $surname = get_post_meta( $reservation->ID, '_htp_surname', true );
        $expires_ts = (int) get_post_meta( $reservation->ID, '_htp_expires_at', true );
        $status = get_post_meta( $reservation->ID, '_htp_status', true );
        
        $product = wc_get_product( $product_id );
        $product_name = $product ? $product->get_name() : 'Unknown Product (ID: ' . $product_id . ')';
        $product_edit_url = $product ? admin_url( 'post.php?post=' . $product_id . '&action=edit' ) : '#';
        
        // Determine customer display name
        if ( $reservation->post_author ) {
            $user = get_userdata( $reservation->post_author );
            $customer = $user ? $user->display_name . ' (' . $user->user_email . ')' : 'Unknown User';
            $customer_short = $user ? $user->display_name : 'Unknown User';
        } else {
            $customer_full = trim( $name . ' ' . $surname );
            if ( empty( $customer_full ) ) {
                $customer = $email ?: __('No email', 'hold-this-product');
                $customer_short = $email ?: __('No email', 'hold-this-product');
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
        if ( $expires_ts && $status === 'active' ) {
            $diff = $expires_ts - current_time( 'timestamp' );
            if ( $diff > 0 ) {
                $days = floor( $diff / DAY_IN_SECONDS );
                $hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
                $minutes = floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
                
                if ( $days > 0 ) {
                    $time_left = sprintf( '%dd %dh', $days, $hours );
                } elseif ( $hours > 0 ) {
                    $time_left = sprintf( '%dh %dm', $hours, $minutes );
                } else {
                    $time_left = sprintf( '%dm', $minutes );
                }
                
                // Add warning colors
                if ( $diff < 2 * HOUR_IN_SECONDS ) {
                    $time_class = 'time-left-critical';
                } elseif ( $diff < 6 * HOUR_IN_SECONDS ) {
                    $time_class = 'time-left-warning';
                }
            } else {
                $time_left = 'Expired';
                $time_class = 'time-left-critical';
            }
        } else {
            $time_left = '—';
        }
        
        // Status display with color
        $status_class = 'status-' . str_replace('_', '-', $status);
        $status_display = str_replace('_', ' ', ucfirst( $status ));
        
        echo '<tr>';
        echo '<td>';
        if ( $product ) {
            echo '<a href="' . esc_url( $product_edit_url ) . '" target="_blank">' . esc_html( $product_name ) . '</a>';
        } else {
            echo esc_html( $product_name );
        }
        echo '</td>';
        echo '<td title="' . esc_attr( $customer ) . '">' . esc_html( $customer ) . '</td>';
        echo '<td><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_display ) . '</span></td>';
        echo '<td>' . esc_html( $reserved_date ) . '</td>';
        echo '<td>' . esc_html( $expires_disp ) . '</td>';
        echo '<td class="' . esc_attr( $time_class ) . '">' . esc_html( $time_left ) . '</td>';
        echo '<td>';
        
        // Show appropriate action buttons based on status
        if ( $status === 'pending_approval' ) {
            // Show approve and deny buttons for pending reservations
            echo '<button type="button" class="button button-small htp-approve-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '" style="margin-right: 5px;">';
            echo __( 'Approve', 'hold-this-product' );
            echo '</button>';
            
            echo '<button type="button" class="button button-small button-link-delete htp-deny-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo __( 'Deny', 'hold-this-product' );
            echo '</button>';
        } elseif ( $status === 'active' ) {
            echo '<button type="button" class="button button-small htp-cancel-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo __( 'Cancel', 'hold-this-product' );
            echo '</button>';
        } else {
            // Show delete button for non-active reservations
            echo '<button type="button" class="button button-small button-link-delete htp-delete-reservation" ';
            echo 'data-reservation-id="' . esc_attr( $reservation->ID ) . '" ';
            echo 'data-customer="' . esc_attr( $customer_short ) . '" ';
            echo 'data-product="' . esc_attr( $product_name ) . '">';
            echo __( 'Delete', 'hold-this-product' );
            echo '</button>';
        }
        
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
        
        check_ajax_referer( 'htp_admin_cancel', 'nonce' );
        
        $reservation_id = absint( $_POST['reservation_id'] ?? 0 );
        
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }
        
        // Verify this is an active reservation
        $status = get_post_meta( $reservation_id, '_htp_status', true );
        if ( $status !== 'active' ) {
            wp_send_json_error( 'Reservation is not active.' );
        }
        
        // Cancel the reservation using existing method
        $reservations_class = new HTP_Reservations();
        $reservations_class->cancel_reservation( $reservation_id );
        
        // Add admin note
        update_post_meta( $reservation_id, '_htp_cancelled_by_admin', current_time( 'timestamp' ) );
        update_post_meta( $reservation_id, '_htp_cancelled_by_user', get_current_user_id() );
        
        wp_send_json_success( 'Reservation cancelled successfully.' );
    }
    
    /**
     * Handle admin reservation deletion
     */
    public function handle_admin_delete_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        check_ajax_referer( 'htp_admin_delete', 'nonce' );
        
        $reservation_id = absint( $_POST['reservation_id'] ?? 0 );
        
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }
        
        // Verify this is not an active reservation (should not delete active ones)
        $status = get_post_meta( $reservation_id, '_htp_status', true );
        if ( $status === 'active' ) {
            wp_send_json_error( 'Cannot delete active reservations. Cancel them first.' );
        }
        
        // Delete the reservation post
        $result = wp_delete_post( $reservation_id, true );
        
        if ( $result ) {
            wp_send_json_success( 'Reservation deleted successfully.' );
        } else {
            wp_send_json_error( 'Failed to delete reservation.' );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        // `admin_enqueue_scripts` provides a hook suffix, but it can vary depending on menu nesting.
        // The `page` query arg is stable for our plugin pages, so use it as a fallback.
        $page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

        // Settings page scripts
        if ( $hook === 'toplevel_page_holdthisproduct-settings' || $page === 'holdthisproduct-settings' ) {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_style( 'wp-components' );
            wp_enqueue_script( 'wp-components' );
            wp_enqueue_style( 'holdthisproduct-admin-style', HTP_PLUGIN_URL . 'assets/css/admin-style.css', array(), HTP_VERSION );
            
            wp_add_inline_script( 'wp-components', $this->get_admin_inline_script() );
        }
        
        // Manage reservations page scripts
        // The hook suffix for submenu pages is based on the parent slug.
        // Be tolerant here so scripts/styles load regardless of WP's generated suffix.
        if (
            $hook === 'holdthisproduct_page_holdthisproduct-manage-reservations'
            || $hook === 'holdthisproduct-settings_page_holdthisproduct-manage-reservations'
            || $page === 'holdthisproduct-manage-reservations'
        ) {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_style( 'holdthisproduct-admin-style', HTP_PLUGIN_URL . 'assets/css/admin-style.css', array(), HTP_VERSION );
        }
        
        // Product edit page scripts
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            global $post;
            if ( $post && $post->post_type === 'product' ) {
                wp_enqueue_script( 'jquery' );
                wp_add_inline_script( 'jquery', $this->get_product_page_script() );
            }
        }
        
        // Product list toggle feature removed in free version
    }
    
    /**
     * Get manage reservations page inline script
     */
    private function get_manage_reservations_inline_script() {
        $nonce = wp_create_nonce( 'htp_admin_cancel' );
        return "
            jQuery(document).ready(function($) {
                $('.htp-cancel-reservation').on('click', function() {
                    var \$btn = $(this);
                    var reservationId = \$btn.data('reservation-id');
                    var customer = \$btn.data('customer');
                    var product = \$btn.data('product') || 'this product';
                    
                    if (confirm('Are you sure you want to cancel the reservation for ' + customer + ' on ' + product + '?')) {
                        \$btn.prop('disabled', true).text('Cancelling...');
                        
                        $.post(ajaxurl, {
                            action: 'htp_cancel_admin_reservation',
                            reservation_id: reservationId,
                            nonce: '{$nonce}'
                        })
                        .done(function(response) {
                            if (response.success) {
                                \$btn.closest('tr').fadeOut(function() {
                                    $(this).remove();
                                });
                                
                                // Update counter
                                var currentCount = $('.htp-reservations-stats p strong').text().match(/\d+/);
                                if (currentCount) {
                                    var newCount = parseInt(currentCount[0]) - 1;
                                    $('.htp-reservations-stats p strong').html('Total Active Reservations: ' + newCount);
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
                    $('#holdthisproduct-max-reservations-wrapper').toggle(
                        $('input[name=\"holdthisproduct_options[enable_reservation]\"]').is(':checked')
                    );
                }
                
                toggleMaxReservations();
                
                $('input[name=\"holdthisproduct_options[enable_reservation]\"]').on('change', function() {
                    toggleMaxReservations();
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
                $('.htp-cancel-reservation').on('click', function() {
                    var \$btn = $(this);
                    var reservationId = \$btn.data('reservation-id');
                    var customer = \$btn.data('customer');
                    
                    if (confirm('Are you sure you want to cancel the reservation for ' + customer + '?')) {
                        \$btn.prop('disabled', true).text('Cancelling...');
                        
                        $.post(ajaxurl, {
                            action: 'htp_cancel_admin_reservation',
                            reservation_id: reservationId,
                            nonce: '" . wp_create_nonce( 'htp_admin_cancel' ) . "'
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
     * Admin toggle feature removed in free version
     */
    public function init_products_list() {
        // Feature removed in free version
    }
    
    /**
     * Handle reservation approval
     */
    public function handle_approve_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        check_ajax_referer( 'htp_admin_approve', 'nonce' );
        
        $reservation_id = absint( $_POST['reservation_id'] ?? 0 );
        
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }
        
        // Check if reservation exists
        $post = get_post( $reservation_id );
        if ( ! $post || $post->post_type !== 'htp_reservation' ) {
            wp_send_json_error( 'Invalid reservation.' );
        }
        
        if ( ! class_exists( 'HTP_Reservations' ) ) {
            wp_send_json_error( 'Reservations class not found.' );
        }
        
        $reservations_class = new HTP_Reservations();
        $result = $reservations_class->approve_reservation( $reservation_id );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } elseif ( $result ) {
            wp_send_json_success( 'Reservation approved successfully.' );
        } else {
            wp_send_json_error( 'Failed to approve reservation.' );
        }
    }
    
    /**
     * Handle reservation denial
     */
    public function handle_deny_reservation() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        check_ajax_referer( 'htp_admin_deny', 'nonce' );
        
        $reservation_id = absint( $_POST['reservation_id'] ?? 0 );
        $reason = sanitize_text_field( $_POST['reason'] ?? '' );
        
        if ( ! $reservation_id ) {
            wp_send_json_error( 'Invalid reservation ID.' );
        }
        
        // Check if reservation exists
        $post = get_post( $reservation_id );
        if ( ! $post || $post->post_type !== 'htp_reservation' ) {
            wp_send_json_error( 'Invalid reservation.' );
        }
        
        if ( ! class_exists( 'HTP_Reservations' ) ) {
            wp_send_json_error( 'Reservations class not found.' );
        }
        
        $reservations_class = new HTP_Reservations();
        $result = $reservations_class->deny_reservation( $reservation_id, $reason );
        
        if ( $result ) {
            wp_send_json_success( 'Reservation denied successfully.' );
        } else {
            wp_send_json_error( 'Failed to deny reservation.' );
        }
    }
}
