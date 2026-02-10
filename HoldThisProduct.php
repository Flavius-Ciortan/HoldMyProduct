<?php

/**
 * Plugin Name:       Hold This Product
 * Plugin URI:        https://github.com/Flavius-Ciortan/HoldThisProduct
 * Description:       Allows WooCommerce customers to reserve products for a limited time before purchase.
 * Version:           1.0.0
 * Author:            Flavius Ciortan, Anghel Emanuel.
 * Author URI:        https://github.com/Flavius-Ciortan
 * Text Domain:       hold-this-product
 * Domain Path:       /languages
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if PRO version is active
if ( defined( 'HTP_PRO_VERSION' ) ) {
    add_action( 'admin_init', function() {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    } );
    add_action( 'admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Hold This Product (Free) cannot be activated because Hold This Product PRO is already active. Please deactivate the PRO version first if you want to use the free version.', 'hold-this-product' ); ?></p>
        </div>
        <?php
    } );
    return;
}

// Define plugin constants
define( 'HTP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HTP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HTP_VERSION', '1.0.0' );

/**
 * Main plugin class
 */
class HoldThisProduct {
    
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
        
        // Activation and deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );
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
            <p><?php esc_html_e( 'Hold This Product requires WooCommerce to be installed and active.', 'hold-this-product' ); ?></p>
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
        require_once HTP_PLUGIN_PATH . 'includes/class-htp-reservations.php';
        require_once HTP_PLUGIN_PATH . 'includes/class-htp-email-manager.php';
        
        // Admin classes
        if ( is_admin() ) {
            require_once HTP_PLUGIN_PATH . 'includes/admin/class-htp-admin.php';
            require_once HTP_PLUGIN_PATH . 'includes/admin/class-htp-analytics.php';
        }
        
        // Frontend classes
        if ( ! is_admin() ) {
            require_once HTP_PLUGIN_PATH . 'includes/frontend/class-htp-frontend.php';
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
        $this->reservations = new HTP_Reservations();
        new HTP_Email_Manager();
        
        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new HTP_Admin();
            new HTP_Analytics();
        }
        
        // Initialize frontend
        if ( ! is_admin() ) {
            $this->frontend = new HTP_Frontend();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate_plugin() {
        if ( ! $this->check_dependencies() ) {
            return;
        }
        
        // Load reservations class to register endpoints
        require_once HTP_PLUGIN_PATH . 'includes/class-htp-reservations.php';
        $reservations = new HTP_Reservations();
        
        // Flush rewrite rules to register the new endpoint
        $reservations->flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        // Flush rewrite rules on deactivation to clean up
        flush_rewrite_rules();
    }
}

// Initialize the plugin
HoldThisProduct::get_instance();
