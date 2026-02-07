<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email notifications for reservations
 */
class HTP_Email_Manager {
    
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
        add_action( 'htp_reservation_created', array( $this, 'send_confirmation_email' ), 10, 2 );
        add_action( 'htp_reservation_expired', array( $this, 'send_expiration_email' ), 10, 2 );
        add_action( 'htp_reservation_pending_approval', array( $this, 'send_pending_approval_email' ), 10, 2 );
        add_action( 'htp_reservation_approved', array( $this, 'send_approval_confirmation_email' ), 10, 2 );
        add_action( 'htp_reservation_denied', array( $this, 'send_denial_email' ), 10, 3 );
    }
    
    /**
     * Check if email notifications are enabled
     */
    private function are_email_notifications_enabled() {
        $options = get_option( 'holdthisproduct_options' );
        return ! empty( $options['enable_email_notifications'] );
    }
    
    /**
     * Send reservation confirmation email
     */
    public function send_confirmation_email( $reservation_id, $email ) {
        if ( ! $this->are_email_notifications_enabled() ) {
            return;
        }
        
        $product_id = get_post_meta( $reservation_id, '_htp_product_id', true );
        $expires_at = get_post_meta( $reservation_id, '_htp_expires_at', true );
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
        
        $product_id = get_post_meta( $reservation_id, '_htp_product_id', true );
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
    
    /**
     * Send pending approval email to customer
     */
    public function send_pending_approval_email( $reservation_id, $email ) {
        if ( ! $this->are_email_notifications_enabled() ) {
            return;
        }
        
        $product_id = get_post_meta( $reservation_id, '_htp_product_id', true );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) return;
        
        $subject = sprintf( 'Reservation Pending Approval: %s', $product->get_name() );
        
        $message = sprintf(
            "Hello,\n\nThank you for your reservation request for %s.\n\nYour reservation is currently pending admin approval. You will receive another email once your reservation has been reviewed.\n\nView Product: %s\n\nThank you for your patience!",
            $product->get_name(),
            get_permalink( $product_id )
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject, nl2br( $message ), $headers );
    }
    
    /**
     * Send approval confirmation email to customer
     */
    public function send_approval_confirmation_email( $reservation_id, $email ) {
        if ( ! $this->are_email_notifications_enabled() ) {
            return;
        }
        
        $product_id = get_post_meta( $reservation_id, '_htp_product_id', true );
        $expires_at = get_post_meta( $reservation_id, '_htp_expires_at', true );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) return;
        
        $subject = sprintf( 'Reservation Approved: %s', $product->get_name() );
        $expires_formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at );
        
        $message = sprintf(
            "Hello,\n\nGreat news! Your reservation for %s has been approved and is now active.\n\nExpires: %s\n\nView Product: %s\n\nAdd to Cart: %s\n\nThank you!",
            $product->get_name(),
            $expires_formatted,
            get_permalink( $product_id ),
            wc_get_cart_url() . '?add-to-cart=' . $product_id
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject, nl2br( $message ), $headers );
    }
    
    /**
     * Send denial email to customer
     */
    public function send_denial_email( $reservation_id, $email, $reason = '' ) {
        if ( ! $this->are_email_notifications_enabled() ) {
            return;
        }
        
        $product_id = get_post_meta( $reservation_id, '_htp_product_id', true );
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) return;
        
        $subject = sprintf( 'Reservation Not Approved: %s', $product->get_name() );
        
        $message = sprintf(
            "Hello,\n\nWe're sorry to inform you that your reservation request for %s could not be approved at this time.\n\n%s\n\nYou can still view the product and make a purchase if it becomes available: %s\n\nThank you for your understanding!",
            $product->get_name(),
            $reason ? "Reason: " . $reason : '',
            get_permalink( $product_id )
        );
        
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        wp_mail( $email, $subject, nl2br( $message ), $headers );
    }
}
