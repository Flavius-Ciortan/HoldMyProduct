jQuery(document).ready(function($) {

    $('#hmp_reserve_product').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('productid');

        // Check if user can make reservations
        if (holdmyproduct_ajax.is_logged_in == 0 && holdmyproduct_ajax.guest_reservations_enabled == 0) {
            alert('Please log in to reserve products.');
            return;
        }

        $('#reservation-modal').show();
        $('#reservation-form').find('input[name="product_id"]').val(productId);

        $('#reservation-modal').dialog({
            resizable: false,
            height: 'auto',
            width: 500,
            modal: true,
            closeOnEscape: true,
            open: function () {
                $('.ui-widget-overlay').bind('click', function () {
                    $('#reservation-modal').dialog('close');
                });
            }
        });
    });

    $('#reservation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var formData = new FormData(this);
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'holdmyproduct_reserve',
            product_id: formData.get('product_id'),
            security: holdmyproduct_ajax.nonce
        };

        // Add guest data if not logged in
        if (holdmyproduct_ajax.is_logged_in == 0) {
            ajaxData.email = formData.get('email');
            ajaxData.name = formData.get('name');
            ajaxData.surname = formData.get('surname');
        }

        // Disable submit button during request
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Processing...');

        $.post(holdmyproduct_ajax.ajax_url, ajaxData)
        .done(function(response) {
            if (response.success) {
                alert('Reservation successful! You will receive a confirmation email shortly.');
                $('#reservation-modal').dialog('close');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        })
        .fail(function() {
            alert('Request failed. Please try again.');
        })
        .always(function() {
            $submitBtn.prop('disabled', false).text(originalText);
        });
    });
});




