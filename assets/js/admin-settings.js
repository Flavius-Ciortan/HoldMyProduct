(function ($) {
	'use strict';

	$(function () {
		var allowedTabs = ['general', 'logged-in'];
		var requestedTab = new URLSearchParams(window.location.search).get('active_tab') || window.localStorage.getItem('hold_this_product_active_tab') || 'general';
		var activeTab = allowedTabs.indexOf(requestedTab) === -1 ? 'general' : requestedTab;

		function showTab(target) {
			if (allowedTabs.indexOf(target) === -1) {
				target = 'general';
			}
			$('.hold-this-product-nav-tab').removeClass('hold-this-product-nav-tab-active');
			$('.hold-this-product-tab-content').removeClass('hold-this-product-tab-active').hide();
			$('.hold-this-product-nav-tab[data-target="' + target + '"]').addClass('hold-this-product-nav-tab-active');
			$('#hold-this-product-' + target).addClass('hold-this-product-tab-active').show();
			$('#hold-this-product-active-tab-field').val(target);
			window.localStorage.setItem('hold_this_product_active_tab', target);
		}

		$('<input>', { type: 'hidden', name: 'active_tab', id: 'hold-this-product-active-tab-field', value: activeTab }).appendTo('.hold-this-product-settings-form');
		showTab(activeTab);
		$('.hold-this-product-form-actions').addClass('hold-this-product-ready');

		$('.hold-this-product-nav-tab').on('click', function () {
			showTab($(this).data('target'));
		});

		$('.hold-this-product-popup-tab').on('click', function () {
			var tab = $(this).data('popup-tab');
			$('.hold-this-product-popup-tab').removeClass('hold-this-product-popup-tab-active');
			$(this).addClass('hold-this-product-popup-tab-active');
			$('.hold-this-product-popup-tab-content').hide();
			$('.hold-this-product-popup-tab-content-' + tab).show();
		});

		var $customizationToggle = $('input[name="holdthisproduct_options[enable_popup_customization_logged_in]"]');
		var $customizationFields = $('.hold-this-product-popup-customization-fields-logged-in');
		$customizationToggle.on('change', function () {
			$customizationFields[$(this).is(':checked') ? 'slideDown' : 'slideUp']();
		});

		function toggleMaxReservations() {
			$('#holdthisproduct-max-reservations-wrapper').toggle($('input[name="holdthisproduct_options[enable_reservation]"]').is(':checked'));
		}
		toggleMaxReservations();
		$('input[name="holdthisproduct_options[enable_reservation]"]').on('change', toggleMaxReservations);
	});
}(jQuery));
