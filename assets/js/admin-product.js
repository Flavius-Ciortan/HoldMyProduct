(function ($, config) {
	'use strict';
	$(function () {
		$('.hold-this-product-cancel-reservation').on('click', function () {
			var $button = $(this);
			var customer = $button.data('customer');
			if (!window.confirm(config.strings.confirmCancel.replace('%s', customer))) {
				return;
			}
			$button.prop('disabled', true).text(config.strings.cancelling);
			$.post(config.ajaxUrl, {
				action: 'hold_this_product_cancel_admin_reservation',
				reservation_id: $button.data('reservation-id'),
				nonce: config.nonce
			}).done(function (response) {
				if (response.success) {
					$button.closest('tr').fadeOut(function () { $(this).remove(); });
					window.alert(config.strings.cancelled);
					return;
				}
				window.alert(config.strings.errorPrefix + response.data);
				$button.prop('disabled', false).text(config.strings.cancel);
			}).fail(function () {
				window.alert(config.strings.requestFailed);
				$button.prop('disabled', false).text(config.strings.cancel);
			});
		});
	});
}(jQuery, window.holdThisProductAdminProduct || {}));
