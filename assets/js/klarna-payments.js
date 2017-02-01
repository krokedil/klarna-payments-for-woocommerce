jQuery( function( $ ) {
	'use strict';

	var klarna_payments = {
		authorization_response: {},
		client_token: false,

		start: function() {
			$( document.body ).on( 'updated_checkout', function() {
				if (typeof klarna_payments_params !== undefined) {
					if (klarna_payments_params.hasOwnProperty('client_token')) {
						klarna_payments.client_token = klarna_payments_params.client_token
						klarna_payments.init().then(klarna_payments.load());
					}
				}

				// @TODO: Improve error handling on authorize, currently it just keeps submitting
				$( 'form.checkout' ).on( 'checkout_place_order_klarna_payments', function() {
					// If we don't have response, call Klarna.Credit.authorize
					if ( ! klarna_payments.authorization_response.hasOwnProperty('authorization_token') ) {
						console.log(klarna_payments.authorization_response)

						if ( klarna_payments.authorization_response.show_form ) {
							if ( ! klarna_payments.authorization_response.show_form ) {
								return false;
							}
						}
						
						klarna_payments.authorize().done( function( response ) {
							$( 'form.checkout' ).append( '<input type="hidden" name="klarna_payments_authorization_token" value="' + klarna_payments.authorization_response.authorization_token + '" />').submit();
						} );

						return false;
					} else {
						if ( klarna_payments.authorization_response.approved ) {
							return true
						} else if ( klarna_payments.authorization_response.show_form ) {
							// Fix the form, try again.
							// klarna_payments.authorization_response = {}
							console.log(klarna_payments.authorization_response.show_form)

							return false;
						} else {
							// Hide Klarna Payments.
							// @TODO: Figure out what to do when KP is the only payment method
							$('li.payment_method_klarna_payments input[type="radio"]').attr('disabled', true)
							$('li.payment_method_klarna_payments').hide()

							return false;
						}
					}
				});
			});
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
							$defer.resolve(response);
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
				console.log(response)
				console.log('test')
				klarna_payments.authorization_response = response;
				// if ( klarna_payments.authorization_response.hasOwnProperty('authorization_token') ) {
					$defer.resolve(response)
				// } else {
					// $defer.reject(response)
				// }
			});

			return $defer.promise();
		}
	}
	klarna_payments.start();
});