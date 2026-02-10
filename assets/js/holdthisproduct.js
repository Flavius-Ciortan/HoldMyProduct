jQuery(document).ready(function($) {

    $('#htp_reserve_product').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('productid');

        if (holdthisproduct_ajax.is_logged_in == 0) {
            alert('Please log in to reserve products.');
            return;
        }

        $('#reservation-form').find('input[name="product_id"]').val(productId);
        
        $('#reservation-modal').show();
    });

    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
            $('#reservation-modal').hide();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#reservation-modal').hide();
        }
    });

    $('#reservation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var formData = new FormData(this);
        
        var ajaxData = {
            action: 'holdthisproduct_reserve',
            product_id: formData.get('product_id'),
            security: holdthisproduct_ajax.nonce
        };

        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Processing...');

        $.post(holdthisproduct_ajax.ajax_url, ajaxData)
        .done(function(response) {
            if (response.success) {
                alert('Reservation successful!');
                $('#reservation-modal').hide();
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
