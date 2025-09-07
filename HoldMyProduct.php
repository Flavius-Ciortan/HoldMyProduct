<?php

/**
 * Plugin Name:       Hold My Product
 * Plugin URI:        https://github.com/Flavius-Ciortan/HoldMyProduct
 * Description:       Allows WooCommerce customers to reserve products for a limited time before purchase.
 * Version:           1.0.0
 * Author:            Flavius Ciortan
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

    if ( !is_product() )
        return;

    include plugin_dir_path(__FILE__) . 'form_template.php';
}

// Îl adăugăm sub butonul „Adaugă în coș”
add_action('woocommerce_after_add_to_cart_form', 'holdmyproduct_display_form');

// // Hook the function into the WooCommerce product page
// add_action('woocommerce_after_product_summary' , 'insert_custom_html_into_product', 10);

function holdmyproduct_enqueue_scripts() {
    if (!is_admin()) {
        wp_enqueue_script(
            'holdmyproduct-js',
            plugin_dir_url(__FILE__) . 'holdmyproduct.js',
            array( 'jquery', 'jquery-ui-dialog' ),
            '1.0',
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


function holdmyproduct_handle_reservation() {
    check_ajax_referer('holdmyproduct_nonce', 'security');

    $product_id = intval($_POST['product_id']);

    if (!$product_id) {
        wp_send_json_error('Invalid product ID.');
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error('Product not found.');
    }

    // Verifică dacă produsul e gestionat stoc
    if (!$product->managing_stock()) {
        wp_send_json_error('Product stock is not managed.');
    }

    // Verifică stocul actual
    $stock_quantity = $product->get_stock_quantity();

    if ($stock_quantity <= 0) {
        wp_send_json_error('No stock available.');
    }

    // Scade 1 din stoc
    $new_stock = $stock_quantity - 1;
    $product->set_stock_quantity($new_stock);

    // Actualizează stocul în bază
    $product->save();

    wp_send_json_success('Stock updated.');

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
        'dashicons-products',        // Icon
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
}

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