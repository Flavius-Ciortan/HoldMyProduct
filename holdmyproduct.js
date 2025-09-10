jQuery(document).ready(function($) {

    $('#hmp_reserve_product').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('productid');

        $('#reservation-modal').show(); // Show modal

        $('#reservation-form').find('input[name="product_id"]').val(productId); // Pass ID to form

        jQuery('#reservation-modal').dialog({
            resizable: false,
            height: 'auto',
            width: 800,
            modal: true,
            closeOnEscape: true,
    
            open: function () {
                jQuery('.ui-widget-overlay').bind('click',function () {
                    jQuery('#reservation-modal').dialog('close');
                })
            },
    
            close: function () {
            }
        });
        
    });

    // $('.modal-close').on('click', function() {
    //     $('#reservation-modal').hide();
    // });


    $('#reservation-form').on('submit', function(e) {
        e.preventDefault();

        var productId = $(this).find('input[name="product_id"]').val();
        let formData = new FormData(this);
        let userEmail = formData.get('email');

        $.post(holdmyproduct_ajax.ajax_url, {
            action: 'holdmyproduct_reserve',
            product_id: productId,
            email: userEmail,
            security: holdmyproduct_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Reservation successful! The stock was updated.');
                // $('#reservation-modal').hide();
                jQuery('#reservation-modal').dialog('close');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

});




