jQuery( function( $ ) {
	'use strict';

	var klarna_payments = {
		authorization_response: {},
		iframe_loaded: false,
		// klarna_html: '',
		klarna_container_selector: '#klarna_container',

		start: function() {
			$( document.body ).on( 'update_checkout', function() {
				$('')
			})

			$('body').on('updated_checkout', function() {
				// Unblock the payments element if blocked
				var element_data = $('.woocommerce-checkout-payment').data()
				if ( 1 === element_data['blockUI.isBlocked'] ) {
					$('.woocommerce-checkout-payment').unblock()
				}

				// If Klarna Payments is selected and iframe is not loaded yet, disable the form.
				if ( 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val() ) {
					$('#place_order').attr('disabled', true)

					if ( klarna_payments.check_required_fields() ) {
						klarna_payments.load().then(function (response) {
							klarna_payments.iframe_loaded = true
							$('#place_order').attr('disabled', false)
						})
					}
				}
			})

			$('form.checkout').on('change', 'input, select', function() {
				if ( 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val() ) {
					if ( klarna_payments.check_required_fields() ) {
						$('#place_order').attr('disabled', true)

						klarna_payments.load().then(function (response) {
							klarna_payments.iframe_loaded = true
							if (! response.error) {
								$('#place_order').attr('disabled', false)
							}
						})
					}
				}

				// When changing payment method.
				if ( 'payment_method' === $(this).attr('name') ) {
					// If Klarna Payments is selected and iframe is not loaded yet, disable the form.
					if (!klarna_payments.iframe_loaded && 'klarna_payments' === jQuery('input[name="payment_method"]:checked').val()) {
						$('#place_order').attr('disabled', true)
					}

					// Enable the form if any other payment method is selected.
					if ('klarna_payments' !== jQuery('input[name="payment_method"]:checked').val()) {
						$('#place_order').attr('disabled', false)
					}
				}
			})

			// Hooking into WooCommerce.
			$( 'form.checkout' ).on( 'checkout_place_order_klarna_payments', function() {
				if ($('input[name="klarna_payments_authorization_token"]').length) {
					return true
				}

				klarna_payments.authorize().done( function(response) {
					console.log(response)

					if ('authorization_token' in response) {
						$('input[name="klarna_payments_authorization_token"]').remove()
						$('form.checkout').append('<input type="hidden" name="klarna_payments_authorization_token" value="' + klarna_payments.authorization_response.authorization_token + '" />').submit()
					}

					if (false === response.show_form) {
						// @TODO: Do not do this
						// Hide Klarna Payments.
						$('li.payment_method_klarna_payments input[type="radio"]').attr('disabled', true)
						$('li.payment_method_klarna_payments').hide()
					}
				})

				return false
			})
		},

		load: function() {
			if ($(klarna_payments.klarna_container_selector).length) {
				var $defer = $.Deferred();

				var klarnaLoadedInterval = setInterval(function () {
					var Klarna = false;

					try {
						Klarna = window.Klarna;
					} catch (e) {
						//
					}

					if (Klarna && Klarna.Credit.initialized) {
						clearInterval(klarnaLoadedInterval);
						clearTimeout(klarnaLoadedTimeout);

						// @TODO: Currently using billing phone and email for shipping details, check if this is OK
						var first_name = $('#billing_first_name').val(),
							last_name = $('#billing_last_name').val(),
							email = $('#billing_email').val(),
							phone = $('#billing_phone').val(),
							country = $('#billing_country').val(),
							state = $('#billing_state').val(),
							postcode = $('input#billing_postcode').val(),
							city = $('#billing_city').val(),
							address = $('input#billing_address_1').val(),
							address_2 = $('input#billing_address_2').val(),

							s_first_name = first_name,
							s_last_name = last_name,
							s_country = country,
							s_state = state,
							s_postcode = postcode,
							s_city = city,
							s_address = address,
							s_address_2 = address_2,
							s_phone = phone,
							s_email = email;

						if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
							s_first_name = $('#shipping_first_name').val();
							s_last_name = $('#shipping_last_name').val();
							s_country = $('#shipping_country').val();
							s_state = $('#shipping_state').val();
							s_postcode = $('input#shipping_postcode').val();
							s_city = $('#shipping_city').val();
							s_address = $('input#shipping_address_1').val();
							s_address_2 = $('input#shipping_address_2').val();
						}

						var options = {
							container: klarna_payments.klarna_container_selector
						};

						Klarna.Credit.load(
							options,
							{
								billing_address: {
									given_name: first_name,
									family_name: last_name,
									email: email,
									// title: "Mr",
									street_address: address,
									street_address2: address_2,
									postal_code: postcode,
									city: city,
									region: state,
									phone: phone,
									country: country
								},
								shipping_address: {
									given_name: s_first_name,
									family_name: s_last_name,
									email: s_email,
									// title: "Mr",
									street_address: s_address,
									street_address2: s_address_2,
									postal_code: s_postcode,
									city: s_city,
									region: s_state,
									phone: s_phone,
									country: s_country
								}
							},
							function (response) {
								$defer.resolve(response)
							}
						);
					}
				}, 100);

				var klarnaLoadedTimeout = setTimeout(function () {
					clearInterval(klarnaLoadedInterval);
					$defer.reject();
				}, 3000);

				return $defer.promise();
			}
		},

		authorize: function() {
			var $defer = $.Deferred();
			klarna_payments.authorization_response = {}

			// @TODO: Currently using billing phone and email for shipping details, check if this is OK
			var first_name = $('#billing_first_name').val(),
				last_name = $('#billing_last_name').val(),
				email = $('#billing_email').val(),
				phone = $('#billing_phone').val(),
				country = $('#billing_country').val(),
				state = $('#billing_state').val(),
				postcode = $('input#billing_postcode').val(),
				city = $('#billing_city').val(),
				address = $('input#billing_address_1').val(),
				address_2 = $('input#billing_address_2').val(),

				s_first_name = first_name,
				s_last_name = last_name,
				s_country = country,
				s_state = state,
				s_postcode = postcode,
				s_city = city,
				s_address = address,
				s_address_2 = address_2,
				s_phone = phone,
				s_email = email;

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				s_first_name = $('#shipping_first_name').val();
				s_last_name = $('#shipping_last_name').val();
				s_country = $('#shipping_country').val();
				s_state = $('#shipping_state').val();
				s_postcode = $('input#shipping_postcode').val();
				s_city = $('#shipping_city').val();
				s_address = $('input#shipping_address_1').val();
				s_address_2 = $('input#shipping_address_2').val();
			}

			Klarna.Credit.authorize( {
				purchase_country: "US",
				purchase_currency: "USD",
				locale: "en-US",
				billing_address: {
					given_name: first_name,
					family_name: last_name,
					email: email,
					// title: "Mr",
					street_address: address,
					street_address2: address_2,
					postal_code: postcode,
					city: city,
					region: state,
					phone: phone,
					country: country
				},
				shipping_address: {
					given_name: s_first_name,
					family_name: s_last_name,
					email: s_email,
					// title: "Mr",
					street_address: s_address,
					street_address2: s_address_2,
					postal_code: s_postcode,
					city: s_city,
					region: s_state,
					phone: s_phone,
					country: s_country
				}
			}, function(response) {
				klarna_payments.authorization_response = response;
				$defer.resolve(response)
			});

			return $defer.promise();
		},

		check_required_fields: function() {
			var input_value
			var input_flag = false

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				$('.woocommerce-billing-fields .validate-required[id^="billing"], .woocommerce-shipping-fields .validate-required[id^="shipping"]').each(function() {
					if ( $(this).find('select').length ) {
						input_value = $(this).find('select').val()
					} else {
						input_value = $(this).find('input').val()
					}

					if ('' === input_value || undefined === input_value) {
						input_flag = true
					}
				})
			} else {
				$('.woocommerce-billing-fields .validate-required[id^="billing"]').each(function() {
					if ( $(this).find('select').length ) {
						input_value = $(this).find('select').val()
					} else {
						input_value = $(this).find('input').val()
					}

					if ('' === input_value || undefined === input_value) {
						input_flag = true
					}
				})
			}

			if ( input_flag ) {
				return false
			} else {
				return true
			}
		}
	}
	klarna_payments.start();
});