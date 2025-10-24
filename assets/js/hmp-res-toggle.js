jQuery(function ($) {
  $(document).on('click', '.hmp-res-toggle', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $cell = $btn.closest('td');
    var id = $btn.data('product-id');
    var isOn = $btn.data('state') === 'on';
    var next = isOn ? 'no' : 'yes';
    var $spinner = $cell.find('.spinner');

    $spinner.addClass('is-active');
    $btn.prop('disabled', true);

    $.post(hmpResToggle.ajax, {
      action: 'hmp_toggle_res',
      nonce: hmpResToggle.nonce,
      product_id: id,
      new: next
    }).done(function (resp) {
      if (resp && resp.success) {
        var newVal = resp.data.new; // 'yes' or 'no'
        var newState = newVal === 'yes' ? 'on' : 'off';

        //  Show the NEXT action instead of current state
        var newActionLabel = (newVal === 'yes')
        ? (hmpResToggle.disable || 'Disable')   // product is now enabled → show "Disable"
        : (hmpResToggle.enable || 'Enable');    // product is now disabled → show "Enable"

        $btn
        .removeClass('on off')
        .addClass(newState)
        .attr('aria-pressed', newState === 'on' ? 'true' : 'false')
        .data('state', newState)
        .find('.hmp-res-toggle-label').text(newActionLabel);

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
