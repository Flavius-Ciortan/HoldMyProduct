<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email notifications for reservations
 */
class HMP_Email_Manager {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init() {
        add_action( 'hmp_reservation_created', array( $this, 'send_confirmation_email' ), 10, 2 );
        add_action( 'hmp_reservation_expired', array( $this, 'send_expiration_email' ), 10, 2 );
    }
    
    /**
     * Check if email notifications are enabled
     */
    private function are_email_notifications_enabled() {
        $options = get_option( 'holdmyproduct_options' );
        return ! empty( $options['enable_email_notifications'] );
    }
    
    /**
     * Send reservation confirmation email
     */
    public function send_confirmation_email( $reservation_id, $email ) {
        if ( ! $this->are_email_notifications_enabled() ) {
            return;
        }
        
        $product_id = get_post_meta( $reservation_id, '_hmp_product_id', true );
        $expires_at = get_post_meta( $reservation_id, '_hmp_expires_at', true );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) return;
        
        $subject = sprintf( 'Reservation Confirmed: %s', $product->get_name() );
        $expires_formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at );
        
        $message = sprintf(
            "Hello,\n\nYour reservation for %s has been confirmed.\n\nExpires: %s\n\nView Product: %s\n\nAdd to Cart: %s\n\nThank you!",
            $product->get_name(),
            $expires_formatted,
            get_permalink( $product_id ),
            wc_get_cart_url() . '?add-to-cart=' . $product_id
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject, nl2br( $message ), $headers );
    }
    
    /**
     * Send expiration notification email
     */
    public function send_expiration_email( $reservation_id, $email ) {
        if ( ! $this->are_email_notifications_enabled() ) {
            return;
        }
        
        $product_id = get_post_meta( $reservation_id, '_hmp_product_id', true );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) return;
        
        $subject = sprintf( 'Reservation Expired: %s', $product->get_name() );
        
        $message = sprintf(
            "Hello,\n\nYour reservation for %s has expired and the product is now available to other customers.\n\nYou can still purchase it if it's available: %s\n\nThank you!",
            $product->get_name(),
            get_permalink( $product_id )
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject, nl2br( $message ), $headers );
    }
}
