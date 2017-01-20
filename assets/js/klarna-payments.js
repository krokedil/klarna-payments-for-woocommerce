jQuery( function( $ ) {
	'use strict';

	// @TODO: Make sure the form is OK'd by WooCommerce before calling Klarna.Credit.authorize

	var klarna_payments = {
		authorization_token: false,

		start: function() {
			$( document.body ).on( 'updated_checkout', function() {
				klarna_payments.init().then(klarna_payments.load());
			});

			$( 'form.checkout' ).on( 'checkout_place_order_klarna_payments', function() {
				console.log('test');
				console.log('before', klarna_payments.authorization_token);
				klarna_payments.authorize().done(function() {
					console.log('after', klarna_payments.authorization_token);
					$( 'form.checkout' ).append( '<input type="text" name="slbd_name" value="slbd_value" />').submit();
				});

				if ( klarna_payments.authorization_token ) {
					return true;
				} else {
					return false;
				}
			});
		},

		init: function() {
			var $defer = $.Deferred();

			var klarnaLoadedInterval = setInterval(function() {
				var Klarna = false;

				try {
					Klarna = window.Klarna;
				} catch (e) {
					//
				}

				if (Klarna && Klarna.Credit && !Klarna.Credit.initialized) {
					clearInterval(klarnaLoadedInterval);
					clearTimeout(klarnaLoadedTimeout);

					var data = {
						client_token: klarna_payments_params.client_token,
					};

					console.log('****** Klarna Credit - Klarna.Credit.init() ******');
					console.log(data);

					Klarna.Credit.init( data);
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
			var $defer = $.Deferred();

			var klarnaLoadedInterval = setInterval( function() {
				var Klarna = false;

				try {
					Klarna = window.Klarna;
				} catch (e) {
					//
				}

				if (Klarna) {
					clearInterval(klarnaLoadedInterval);
					clearTimeout(klarnaLoadedTimeout);

					var options = {
						container: '#klarna_container'
					};

					// var data = credit.orderData.get();

					console.log('****** Klarna Credit - Klarna.Credit.load() ******');
					console.log(options);

					Klarna.Credit.load(options, function(response) {
						console.log('****** Klarna Credit - Klarna.Credit.load RESPONSE: ******');
						console.log(response);
						$defer.resolve(response);
					});
				}
			}, 100);

			var klarnaLoadedTimeout = setTimeout( function() {
				clearInterval(klarnaLoadedInterval);
				$defer.reject();
			}, 3000);

			return $defer.promise();
		},

		authorize: function() {
			var $defer = $.Deferred();

			console.log('****** Klarna Credit - Klarna.Credit.authorize() ******');
			Klarna.Credit.authorize( {
				purchase_country: "US",
				purchase_currency: "USD",
				locale: "en-US",
				billing_address: {
					given_name: "John",
					family_name: "Doe",
					email: "john@doe.com",
					title: "Mr",
					street_address: "Lombard St 10",
					street_address2: "Apt 214",
					postal_code: "90210",
					city: "Beverly Hills",
					region: "CA",
					phone: "0333444555",
					country: "US"
				},
				shipping_address: {
					given_name: "John",
					family_name: "Doe",
					email: "john@doe.com",
					title: "Mr",
					street_address: "Lombard St 10",
					street_address2: "Apt 214",
					postal_code: "90210",
					city: "Beverly Hills",
					region: "CA",
					phone: "0333444555",
					country: "US"
				}
			}, function(response) {
				console.log('****** Klarna Credit - Klarna.Credit.authorize RESPONSE: ******');
				console.log(response);
				if (response.authorization_token) {
					klarna_payments.authorization_token = response.authorization_token;
					console.log( response.authorization_token );
				}
				$defer.resolve(response);
			});

			return $defer.promise();
		}
	}
	klarna_payments.start();
});