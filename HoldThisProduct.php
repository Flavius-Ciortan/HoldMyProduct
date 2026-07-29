<?php

/**
 * Plugin Name:       Hold This Product
 * Plugin URI:        https://github.com/Flavius-Ciortan/HoldThisProduct
 * Description:       Allows WooCommerce customers to reserve products for a limited time before purchase.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.3
 * WC tested up to:   10.9.4
 * Author:            Flavius Ciortan, Anghel Emanuel.
 * Author URI:        https://github.com/Flavius-Ciortan
 * Text Domain:       hold-this-product
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if PRO version is active
if ( defined( 'HOLD_THIS_PRODUCT_PRO_VERSION' ) || defined( 'HTP_PRO_VERSION' ) ) {
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
define( 'HOLD_THIS_PRODUCT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HOLD_THIS_PRODUCT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HOLD_THIS_PRODUCT_VERSION', '1.0.0' );

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
        add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
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
		require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/class-htp-reservations.php';
        require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/class-htp-email-manager.php';
        
        // Admin classes
        if ( is_admin() ) {
            require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/admin/class-htp-admin.php';
            require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/admin/class-htp-admin-reservations.php';
            require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/admin/class-htp-admin-analytics.php';
        }
        
        // Frontend classes
        if ( ! is_admin() ) {
            require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/frontend/class-htp-frontend.php';
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
        $this->reservations = new Hold_This_Product_Reservations();
        new Hold_This_Product_Email_Manager();
        
        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new Hold_This_Product_Admin( $this->reservations );
            new Hold_This_Product_Analytics( $this->reservations );
        }
        
        // Initialize frontend
        if ( ! is_admin() ) {
            $this->frontend = new Hold_This_Product_Frontend( $this->reservations );
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
		require_once HOLD_THIS_PRODUCT_PLUGIN_PATH . 'includes/class-htp-reservations.php';
        $reservations = new Hold_This_Product_Reservations();
        
        // Flush rewrite rules to register the new endpoint
        $reservations->flush_rewrite_rules();
		$reservations->schedule_expiration();
		update_option( 'hold_this_product_version', HOLD_THIS_PRODUCT_VERSION, false );
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
		wp_clear_scheduled_hook( 'hold_this_product_expire_reservations' );
        // Flush rewrite rules on deactivation to clean up
        flush_rewrite_rules();
    }

	/**
	 * Declare tested WooCommerce feature compatibility.
	 */
	public function declare_woocommerce_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}

	/** Normalize legacy local-offset timestamps in bounded upgrade batches. */
	public function maybe_upgrade() {
		$schema_version = (string) get_option( 'hold_this_product_schema_version', '0' );
		if ( version_compare( $schema_version, '1.0.0', '>=' ) || ! class_exists( 'Hold_This_Product_Reservations' ) ) {
			return;
		}
		$this->migrate_legacy_identifiers();
		$ids = get_posts( array(
			'post_type' => 'holdthisproduct_res', 'post_status' => 'publish', 'fields' => 'ids',
			'posts_per_page' => 500, 'no_found_rows' => true,
			'meta_query' => array(
				array( 'key' => '_hold_this_product_expires_at', 'compare' => 'EXISTS' ),
				array( 'key' => '_hold_this_product_timestamp_model', 'compare' => 'NOT EXISTS' ),
			),
		) );
		$offset = current_time( 'timestamp' ) - time();
		foreach ( $ids as $reservation_id ) {
			$expires = (int) get_post_meta( $reservation_id, '_hold_this_product_expires_at', true );
			update_post_meta( $reservation_id, '_hold_this_product_expires_at', max( 0, $expires - $offset ) );
			update_post_meta( $reservation_id, '_hold_this_product_timestamp_model', 'utc' );
		}
		$this->reservations->schedule_expiration();
		if ( count( $ids ) < 500 ) {
			update_option( 'hold_this_product_version', HOLD_THIS_PRODUCT_VERSION, false );
			update_option( 'hold_this_product_schema_version', '1.0.0', false );
			delete_option( 'htp_version' );
		}
	}

	/** Migrate identifiers from pre-directory development builds without changing reservation data. */
	private function migrate_legacy_identifiers() {
		global $wpdb;
		$legacy_post_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'htp_reservation'"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed-value one-time migration.
		$wpdb->query(
			"UPDATE {$wpdb->posts} SET post_type = 'holdthisproduct_res' WHERE post_type = 'htp_reservation'"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed-value one-time migration.
		$wpdb->query(
			"UPDATE {$wpdb->postmeta} SET meta_key = CONCAT('_hold_this_product_', SUBSTRING(meta_key, 6)) WHERE meta_key LIKE '\\_htp\\_%'"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed-value one-time migration.
		if ( isset( $wpdb->woocommerce_order_itemmeta ) ) {
			$table = $wpdb->woocommerce_order_itemmeta;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET meta_key = CONCAT('_hold_this_product_', SUBSTRING(meta_key, 6)) WHERE meta_key LIKE %s",
					$table,
					$wpdb->esc_like( '_htp_' ) . '%'
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed-value one-time migration.
		}
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'htp_lock_%'"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Removes obsolete short-lived locks.
		wp_clear_scheduled_hook( 'htp_expire_reservations' );
		foreach ( $legacy_post_ids as $legacy_post_id ) {
			clean_post_cache( (int) $legacy_post_id );
		}
		flush_rewrite_rules( false );
	}

	public function add_privacy_policy_content() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Hold This Product', 'hold-this-product' ),
				wp_kses_post( '<p>' . __( 'This plugin stores the customer user ID, email address, product ID, reservation status, expiry time, and related order ID to manage product reservations. Reservation data can be exported and erased with the WordPress privacy tools. Open reservations are retained until their inventory obligation ends.', 'hold-this-product' ) . '</p>' )
			);
		}
	}
}

// Initialize the plugin
HoldThisProduct::get_instance();
