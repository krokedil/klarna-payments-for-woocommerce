jQuery( function( $ ) {
	'use strict';

	var klarna_payments = {
		authorization_response: {},
		iframe_loaded: false,
		show_form: false,
		klarna_container_selector: '#klarna_container',

		start: function() {
			// Add page visibility listener to handle tab changes.
			klarna_payments.page_visibility_listener();

			$('body').on('updated_checkout', function() {
				// Unblock the payments element if blocked
				var blocked_el = $('.woocommerce-checkout-payment');
				var blocked_el_data = blocked_el.data();
				if ( blocked_el.length && 1 === blocked_el_data['blockUI.isBlocked'] ) {
					blocked_el.unblock();
				}

				// If Klarna Payments is selected and iframe is not loaded yet, disable the form.
				if ( 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val() ) {
					$('#place_order').attr('disabled', true);

					if ( klarna_payments.check_required_fields() ) {
						klarna_payments.load().then(klarna_payments.loadHandler);
					}
				}
			});

			$('form.checkout').on('change', 'input, select', function() {
				if ( 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val() ) {
					if ( klarna_payments.check_required_fields() ) {
						$('#place_order').attr('disabled', true);

						klarna_payments.load().then(klarna_payments.loadHandler);
					}
				}

				// When changing payment method.
				if ( 'payment_method' === $(this).attr('name') ) {
					// If Klarna Payments is selected and iframe is not loaded yet, disable the form.
					if (!klarna_payments.show_form && 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val()) {
						$('#place_order').attr('disabled', true);
					}

					// Enable the form if any other payment method is selected.
					if ('klarna_payments' !== jQuery('input[name="payment_method"]:checked').val()) {
						$('#place_order').attr('disabled', false);
					}
				}
			});

			// Need to do this for form auto-fill (no change event).
			var checkFormInterval = setInterval(function () {
				if (klarna_payments.show_form) {
					clearInterval(checkFormInterval);
				}

				if ( 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val() ) {
					if ( klarna_payments.check_required_fields() ) {
						$('#place_order').attr('disabled', true);

						clearInterval(checkFormInterval);

						klarna_payments.load().then(klarna_payments.loadHandler);
					}
				}
			}, 100);

			/**
			 * Hooking into WooCommerce.
			 *
			 * Firing Klarna.Credit.authorize(), then once it resolves, adding the hidden form field and re-submitting the form.
 			 */
			$( 'form.checkout' ).on( 'checkout_place_order_klarna_payments', function() {
				if ($('input[name="klarna_payments_authorization_token"]').length) {
					return true;
				}

				klarna_payments.authorize().done( function(response) {
					if ('authorization_token' in response) {
						$('input[name="klarna_payments_authorization_token"]').remove();
						$('form.checkout').append('<input type="hidden" name="klarna_payments_authorization_token" value="' + klarna_payments.authorization_response.authorization_token + '" />').submit();
					}

					if (false === response.show_form) {
						// Hide Klarna Payments (for now, do not do this).
						/*
						$('li.payment_method_klarna_payments input[type="radio"]').attr('disabled', true)
						$('li.payment_method_klarna_payments').hide()
						*/
					}
				});

				return false;
			});
		},

		load: function() {
			if ($(klarna_payments.klarna_container_selector).length) {
				var $defer = $.Deferred();

				var klarnaLoadedInterval = setInterval(function () {
					var Klarna = false;

					try {
						Klarna = window.Klarna;
					} catch (e) {
						console.debug(e);
					}

					if (Klarna && Klarna.Credit && Klarna.Credit.initialized) {
						clearInterval(klarnaLoadedInterval);
						clearTimeout(klarnaLoadedTimeout);

						var options = {
							container: klarna_payments.klarna_container_selector
						};

						if ( 'US' === $('#billing_country').val() ) {
							var address = klarna_payments.getAddress();

							Klarna.Credit.load(
								options,
								address,
								function (response) {
									$defer.resolve(response);
								}
							);
						} else {
							Klarna.Credit.load(
								options,
								function (response) {
									$defer.resolve(response);
								}
							);
						}
					}
				}, 100);

				var klarnaLoadedTimeout = setTimeout(function () {
					clearInterval(klarnaLoadedInterval);
					$defer.reject();
				}, 3000);

				return $defer.promise();
			}
		},

		loadHandler: function(response) {
			klarna_payments.iframe_loaded = true;

			if (response.show_form) {
				klarna_payments.show_form = true;
				$('#place_order').attr('disabled', false);
			}
		},

		authorize: function() {
			var $defer = $.Deferred();
			var address = klarna_payments.getAddress();

			klarna_payments.authorization_response = {};

			Klarna.Credit.authorize( address, function(response) {
				klarna_payments.authorization_response = response;
				$defer.resolve(response);
			});

			return $defer.promise();
		},

		check_required_fields: function() {
			var input_value;
			var input_flag = false;

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				$('.woocommerce-billing-fields .validate-required[id^="billing"], .woocommerce-shipping-fields .validate-required[id^="shipping"]').each(function() {
					if ( $(this).find('select').length ) {
						input_value = $(this).find('select').val();
					} else {
						input_value = $(this).find('input').val();
					}

					if ('' === input_value || undefined === input_value) {
						input_flag = true;
					}
				});
			} else {
				$('.woocommerce-billing-fields .validate-required[id^="billing"]').each(function() {
					if ( $(this).find('select').length ) {
						input_value = $(this).find('select').val();
					} else {
						input_value = $(this).find('input').val();
					}

					if ('' === input_value || undefined === input_value) {
						input_flag = true;
					}
				});
			}

			if ( input_flag ) {
				klarna_payments.show_form = false;
				return false;
			} else {
				return true;
			}
		},

		page_visibility_listener: function() {
			var hidden, visibilityChange;

			if (typeof document.hidden !== "undefined") { // Opera 12.10 and Firefox 18 and later support
				hidden = "hidden";
				visibilityChange = "visibilitychange";
			} else if (typeof document.msHidden !== "undefined") {
				hidden = "msHidden";
				visibilityChange = "msvisibilitychange";
			} else if (typeof document.webkitHidden !== "undefined") {
				hidden = "webkitHidden";
				visibilityChange = "webkitvisibilitychange";
			}

			// Warn if the browser doesn't support addEventListener or the Page Visibility API
			if (typeof document.addEventListener === "undefined" || typeof document[hidden] === "undefined") {
			} else {
				// Handle page visibility change
				document.addEventListener(visibilityChange, handleVisibilityChange, false);
			}

			function handleVisibilityChange() {
				if (! document[hidden]) {
					if ( 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val() ) {
						$('body').trigger('update_checkout');
					}
				}
			}
		},

		getAddress: function() {
			var address = {
				billing_address: {
					given_name : $('#billing_first_name').val(),
					family_name : $('#billing_last_name').val(),
					email : $('#billing_email').val(),
					phone : $('#billing_phone').val(),
					country : $('#billing_country').val(),
					region : $('#billing_state').val(),
					postal_code : $('input#billing_postcode').val(),
					city : $('#billing_city').val(),
					street_address : $('input#billing_address_1').val(),
					street_address2 : $('input#billing_address_2').val(),
				},
				shipping_address: {}
			};

			address.shipping_address = $.extend({}, address.billing_address);

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				address.shipping_address.given_name = $('#shipping_first_name').val();
				address.shipping_address.family_name = $('#shipping_last_name').val();
				address.shipping_address.country = $('#shipping_country').val();
				address.shipping_address.region = $('#shipping_state').val();
				address.shipping_address.postal_code = $('input#shipping_postcode').val();
				address.shipping_address.city = $('#shipping_city').val();
				address.shipping_address.street_address = $('input#shipping_address_1').val();
				address.shipping_address.street_address2 = $('input#shipping_address_2').val();
			}

			return address;
		}
	};

	klarna_payments.start();
});
