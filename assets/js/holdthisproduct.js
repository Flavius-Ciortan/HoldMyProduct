jQuery(document).ready(function($) {

    // Handle reserve button click
    $('#htp_reserve_product').on('click', function(e) {
        e.preventDefault();
        var productId = $(this).data('productid');

        // Check if user can make reservations
        if (holdthisproduct_ajax.is_logged_in == 0 && holdthisproduct_ajax.guest_reservations_enabled == 0) {
            alert('Please log in to reserve products.');
            return;
        }

        // Set product ID in form
        $('#reservation-form').find('input[name="product_id"]').val(productId);
        
        // Show modal
        $('#reservation-modal').show();
    });

    // Close modal when clicking outside
    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
            $('#reservation-modal').hide();
        }
    });

    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#reservation-modal').hide();
        }
    });

    // Handle form submission
    $('#reservation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var formData = new FormData(this);
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'holdthisproduct_reserve',
            product_id: formData.get('product_id'),
            security: holdthisproduct_ajax.nonce
        };

        // Add guest data if not logged in
        if (holdthisproduct_ajax.is_logged_in == 0) {
            ajaxData.email = formData.get('email');
            ajaxData.name = formData.get('name');
            ajaxData.surname = formData.get('surname');
        }

        // Disable submit button during request
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Processing...');

        // Send AJAX request
        $.post(holdthisproduct_ajax.ajax_url, ajaxData)
        .done(function(response) {
            if (response.success) {
                alert('Reservation successful!');
                $('#reservation-modal').hide();
                // Optionally reload the page to show updated stock
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
