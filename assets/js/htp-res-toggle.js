jQuery(function ($) {
  $(document).on('click', '.htp-res-toggle', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $cell = $btn.closest('td');
    var id = $btn.data('product-id');
    var isOn = $btn.data('state') === 'on';
    var next = isOn ? 'no' : 'yes';
    var $spinner = $cell.find('.spinner');

    $spinner.addClass('is-active');
    $btn.prop('disabled', true);

    $.post(htpResToggle.ajax, {
      action: 'htp_toggle_res',
      nonce: htpResToggle.nonce,
      product_id: id,
      new: next
    }).done(function (resp) {
      if (resp && resp.success) {
        var newVal = resp.data.new; // 'yes' or 'no'
        var newState = newVal === 'yes' ? 'on' : 'off';

        //  Show the NEXT action instead of current state
        var newActionLabel = (newVal === 'yes')
        ? (htpResToggle.disable || 'Disable')   // product is now enabled → show "Disable"
        : (htpResToggle.enable || 'Enable');    // product is now disabled → show "Enable"

        $btn
        .removeClass('on off')
        .addClass(newState)
        .attr('aria-pressed', newState === 'on' ? 'true' : 'false')
        .data('state', newState)
        .find('.htp-res-toggle-label').text(newActionLabel);

      } else {
        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Error');
      }
    }).fail(function () {
      alert('Request failed.');
    }).always(function () {
      $spinner.removeClass('is-active');
      $btn.prop('disabled', false);
    });
  });
});
