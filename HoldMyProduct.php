<?php

 /**
 * Plugin Name: Hold My Product
 * Plugin URI: http://www.holdmyproduct.com/
 * Description: A custom plugin for product holding functionality.
 * Version: 1.0.0
 * Author: Hold My Product
 * Author URI: http://www.holdmyproduct.com/
 * Text Domain: hold-my-product
 * Domain Path: /languages
 * License: GPL2
 *
 * == Copyright ==
 * Copyright 2015 Cozmoslabs (www.cozmoslabs.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HoldMyProduct {

    public function __construct() {

        define( 'HMP_VERSION', '2.15.7' );
        define( 'HMP_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
        define( 'HMP_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
        define( 'HMP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
        define( 'HOLD_MY_PRODUCT', 'Hold My Product' );


        // Include dependencies
        $this->include_dependencies();

        // Initialize the components
        $this->init();

        
    }

    public function include_dependencies() {}

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'init', array( $this, 'load_textdomain' ), 1 );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'hold-my-product', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function add_settings_page() {
        add_menu_page(
            'Hold My Product',
            'Hold My Product',
            'manage_options',
            'hold-my-product',
            array( $this, 'render_settings_page' ),
            'dashicons-admin-generic'
        );
    }

    public function register_settings() {
        register_setting( 'hmp_settings_group', 'hmp_enabled' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Hold My Product Settings', 'hold-my-product' ) ?></h1>

            <form method="post" action="options.php">

                <?php settings_fields( 'hmp_settings_group' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"> <?php esc_html_e( 'Reserve Product', 'hold-my-product' ) ?></th>
                        <td>
                            <label for="hmp-enabled">
                                <input type="checkbox" id="hmp-enabled" name="hmp_enabled" value="yes" <?php checked( get_option( 'hmp_enabled', 'no' ), 'yes' ); ?> />
                                <?php esc_html_e( 'By enabling this option a Reserve button will be displayed on the Product page, next to Add To Cart button.', 'hold-my-product' ) ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>

            </form>
        </div>
        <?php
    }
}

new HoldMyProduct();
