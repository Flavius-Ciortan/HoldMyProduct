<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcodes for HoldMyProduct
 */
class HMP_Shortcodes {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize shortcodes
     */
    private function init() {
        add_shortcode( 'hmp_guest_lookup', array( $this, 'guest_lookup_shortcode' ) );
    }
    
    /**
     * Guest reservation lookup shortcode
     */
    public function guest_lookup_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'title' => 'Check Your Reservations'
        ), $atts );
        
        ob_start();
        ?>
        <div id="hmp-guest-lookup">
            <h3><?php echo esc_html( $atts['title'] ); ?></h3>
            <form id="hmp-guest-lookup-form">
                <p>
                    <label for="guest-email">Email Address:</label>
                    <input type="email" id="guest-email" name="email" required>
                    <button type="submit">Check Reservations</button>
                </p>
            </form>
            <div id="hmp-guest-results" style="display: none;">
                <h4>Your Active Reservations:</h4>
                <div id="hmp-guest-reservations"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#hmp-guest-lookup-form').on('submit', function(e) {
                e.preventDefault();
                
                var email = $('#guest-email').val();
                
                $.post(holdmyproduct_ajax.ajax_url, {
                    action: 'hmp_guest_lookup',
                    email: email,
                    security: holdmyproduct_ajax.nonce
                })
                .done(function(response) {
                    if (response.success) {
                        if (response.data.reservations.length > 0) {
                            var html = '<table class="shop_table"><thead><tr><th>Product</th><th>Expires</th><th>Actions</th></tr></thead><tbody>';
                            $.each(response.data.reservations, function(i, res) {
                                html += '<tr>';
                                html += '<td><a href="' + res.product_url + '">' + res.product_name + '</a></td>';
                                html += '<td>' + res.expires_at + '</td>';
                                html += '<td><a href="' + res.add_to_cart_url + '" class="button">Add to Cart</a> ';
                                html += '<button class="button cancel-guest-res" data-id="' + res.id + '" data-email="' + email + '">Cancel</button></td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';
                            $('#hmp-guest-reservations').html(html);
                        } else {
                            $('#hmp-guest-reservations').html('<p>No active reservations found.</p>');
                        }
                        $('#hmp-guest-results').show();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            $(document).on('click', '.cancel-guest-res', function() {
                var $btn = $(this);
                var id = $btn.data('id');
                var email = $btn.data('email');
                
                if (confirm('Are you sure you want to cancel this reservation?')) {
                    $.post(holdmyproduct_ajax.ajax_url, {
                        action: 'hmp_guest_cancel',
                        reservation_id: id,
                        email: email,
                        security: holdmyproduct_ajax.nonce
                    })
                    .done(function(response) {
                        if (response.success) {
                            $btn.closest('tr').remove();
                            alert('Reservation cancelled successfully.');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
