jQuery( function( $ ) {
	'use strict';

	var klarna_payments = {
		authorization_response: {},
		client_token: false,
		klarna_loaded: false,
		klarna_html: '',

		start: function() {
			$( document.body ).on( 'update_checkout', function() {
				$('.wc-klarna-payments-hide').remove()
			})

			$( document.body ).on( 'updated_checkout', function() {
				// Now that we are filtering into fragments that are getting refreshed, we need to hide unavailable
				// gateways manually.
				$('.wc-klarna-payments-hide + li.wc_payment_method').hide().find('input[type="radio"]').attr('disabled', true)

				// Unblock the payments element if blocked
				var element_data = $('.woocommerce-checkout-payment').data()
				if ( 1 === element_data['blockUI.isBlocked'] ) {
					$('.woocommerce-checkout-payment').unblock();
				}

				if (typeof klarna_payments_params !== undefined) {
					if (!klarna_payments.klarna_loaded) {
						if ('client_token' in klarna_payments_params) {
							klarna_payments.client_token = klarna_payments_params.client_token
							klarna_payments.init().then(klarna_payments.load().done(function (response) {
								klarna_payments.klarna_loaded = true
							}))
						}
					}
				}
			})

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
						// Hide Klarna Payments.
						$('li.payment_method_klarna_payments input[type="radio"]').attr('disabled', true)
						$('li.payment_method_klarna_payments').hide()
					}
				})

				return false
			})
		},

		init: function() {
			var $defer = $.Deferred();

			var klarnaLoadedInterval = setInterval(function() {
				var Klarna = false;

				try {
					Klarna = window.Klarna;
				} catch (e) {
					if ( klarna_payments_params.testmode == true ) {
						console.log(e)
					}
				}

				if (Klarna && Klarna.Credit && !Klarna.Credit.initialized) {
					clearInterval(klarnaLoadedInterval);
					clearTimeout(klarnaLoadedTimeout);

					var data = {
						client_token: klarna_payments.client_token,
					};

					try {
						Klarna.Credit.init(data);
					} catch (e) {
						if ( klarna_payments_params.testmode == true ) {
							console.log(e)
						}
					}

					$defer.resolve();
				}
			}, 100);

			var klarnaLoadedTimeout = setTimeout( function() {
				clearInterval(klarnaLoadedInterval);
				$defer.reject();
			}, 3000);

			return $defer.promise();
		},

		load: function() {
			if ($('#klarna_container').length) {
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

						var options = {
							container: '#klarna_container'
						};

						Klarna.Credit.load(options, function (response) {
							$defer.resolve(response)
						});
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
		}
	}
	klarna_payments.start();
});