<?php

/**
 * Plugin Name:       Hold My Product
 * Plugin URI:        https://github.com/Flavius-Ciortan/HoldMyProduct
 * Description:       Allows WooCommerce customers to reserve products for a limited time before purchase.
 * Version:           1.0.0
 * Author:            Flavius Ciortan, Anghel Emanuel.
 * Author URI:        https://github.com/Flavius-Ciortan
 * Text Domain:       hold-my-product
 * Domain Path:       /languages
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * This file is part of Hold My Product.
 *
 * Hold My Product is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Hold My Product is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) exit;


if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'class-holdmyproduct-admin-reservation.php';
}


//  Încarcă fișierul CSS din plugin (frontend)


function holdmyproduct_enqueue_styles() {
    // Rulează doar în frontend
    if (!is_admin()) {
        wp_enqueue_style(
            'holdmyproduct-style',
            plugin_dir_url(__FILE__) . 'style.css',
            array(),
            '1.0'
        );

        wp_enqueue_style( 'wp-jquery-ui-dialog' );    
    }
}
add_action('wp_enqueue_scripts', 'holdmyproduct_enqueue_styles');


//  Afișează formularul doar pe paginile de produs WooCommerce


function holdmyproduct_display_form() {

    if ( ! is_product() ) {
        return;
    }

    global $product;
    if ( ! $product ) {
        return;
    }

    // If reservations are disabled for this product: don't render the button/modal

    if ( function_exists( 'holdmyproduct_is_product_reservable' )
         && ! holdmyproduct_is_product_reservable( $product->get_id() ) ) {

        // Optional note for users:

        echo '<p class="hmp-reserve-unavailable" style="margin-top:8px;">Reservations are not available for this product.</p>';
        return;
    }

    // Allowed → render the template with the button + modal


    include plugin_dir_path( __FILE__ ) . 'form_template.php';
}




// Îl adăugăm sub butonul „Adaugă în coș”

add_action('woocommerce_after_add_to_cart_form', 'holdmyproduct_display_form');


function holdmyproduct_enqueue_scripts() {
    if (!is_admin()) {
        wp_enqueue_script(
            'holdmyproduct-js',
            plugin_dir_url(__FILE__) . 'holdmyproduct.js',
            array( 'jquery', 'jquery-ui-dialog' ),
            '1.1',
            true
        );

        // Trimite la JS adresa AJAX și nonce pentru securitate


        wp_localize_script('holdmyproduct-js', 'holdmyproduct_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('holdmyproduct_nonce'),
            'is_logged_in' => is_user_logged_in() ? 1 : 0,
        ));
    }
}
add_action('wp_enqueue_scripts', 'holdmyproduct_enqueue_scripts');

// Aici ar trebui verificat de ce nu se decodeaza email-ul 

function holdmyproduct_handle_reservation() {

    // Normalize payload so we can see fields even if the client sent `data=...` as a single string
    
$payload = $_POST;

    check_ajax_referer('holdmyproduct_nonce', 'security');

    $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    if ( ! $product_id ) {
        wp_send_json_error('Invalid product ID.');
    }

    // Identify reserver (user or guest email)
    if ( is_user_logged_in() ) {
    $user_id     = get_current_user_id();
    $guest_email = wp_get_current_user()->user_email ?: '';
} else {
    $user_id   = 0;



    // Accept common names, then decode + sanitize
    $raw_email = $payload['email']
        ?? $payload['user_email']
        ?? $payload['reservation_email']
        ?? '';

    $raw_email   = is_string( $raw_email ) ? urldecode( wp_unslash( $raw_email ) ) : '';
    // $guest_email = sanitize_email( $raw_email );
    $guest_email =  $raw_email;

    if ( empty( $guest_email ) || ! is_email( $guest_email ) ) {
        wp_send_json_error( 'Please provide an email address.' );
    }
}



    // Block if product-level or global logic says not reservable
    if ( function_exists( 'holdmyproduct_is_product_reservable' )
         && ! holdmyproduct_is_product_reservable( $product_id ) ) {
        wp_send_json_error( 'Reservations are disabled for this product.' );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( 'Product not found.' );
    }

    if ( ! $product->managing_stock() ) {
        wp_send_json_error( 'Product stock is not managed.' );
    }

    $stock_quantity = (int) $product->get_stock_quantity();
    if ( $stock_quantity <= 0 ) {
        wp_send_json_error( 'No stock available.' );
    }

    // Per-user max + duplicate product check
    $limit  = hmp_max_reservations_per_user();
    $active = hmp_count_active_reservations( $user_id, $guest_email );
    if ( $active >= $limit ) {
        wp_send_json_error( sprintf( 'You have reached the maximum of %d active reservations.', $limit ) );
    }
    if ( hmp_user_has_active_res_for_product( $product_id, $user_id, $guest_email ) ) {
        wp_send_json_error( 'You already have an active reservation for this product.' );
    }

    // Decrease stock by 1
    $product->set_stock_quantity( max( 0, $stock_quantity - 1 ) );
    $product->save();

    // 24h expiry, WP timezone-aware
    $now        = current_time( 'timestamp' );
    $expires_at = $now + DAY_IN_SECONDS;

    // Optional extra fields from form
    $guest_name  = isset($_POST['name'])  ? sanitize_text_field($_POST['name'])  : '';
    $guest_phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';

    // Create reservation CPT
    $res_id = wp_insert_post( [
        'post_type'   => 'hmp_reservation',
        'post_title'  => 'Reservation for product ' . $product_id,
        'post_status' => 'publish',
        'post_author' => $user_id ?: 0,
    ] );

    if ( $res_id && ! is_wp_error( $res_id ) ) {
        update_post_meta( $res_id, '_hmp_product_id', (int) $product_id );
        update_post_meta( $res_id, '_hmp_status', 'active' );           // active|expired|cancelled|fulfilled
        update_post_meta( $res_id, '_hmp_expires_at', (int) $expires_at );
        update_post_meta( $res_id, '_hmp_qty', 1 );

        if ( $guest_name )  update_post_meta( $res_id, '_hmp_name',  $guest_name );
        if ( $guest_email ) update_post_meta( $res_id, '_hmp_email', $guest_email );
        if ( $guest_phone ) update_post_meta( $res_id, '_hmp_phone', $guest_phone );
    } else {
        // roll back stock if insert failed
        $product->set_stock_quantity( $stock_quantity );
        $product->save();
        wp_send_json_error( 'Could not create reservation.' );
    }

    wp_send_json_success( 'Stock updated.' );
}

add_action('wp_ajax_holdmyproduct_reserve', 'holdmyproduct_handle_reservation');
add_action('wp_ajax_nopriv_holdmyproduct_reserve', 'holdmyproduct_handle_reservation');

// 1. Hook to add the settings menu page
add_action('admin_menu', 'holdmyproduct_add_admin_menu');

function holdmyproduct_add_admin_menu() {
    add_menu_page(
        'HoldMyProduct Settings',    // Page title
        'HoldMyProduct',             // Menu title
        'manage_options',            // Capability
        'holdmyproduct-settings',    // Menu slug
        'holdmyproduct_settings_page', // Callback to display content
        // 'dashicons-products',        // Icon
        plugin_dir_url( __FILE__ ) . 'HMP-menu-icon.png',
        80                          // Position
    );
}

// 2. Register settings, sections, and fields
add_action('admin_init', 'holdmyproduct_settings_init');

function holdmyproduct_settings_init() {
    // Register a setting
    register_setting('holdmyproduct_options_group', 'holdmyproduct_options');

    // Add a section in the settings page
    add_settings_section(
        'holdmyproduct_settings_section',
        'General Settings',
        'holdmyproduct_settings_section_cb',
        'holdmyproduct-settings'
    );

    // Add a field for enabling/disabling the reservation form
    add_settings_field(
        'holdmyproduct_enable_reservation',
        'Enable Reservation',
        'holdmyproduct_enable_reservation_cb',
        'holdmyproduct-settings',
        'holdmyproduct_settings_section'
    );

    // Add another field for max reservations per user (example)
    add_settings_field(
        'holdmyproduct_max_reservations',
        'Max Reservations Per User',
        'holdmyproduct_max_reservations_cb',
        'holdmyproduct-settings',
        'holdmyproduct_settings_section'
    );
    // Show per-product toggle in Products list (admin-only UI)
    add_settings_field(
        'holdmyproduct_show_admin_toggle',
        'Show Admin Toggle (Products list)',
        'holdmyproduct_show_admin_toggle_cb',
        'holdmyproduct-settings',
        'holdmyproduct_settings_section'
    );

}

add_action('admin_init', function () {
    if ( ! class_exists('WooCommerce') ) return;
    if ( ! hmp_show_admin_toggle_enabled() ) return; // OFF → no column, no scripts



    //  Add "Reservations" column to Products list

    add_filter('manage_edit-product_columns', function ($cols) {
        $new = [];
        $inserted = false;
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'sku') {
                $new['hmp_reservations'] = __('Reservations', 'holdmyproduct');
                $inserted = true;
            }
        }
        if (!$inserted) {
            $new['hmp_reservations'] = __('Reservations', 'holdmyproduct');
        }
        return $new;
    });



    //  Enqueue JS/CSS on Products list only


    add_action('admin_enqueue_scripts', function ($hook) {
        if ($hook !== 'edit.php' || ( $_GET['post_type'] ?? '' ) !== 'product') return;

        $base_dir = plugin_dir_path(__FILE__);
        $base_url = plugin_dir_url(__FILE__);

        // JS (adjust path if needed)
        $js_rel  = 'hmp-res-toggle.js';
        $js_file = $base_dir . $js_rel;
        wp_enqueue_script(
            'hmp-res-toggle',
            $base_url . $js_rel,
            ['jquery'],
            file_exists($js_file) ? filemtime($js_file) : '1.0',
            true
        );
        wp_localize_script('hmp-res-toggle', 'hmpResToggle', [
            'ajax'    => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('hmp_toggle_res'),
            'enable'  => __('Enable', 'holdmyproduct'),
            'disable' => __('Disable', 'holdmyproduct'),
        ]);

        // Admin CSS
        $css_rel  = 'admin-style.css';
        $css_file = $base_dir . $css_rel;
        wp_enqueue_style(
            'holdmyproduct-admin-style',
            $base_url . $css_rel,
            [],
            file_exists($css_file) ? filemtime($css_file) : '1.0'
        );
    });



    //  AJAX handler (can stay always-on, but harmless if UI hidden)


    add_action('wp_ajax_hmp_toggle_res', function () {
        if ( ! current_user_can('edit_products') ) {
            wp_send_json_error(['message' => __('Forbidden', 'holdmyproduct')], 403);
        }
        check_ajax_referer('hmp_toggle_res', 'nonce');

        $pid = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $new = (isset($_POST['new']) && $_POST['new'] === 'yes') ? 'yes' : 'no';
        if (!$pid) {
            wp_send_json_error(['message' => __('Invalid product', 'holdmyproduct')], 400);
        }

        update_post_meta($pid, '_hmp_reservations_enabled', $new);

        wp_send_json_success([
            'new'   => $new,
            'label' => ($new === 'yes') ? __('Enabled', 'holdmyproduct') : __('Disabled', 'holdmyproduct'),
        ]);
    });

});



// Add/remove the Reservations column based on the admin-only switch


add_filter('manage_edit-product_columns', function ($cols) {
    if ( hmp_show_admin_toggle_enabled() ) {
        // Ensure the column exists (insert after SKU)
        if ( ! isset($cols['hmp_reservations']) ) {
            $new = []; $inserted = false;
            foreach ($cols as $key => $label) {
                $new[$key] = $label;
                if ($key === 'sku') {
                    $new['hmp_reservations'] = __('Reservations', 'holdmyproduct');
                    $inserted = true;
                }
            }
            if ( ! $inserted ) {
                $new['hmp_reservations'] = __('Reservations', 'holdmyproduct');
            }
            return $new;
        }
        return $cols;
    } else {
        // Switch OFF → remove the column header entirely
        if ( isset($cols['hmp_reservations']) ) {
            unset($cols['hmp_reservations']);
        }
        return $cols;
    }
}, 999);



// Section callback
function holdmyproduct_settings_section_cb() {
    echo '<p>Configure the HoldMyProduct plugin settings below.</p>';
}

// Field callbacks
function holdmyproduct_enable_reservation_cb() {
    $options = get_option('holdmyproduct_options');
    $checked = isset($options['enable_reservation']) && $options['enable_reservation'] ? 'checked' : '';
    ?>
    <label class="toggle-switch">
        <input type="checkbox" name="holdmyproduct_options[enable_reservation]" value="1" <?php echo $checked; ?>>
        <span class="slider"></span>
    </label>
    <?php
}

function holdmyproduct_max_reservations_cb() {
    $options = get_option('holdmyproduct_options');
    $value = isset($options['max_reservations']) ? intval($options['max_reservations']) : 1;
    ?>
    <div id="holdmyproduct-max-reservations-wrapper">
        <input type="number" min="1" name="holdmyproduct_options[max_reservations]" value="<?php echo esc_attr($value); ?>" class="holdmyproduct-small-input" />
    </div>
    <?php
}
function holdmyproduct_show_admin_toggle_cb() {
    $options = get_option('holdmyproduct_options');
    $checked = !empty($options['show_admin_toggle']) ? 'checked' : '';
    ?>
    <label class="toggle-switch">
        <input type="checkbox" name="holdmyproduct_options[show_admin_toggle]" value="1" <?php echo $checked; ?>>
        <span class="slider"></span>
    </label>
    <?php
}


// 3. The settings page HTML
function holdmyproduct_settings_page() {
    ?>
    <div class="wrap">
        <h1>HoldMyProduct Settings</h1>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active">General Settings</a>
            <a href="#logged-in" class="nav-tab">Logged In Users</a>
            <a href="#logged-out" class="nav-tab">Logged Out Users</a>
        </h2>

        <!-- Tab Content -->
        <div id="general" class="tab-content active">
            <form method="post" action="options.php">
                <?php
                settings_fields('holdmyproduct_options_group');
                do_settings_sections('holdmyproduct-settings');
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

$options = get_option('holdmyproduct_options');

// Check if reservation is enabled
if ( isset($options['enable_reservation']) && $options['enable_reservation'] ) {
    // Your reservation logic here
}

// Get max reservations
$max_res = isset($options['max_reservations']) ? intval($options['max_reservations']) : 1;

add_action('admin_enqueue_scripts', 'holdmyproduct_admin_enqueue_scripts');

function holdmyproduct_admin_enqueue_scripts($hook) {
    // Only load on HoldMyProduct settings page
    if ($hook !== 'toplevel_page_holdmyproduct-settings') {
        return;
    }

    // Enqueue WP Components (for nice UI styles)
    wp_enqueue_style('wp-components');
    wp_enqueue_script('wp-components');

    // Enqueue your custom admin CSS
    wp_enqueue_style('holdmyproduct-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css', [], '1.0');

    // Add inline JS for toggle behavior
    wp_add_inline_script('wp-components', "
        jQuery(document).ready(function($) {
            function toggleMaxReservations() {
                if ($('input[name=\"holdmyproduct_options[enable_reservation]\"]').is(':checked')) {
                    $('#holdmyproduct-max-reservations-wrapper').show();
                } else {
                    $('#holdmyproduct-max-reservations-wrapper').hide();
                }
            }
            toggleMaxReservations(); // Initial check on page load

            $('input[name=\"holdmyproduct_options[enable_reservation]\"]').on('change', function() {
                toggleMaxReservations();
            });
        });
    ");
    
    wp_add_inline_script('wp-components', "
    jQuery(document).ready(function($) {
        $('.nav-tab').click(function(e) {
            e.preventDefault();

            // Remove active classes
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');

            // Add active classes to clicked tab and its content
            $(this).addClass('nav-tab-active');
            $($(this).attr('href')).addClass('active');
        });

        // Keep 'Max Reservations' row hidden if toggle is off
        function toggleMaxReservationsRow() {
            const isChecked = $('input[name=\"holdmyproduct_options[enable_reservation]\"]').is(':checked');
            const row = $('#holdmyproduct_options\\\\[max_reservations\\\\]_field');
            row.toggle(isChecked);
        }

        toggleMaxReservationsRow();
        $('input[name=\"holdmyproduct_options[enable_reservation]\"]').on('change', toggleMaxReservationsRow);
    });
");

}



// Register a minimal CPT to record reservations
add_action('init', function () {
    register_post_type('hmp_reservation', [
        'labels' => ['name' => 'Reservations'],
        'public' => false,
        'show_ui' => false,        // keep it out of admin menus
        'supports' => ['title', 'author'],
        'capability_type' => 'post',
    ]);
});



// Add menu item
add_filter('woocommerce_account_menu_items', function ($items) {
    // Insert before "Logout" (or wherever you want)
    $new = [];
    foreach ($items as $key => $label) {
        if ($key === 'customer-logout') {
            $new['hmp-reservations'] = __('Reservations', 'holdmyproduct');
        }
        $new[$key] = $label;
    }
    if (!isset($new['hmp-reservations'])) {
        $new['hmp-reservations'] = __('Reservations', 'holdmyproduct');
    }
    return $new;
});



// Register endpoint /my-account/hmp-reservations/
add_action('init', function () {
    add_rewrite_endpoint('hmp-reservations', EP_ROOT | EP_PAGES);
});


// Endpoint content hook: woocommerce_account_{endpoint}_endpoint


add_action('woocommerce_account_hmp-reservations_endpoint', function () {

    if (!is_user_logged_in()) {
        echo '<p>' . esc_html__('Please log in to see your reservations.', 'holdmyproduct') . '</p>';
        return;
    }

    $user_id = get_current_user_id();

    // Query active reservations for current user

    $q = new WP_Query([
        'post_type'      => 'hmp_reservation',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => 20,
        'meta_query'     => [
            [
                'key'   => '_hmp_status',
                'value' => 'active',
            ],
        ],
        'no_found_rows'  => true,
    ]);

    if (!$q->have_posts()) {
        echo '<p>' . esc_html__('You have no active reservations.', 'holdmyproduct') . '</p>';
        return;
    }

    echo '<table class="shop_table shop_table_responsive my_account_reservations">';
    echo '<thead><tr>
            <th>' . esc_html__('Product', 'holdmyproduct') . '</th>
            <th>' . esc_html__('Expires', 'holdmyproduct') . '</th>
            <th>' . esc_html__('Actions', 'holdmyproduct') . '</th>
          </tr></thead><tbody>';

    while ($q->have_posts()) {
        $q->the_post();
        $res_id     = get_the_ID();
        $product_id = (int) get_post_meta($res_id, '_hmp_product_id', true);
        $expires_ts = (int) get_post_meta($res_id, '_hmp_expires_at', true);

        $product = wc_get_product($product_id);
        if (!$product) {

            continue;
        }

        $product_link = get_permalink($product_id);
        $product_name = $product->get_name();
        $expires_disp = $expires_ts ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires_ts) : '—';

        // Actions: Add to cart, Cancel

        $add_to_cart_url = esc_url(wc_get_cart_url() . '?add-to-cart=' . $product_id);
        $cancel_url      = wp_nonce_url(
            add_query_arg(['hmp_cancel_res' => $res_id]),
            'hmp_cancel_res_' . $res_id
        );

        echo '<tr>';
        echo '<td data-title="' . esc_attr__('Product', 'holdmyproduct') . '"><a href="' . esc_url($product_link) . '">' . esc_html($product_name) . '</a></td>';
        echo '<td data-title="' . esc_attr__('Expires', 'holdmyproduct') . '">' . esc_html($expires_disp) . '</td>';
        echo '<td data-title="' . esc_attr__('Actions', 'holdmyproduct') . '">
                <a class="button" href="' . $add_to_cart_url . '">' . esc_html__('Add to cart', 'holdmyproduct') . '</a>
                <a class="button cancel hmp-cancel-res" href="' . esc_url($cancel_url) . '">' . esc_html__('Cancel', 'holdmyproduct') . '</a>
              </td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    wp_reset_postdata();
});


// Handle cancel clicks via nonce-protected GET


add_action('template_redirect', function () {
    if (!is_user_logged_in()) return;

    if (isset($_GET['hmp_cancel_res'])) {
        $res_id = absint($_GET['hmp_cancel_res']);
        if (!$res_id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'hmp_cancel_res_' . $res_id)) {
            return;
        }

        $post = get_post($res_id);
        if (!$post || (int)$post->post_author !== get_current_user_id()) {
            return;
        }

        // Restore stock (simple example: +1)

        $product_id = (int) get_post_meta($res_id, '_hmp_product_id', true);
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->managing_stock()) {
                $qty = max(0, (int)$product->get_stock_quantity()) + 1;
                $product->set_stock_quantity($qty);
                $product->save();
            }
        }

        update_post_meta($res_id, '_hmp_status', 'cancelled');

        // Redirect to avoid repeat

        wp_safe_redirect(wc_get_account_endpoint_url('hmp-reservations'));
        exit;
    }
});

// (Optional) Auto-expire on display

add_action('init', function () {
    $expired = new WP_Query([
        'post_type'     => 'hmp_reservation',
        'post_status'   => 'publish',
        'meta_query'    => [
            [
                'key'     => '_hmp_status',
                'value'   => 'active',
            ],
            [
                'key'     => '_hmp_expires_at',
                'value'   => time(),
                'type'    => 'NUMERIC',
                'compare' => '<',
            ],
        ],
        'fields'        => 'ids',
        'no_found_rows' => true,
        'posts_per_page'=> 100,
    ]);
    foreach ($expired->posts as $res_id) {
        update_post_meta($res_id, '_hmp_status', 'expired');
        // Optionally restore stock here as well
    }
});


// === HMP: Helpers for Enable/Disable reservation button ===
    if ( ! function_exists( 'holdmyproduct_is_product_reservable' ) ) {
        function holdmyproduct_is_product_reservable( $product_id ) {
            $val = get_post_meta( $product_id, '_hmp_reservations_enabled', true );
            return ( $val === 'yes' ); // change to ($val !== 'no') to make ON by default
        }
    }

/// Helpers for admin settings

function hmp_show_admin_toggle_enabled(): bool {
    $opts = get_option('holdmyproduct_options');
    return !empty($opts['show_admin_toggle']);
}


function hmp_reservations_globally_enabled(): bool {
    $opts = get_option('holdmyproduct_options');
    return ! empty( $opts['enable_reservation'] );
}

function holdmyproduct_is_product_reservable( $product_id ): bool {
    if ( ! hmp_reservations_globally_enabled() ) return false;   // global off
    $val = get_post_meta( $product_id, '_hmp_reservations_enabled', true );
    return ( $val === 'yes' );
}

// Read the global max from your settings (defaults to 1)
function hmp_max_reservations_per_user(): int {
    $opts = get_option('holdmyproduct_options');
    $n = isset($opts['max_reservations']) ? (int) $opts['max_reservations'] : 1;
    return max(1, $n);
}

// Count active reservations for a user (logged-in) or a guest email
function hmp_count_active_reservations( int $user_id = 0, string $email = '' ): int {
    $meta = [
        [ 'key' => '_hmp_status', 'value' => 'active' ],
    ];

    // Optional: also ensure not expired if you store _hmp_expires_at
    
    $now = current_time('timestamp');
    $meta[] = [ 'key' => '_hmp_expires_at', 'value' => $now, 'type' => 'NUMERIC', 'compare' => '>' ];

    // filter by user or guest email
    $args = [
        'post_type'      => 'hmp_reservation',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'posts_per_page' => -1,
        'meta_query'     => $meta,
    ];

    if ( $user_id > 0 ) {
        $args['author'] = $user_id;
    } elseif ( $email !== '' ) {
        $meta[] = [ 'key' => '_hmp_email', 'value' => $email ];
        $args['meta_query'] = $meta;
    } else {
        return 0; // no identity
    }

    $q = new WP_Query($args);
    return is_wp_error($q) ? 0 : count($q->posts);
}

// Does this user (or guest email) already have an active reservation for THIS product?
function hmp_user_has_active_res_for_product( int $product_id, int $user_id = 0, string $email = '' ): bool {
    $meta = [
        [ 'key' => '_hmp_status', 'value' => 'active' ],
        [ 'key' => '_hmp_product_id', 'value' => $product_id ],
        [ 'key' => '_hmp_expires_at', 'value' => current_time('timestamp'), 'type' => 'NUMERIC', 'compare' => '>' ],
    ];

    if ( $user_id > 0 ) {
        $args = [
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'posts_per_page' => 1,
            'meta_query'     => $meta,
        ];
    } elseif ( $email !== '' ) {
        $meta[] = [ 'key' => '_hmp_email', 'value' => $email ];
        $args = [
            'post_type'      => 'hmp_reservation',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'posts_per_page' => 1,
            'meta_query'     => $meta,
        ];
    } else {
        return false;
    }

    $q = new WP_Query($args);
    return ! is_wp_error($q) && ! empty($q->posts);
}
