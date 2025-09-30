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
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants
define( 'HMP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HMP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HMP_VERSION', '1.0.0' );

/**
 * Main plugin class
 */
class HoldMyProduct {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    public $admin;
    public $frontend;
    public $reservations;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     */
    private function init() {
        // Check if WooCommerce is active
        add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
        
        // Load classes
        add_action( 'init', array( $this, 'load_classes' ) );
        
        // Initialize plugin
        add_action( 'init', array( $this, 'init_plugin' ) );
    }
    
    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return false;
        }
        return true;
    }
    
    /**
     * Show notice if WooCommerce is missing
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Hold My Product requires WooCommerce to be installed and active.', 'hold-my-product' ); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load required classes
     */
    public function load_classes() {
        if ( ! $this->check_dependencies() ) {
            return;
        }
        
        // Core classes
        require_once HMP_PLUGIN_PATH . 'includes/class-hmp-reservations.php';
        
        // Admin classes
        if ( is_admin() ) {
            require_once HMP_PLUGIN_PATH . 'includes/admin/class-hmp-admin.php';
        }
        
        // Frontend classes
        if ( ! is_admin() ) {
            require_once HMP_PLUGIN_PATH . 'includes/frontend/class-hmp-frontend.php';
        }
    }
    
    /**
     * Initialize plugin components
     */
    public function init_plugin() {
        if ( ! $this->check_dependencies() ) {
            return;
        }
        
        // Initialize core
        $this->reservations = new HMP_Reservations();
        
        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new HMP_Admin();
        }
        
        // Initialize frontend
        if ( ! is_admin() ) {
            $this->frontend = new HMP_Frontend();
        }
    }
}

// Initialize the plugin
HoldMyProduct::get_instance();
