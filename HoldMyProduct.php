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

// Check if PRO version is active
if ( defined( 'HMP_PRO_VERSION' ) ) {
    add_action( 'admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Hold My Product PRO is active. Please deactivate the PRO version before activating the free version, or deactivate the free version to use PRO.', 'hold-my-product' ); ?></p>
        </div>
        <?php
    } );
    return;
}

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
        require_once HMP_PLUGIN_PATH . 'includes/class-hmp-email-manager.php';
        require_once HMP_PLUGIN_PATH . 'includes/class-hmp-shortcodes.php';
        
        // Admin classes
        if ( is_admin() ) {
            require_once HMP_PLUGIN_PATH . 'includes/admin/class-hmp-admin.php';
            require_once HMP_PLUGIN_PATH . 'includes/admin/class-hmp-analytics.php';
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
        new HMP_Email_Manager();
        new HMP_Shortcodes();
        
        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new HMP_Admin();
            new HMP_Analytics();
        }
        
        // Initialize frontend
        if ( ! is_admin() ) {
            $this->frontend = new HMP_Frontend();
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
        require_once HMP_PLUGIN_PATH . 'includes/class-hmp-reservations.php';
        $reservations = new HMP_Reservations();
        
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
HoldMyProduct::get_instance();
