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

        $.post(holdmyproduct_ajax.ajax_url, {
            action: 'holdmyproduct_reserve',
            product_id: productId,
            security: holdmyproduct_ajax.nonce
        }, function(response) {
            if (response.success) {
                alert('Reservation successful! The stock was updated.');
                $('#reservation-modal').hide();
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

});

jQuery(function ($) {
  $(document).on('submit', '#reservation-form', function (e) {
    e.preventDefault();

    // Debug: see exactly what will be sent
    console.log('SERIALIZED:', $(this).serialize());

    $.post((window.hmpReserve && hmpReserve.ajax) ? hmpReserve.ajax : window.ajaxurl, $(this).serialize())
      .done(function (resp) {
        if (resp && resp.success) {
          $('#reservation-modal').dialog('close');
          alert('Reserved!');
        } else {
          alert((resp && resp.data) ? resp.data : 'Reservation failed.');
        }
      })
      .fail(function () {
        alert('Request failed.');
      });
  });
});



