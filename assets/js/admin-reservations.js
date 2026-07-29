(function ($, config) {
	'use strict';

	var strings = config.strings || {};

	function format(template, values) {
		return Object.keys(values).reduce(function (message, key) {
			return message.replace('%' + key + '$s', values[key]);
		}, template);
	}

	function errorMessage(response) {
		var detail = response && response.data ? response.data : strings.requestFailed;
		return strings.errorPrefix + detail;
	}

	function successNotice(message) {
		if ($('.notice.notice-success').length) {
			return;
		}
		var $notice = $('<div>', { 'class': 'notice notice-success is-dismissible' });
		$notice.append($('<p>').text(message)).insertAfter('.wrap h1');
	}

	function actionButton(cssClass, label, reservationId, customer, product) {
		return $('<button>', {
			type: 'button',
			'class': cssClass,
			text: label
		}).attr({
			'data-reservation-id': reservationId,
			'data-customer': customer,
			'data-product': product
		});
	}

	$(function () {
		$('#filter-reservations').on('click', function () {
			var url = new URL(window.location);
			url.searchParams.set('status', $('#status-filter').val());
			url.searchParams.set('search_type', $('#search-type').val());
			if ($('#reservation-search').val()) {
				url.searchParams.set('search', $('#reservation-search').val());
			} else {
				url.searchParams.delete('search');
			}
			window.location.href = url.toString();
		});

		$('#clear-filters').on('click', function () {
			var url = new URL(window.location);
			url.searchParams.delete('status');
			url.searchParams.delete('search');
			url.searchParams.delete('search_type');
			window.location.href = url.toString();
		});

		$('#reservation-search').on('keypress', function (event) {
			if (event.which === 13) {
				$('#filter-reservations').trigger('click');
			}
		});

		$(document).on('click', '.hold-this-product-delete-reservation', function () {
			var $button = $(this);
			var reservationId = $button.data('reservation-id');
			var customer = $button.data('customer');
			var product = $button.data('product') || strings.thisProduct;
			if (!window.confirm(format(strings.confirmDelete, { 1: customer, 2: product }))) {
				return;
			}
			$button.prop('disabled', true).text(strings.deleting);
			$.post(config.ajaxUrl, { action: 'hold_this_product_delete_admin_reservation', reservation_id: reservationId, nonce: config.nonces.delete })
				.done(function (response) {
					if (!response.success) {
						window.alert(errorMessage(response));
						$button.prop('disabled', false).text(strings.delete);
						return;
					}
					$button.closest('tr').fadeOut(function () {
						$(this).remove();
						var $count = $('.displaying-num');
						var match = $count.text().match(/\d+/);
						if (match && parseInt(match[0], 10) > 0) {
							$count.text(strings.reservationCount.replace('%d', parseInt(match[0], 10) - 1));
						}
					});
					successNotice(strings.deleted);
				})
				.fail(function () {
					window.alert(strings.requestFailed);
					$button.prop('disabled', false).text(strings.delete);
				});
		});

		$(document).on('click', '.hold-this-product-approve-reservation', function () {
			var $button = $(this);
			var reservationId = $button.data('reservation-id');
			var customer = $button.data('customer');
			var product = $button.data('product') || strings.thisProduct;
			if (!window.confirm(format(strings.confirmApprove, { 1: customer, 2: product }))) {
				return;
			}
			$button.prop('disabled', true).text(strings.approving);
			$.post(config.ajaxUrl, { action: 'hold_this_product_approve_reservation', reservation_id: reservationId, nonce: config.nonces.approve })
				.done(function (response) {
					if (!response.success) {
						window.alert(errorMessage(response));
						$button.prop('disabled', false).text(strings.approve);
						return;
					}
					var $row = $button.closest('tr');
					$row.find('td:last-child').empty().append(actionButton('button button-small hold-this-product-cancel-reservation', strings.cancel, reservationId, customer, product));
					$row.find('td:nth-child(3) span').removeClass('status-pending-approval').addClass('status-active').text(strings.active);
					successNotice(strings.approved);
				})
				.fail(function () {
					window.alert(strings.requestFailed);
					$button.prop('disabled', false).text(strings.approve);
				});
		});

		$(document).on('click', '.hold-this-product-deny-reservation', function () {
			var $button = $(this);
			var reservationId = $button.data('reservation-id');
			var customer = $button.data('customer');
			var product = $button.data('product') || strings.thisProduct;
			var reason = window.prompt(strings.denialPrompt);
			if (reason === null) {
				return;
			}
			$button.prop('disabled', true).text(strings.denying);
			$.post(config.ajaxUrl, { action: 'hold_this_product_deny_reservation', reservation_id: reservationId, reason: reason, nonce: config.nonces.deny })
				.done(function (response) {
					if (!response.success) {
						window.alert(errorMessage(response));
						$button.prop('disabled', false).text(strings.deny);
						return;
					}
					var $row = $button.closest('tr');
					$row.find('td:last-child').empty().append(actionButton('button button-small button-link-delete hold-this-product-delete-reservation', strings.delete, reservationId, customer, product));
					$row.find('td:nth-child(3) span').removeClass('status-pending-approval').addClass('status-denied').text(strings.deniedStatus);
					$row.find('td:nth-child(6)').text('—').removeClass('time-left-critical time-left-warning');
					successNotice(strings.denied);
				})
				.fail(function () {
					window.alert(strings.requestFailed);
					$button.prop('disabled', false).text(strings.deny);
				});
		});

		$(document).on('click', '.hold-this-product-cancel-reservation', function () {
			var $button = $(this);
			var reservationId = $button.data('reservation-id');
			var customer = $button.data('customer');
			var product = $button.data('product') || strings.thisProduct;
			if (!reservationId) {
				window.alert(strings.missingId);
				return;
			}
			if (!window.confirm(format(strings.confirmCancel, { 1: customer, 2: product }))) {
				return;
			}
			$button.prop('disabled', true).text(strings.cancelling);
			$.post(config.ajaxUrl, { action: 'hold_this_product_cancel_admin_reservation', reservation_id: reservationId, nonce: config.nonces.cancel })
				.done(function (response) {
					if (response.success) {
						window.location.reload();
						return;
					}
					window.alert(errorMessage(response));
					$button.prop('disabled', false).text(strings.cancel);
				})
				.fail(function () {
					window.alert(strings.requestFailed);
					$button.prop('disabled', false).text(strings.cancel);
				});
		});
	});
}(jQuery, window.holdThisProductReservations || {}));
