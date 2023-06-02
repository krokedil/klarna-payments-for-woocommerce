/* global console, Klarna */
jQuery( function($) {
	'use strict';

	var klarna_payments = {
		authorization_response: {},
		iframe_loaded: false,
		show_form: false,
		klarna_container_selector: '#klarna_container_2',
		checkout_values: {},
		addresses: {},

		check_changes: function() {
			$('.woocommerce-billing-fields input, .woocommerce-billing-fields select, .woocommerce-shipping-fields input, .woocommerce-shipping-fields select').each(function() {
				var fieldName = $(this).attr('name');
				var fieldValue = $(this).val();
				if ( klarna_payments.checkout_values[ fieldName ] !== fieldValue ) {
					klarna_payments.checkout_values[ fieldName ] = fieldValue;
					$(this).trigger('change');
				}
			});
		},

		debounce_changes: function(func, wait, immediate) {
			var timeout;
			return function() {
				var context = this, args = arguments;
				var later = function() {
					timeout = null;
					if (!immediate) func.apply(context, args);
				};
				var callNow = immediate && !timeout;
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
				if (callNow) func.apply(context, args);
			};
		},

		start: function() {
			// Store all billing and shipping values.
			$(document).ready(function() {
				$('#customer_details input, #customer_details select').each(function() {
					var fieldName = $(this).attr('name');
					var fieldValue = $(this).val();
					klarna_payments.checkout_values[ fieldName ] = fieldValue;
				});
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					klarna_payments.initKlarnaCredit();
					klarna_payments.load().then(klarna_payments.loadHandler);
				}
			});

			/**
			 * When WooCommerce updates checkout
			 * Happens on initial page load, country, state and postal code changes
			 */
			$('body').on('updated_checkout', function() {
				// Unblock the payments element if blocked
				var blocked_el = $('.woocommerce-checkout-payment');
				var blocked_el_data = blocked_el.data();
				if (blocked_el.length && 1 === blocked_el_data['blockUI.isBlocked']) {
					blocked_el.unblock();
				}

				// Check if we need to hide the shipping fields
				klarna_payments.maybeHideShippingAddress();

				if (klarna_payments.isKlarnaPaymentsSelected()) {
					klarna_payments.initKlarnaCredit();
					klarna_payments.load().then(klarna_payments.loadHandler);
				}
			});

			/**
			 * Clear auth token if there's checkout error.
			 */
			$( document.body ).on( 'checkout_error', function() {
				$('input[name="klarna_payments_authorization_token"]').remove();
			});

			/**
			 * Phone field changes. Has to be 5 characters or longer for KP to work.
			 */
			$('form.checkout').on('keyup', '#billing_phone', klarna_payments.debounce_changes(function() {
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					//$('#place_order').attr('disabled', true);
					if ($(this).val().length > 4) {
						klarna_payments.initKlarnaCredit();
						klarna_payments.load().then(klarna_payments.loadHandler);
					}
				}
			}, 750));

			/**
			 * Email field changes, check if WooCommerce says field is valid.
			 */
			$('form.checkout').on('keyup', '#billing_email', klarna_payments.debounce_changes(function() {
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					//$('#place_order').attr('disabled', true);
					if (!$(this).parent().hasClass('woocommerce-invalid')) {
						klarna_payments.initKlarnaCredit();
						klarna_payments.load().then(klarna_payments.loadHandler);
					}
				}
			}, 750));

			/**
			 * Billing company field changes.
			 */
			$('form.checkout').on('keyup', '#billing_company', klarna_payments.debounce_changes(function() {
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					klarna_payments.initKlarnaCredit();
					klarna_payments.load().then(klarna_payments.loadHandler);
				}
			}, 750));

			/**
			 * When changing payment method.
 			 */
			$('body').on('change', 'input[name="payment_method"]', function() {
				// If Klarna Payments is selected and iframe is not loaded yet, disable the form. Also collapse any unselected Klarna Payments gateways.
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					klarna_payments.initKlarnaCredit();
					klarna_payments.load().then(klarna_payments.loadHandler);
					klarna_payments.collapseGateways();
				}

				// Enable the form if any other payment method is selected.
				if (!klarna_payments.isKlarnaPaymentsSelected()) {
					$('#place_order').attr('disabled', false);
				}

				// Check if we need to hide the shipping fields
				klarna_payments.maybeHideShippingAddress();
			});

		},

		load: function() {
			var $defer = $.Deferred();

			// Dont run load during checkout completion.
			if( $('form.checkout').hasClass('processing') ) {
				return $defer.reject();
			}

			var klarna_payments_container_selector_id = '#' + klarna_payments.getSelectorContainerID();
			console.log(klarna_payments_container_selector_id);

			if (klarna_payments_container_selector_id) {

				var klarnaLoadedInterval = setInterval(function () {
					var Klarna = false;

					try {
						Klarna = window.Klarna;
					} catch (e) {
						console.debug(e);
					}

					if (Klarna && Klarna.Payments) {
						clearInterval(klarnaLoadedInterval);
						clearTimeout(klarnaLoadedTimeout);

						var options = {
							container: klarna_payments_container_selector_id,
							payment_method_category: klarna_payments.getSelectedPaymentCategory() === "klarna_payments" ? "" : klarna_payments.getSelectedPaymentCategory()
						};

						if ( 'US' === $('#billing_country').val() ) {
							var address = klarna_payments.get_address_from_checkout_form();

							Klarna.Payments.load(
								options,
								address,
								function (response) {
									$defer.resolve(response);
								}
							);
						} else {
							Klarna.Payments.load(
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
			}
		},

		isKlarnaPaymentsSelected: function () {
			if ($('input[name="payment_method"]:checked').length) {
				var selected_value = $('input[name="payment_method"]:checked').val();
				return selected_value.indexOf('klarna_payments') !== -1;
			}

			return false;
		},

		setRadioButtonValues: function () {
			$('input[name="payment_method"]').each( function( ) {
				if( $(this).val().indexOf( 'klarna_payments' ) !== -1 ) {
					$(this).val( 'klarna_payments' );
				}
			});
			
		},

		getSelectorContainerID: function() {
			var containerID = $('input[name="payment_method"]:checked').attr('id').replace('payment_method_', '');

			return containerID + '_container';
		},

		getSelectedPaymentCategory: function() {
			var selected_category = $('input[name="payment_method"]:checked').attr('id').replace('payment_method_', '');
			console.log( selected_category );
			return selected_category.replace('klarna_payments_', '');
		},

		authorize: function() {
			var $defer = $.Deferred();
			var address = klarna_payments.get_address();

			klarna_payments.authorization_response = {};

			try {
				Klarna.Payments.authorize(
					address,
					{payment_method_category: klarna_payments.getSelectedPaymentCategory() === "klarna_payments" ? "" : klarna_payments.getSelectedPaymentCategory()},
					function (response) {
						klarna_payments.authorization_response = response;
						$defer.resolve(response);
					}
				);
			} catch (e) {
				console.log(e);
			}

			return $defer.promise();
		},

		get_address: function() {
			var address = {
				billing_address: {
					given_name : klarna_payments.addresses.billing.given_name,
					family_name : klarna_payments.addresses.billing.family_name,
					email : klarna_payments.addresses.billing.email,
					phone : klarna_payments.addresses.billing.phone,
					country : klarna_payments.addresses.billing.country,
					region : klarna_payments.addresses.billing.region,
					postal_code : klarna_payments.addresses.billing.postal_code,
					city : klarna_payments.addresses.billing.city,
					street_address : klarna_payments.addresses.billing.street_address,
					street_address2 : klarna_payments.addresses.billing.street_address2,
					organization_name : ( 'b2b' === klarna_payments_params.customer_type ) ? klarna_payments.addresses.billing.organization_name : '',
				},
				shipping_address: {}
			};

			address.shipping_address = $.extend({}, address.billing_address);

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				address.shipping_address.given_name = klarna_payments.addresses.shipping.given_name;
				address.shipping_address.family_name = klarna_payments.addresses.shipping.family_name;
				address.shipping_address.country = klarna_payments.addresses.shipping.country;
				address.shipping_address.region = klarna_payments.addresses.shipping.region;
				address.shipping_address.postal_code = klarna_payments.addresses.shipping.postal_code;
				address.shipping_address.city = klarna_payments.addresses.shipping.city;
				address.shipping_address.street_address = klarna_payments.addresses.shipping.street_address;
				address.shipping_address.street_address2 = klarna_payments.addresses.shipping.street_address2;
			}

			return address;
		},

		get_address_from_checkout_form: function() {
			var address = {
				billing_address: {
					given_name : klarna_payments.checkout_values.billing_first_name,
					family_name : klarna_payments.checkout_values.billing_last_name,
					email : klarna_payments.checkout_values.billing_email,
					phone : klarna_payments.checkout_values.billing_phone,
					country : klarna_payments.checkout_values.billing_country,
					region : klarna_payments.checkout_values.billing_state,
					postal_code : klarna_payments.checkout_values.billing_postcode,
					city : klarna_payments.checkout_values.billing_city,
					street_address : klarna_payments.checkout_values.billing_address_1,
					street_address2 : klarna_payments.checkout_values.billing_address_2,
					organization_name : ( 'b2b' === klarna_payments_params.customer_type ) ? klarna_payments.checkout_values.billing_company : '',
				},
				shipping_address: {}
			};

			address.shipping_address = $.extend({}, address.billing_address);

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				address.shipping_address.given_name = klarna_payments.checkout_values.shipping_first_name;
				address.shipping_address.family_name = klarna_payments.checkout_values.shipping_last_name;
				address.shipping_address.country = klarna_payments.checkout_values.shipping_country;
				address.shipping_address.region = klarna_payments.checkout_values.shipping_state;
				address.shipping_address.postal_code = klarna_payments.checkout_values.shipping_postcode;
				address.shipping_address.city = klarna_payments.checkout_values.shipping_city;
				address.shipping_address.street_address = klarna_payments.checkout_values.shipping_address_1;
				address.shipping_address.street_address2 = klarna_payments.checkout_values.shipping_address_2;
			}

			return address;
		},

		collapseGateways: function() {
			$('input[name="payment_method"]').each( function() {
				if ( $(this).is( ':checked' ) ){
					$(this).siblings("div.payment_box").show();
				} else {
					$(this).siblings("div.payment_box").hide();
				}
			});
		},

		maybeHideShippingAddress: function() {
			if( false !== klarna_payments.isKlarnaPaymentsSelected() ) {
				if( 'b2b' === klarna_payments_params.customer_type ) {
					jQuery('#customer_details .col-2').hide();
				}
			} else {
				jQuery('#customer_details .col-2').show();
			}
		},

		initKlarnaCredit: function ( client_token ) {
			var client_token = klarna_payments.getClientToken();
			window.klarnaInitData = {client_token: client_token};
			Klarna.Payments.init(klarnaInitData);
		},

		printErrorMessage: function( message ) {
			$('#klarna-payments-error-notice').remove();
			$('form.checkout').prepend( '<div id="klarna-payments-error-notice" class="woocommerce-NoticeGroup"><ul class="woocommerce-error" role="alert"><li>' +  message + '</li></ul></div>' );
			$.scroll_to_notices( $('form.checkout') );
		},

		authorizeKlarnaOrder: function( order_id ) {
			klarna_payments.authorize().done( function( response ) {
				if ('authorization_token' in response) {
					$('body').trigger( 'kp_auth_success' );
					$.ajax(
						klarna_payments_params.place_order_url,
						{
							type: "POST",
							dataType: "json",
							async: true,
							data: {
								order_id: order_id,
								auth_token: klarna_payments.authorization_response.authorization_token,
								nonce: klarna_payments_params.place_order_nonce,
							},
							success: function (response) {
								// Log the success.
								console.log('kp_place_order success');
								console.log(response);
							},
							error: function (response) {
								// Log the error.
								console.log('kp_place_order error');
								console.log(response);
							},
							complete: function (response) {
								if( response.responseJSON.success === true ) {
									window.location.href = response.responseJSON.data;
								} else {
									$('form.checkout').removeClass('processing').unblock();
									$('form.checkout').find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
									$('form.checkout').trigger('update_checkout');
								}
							}
						}
					);
				} else {
					$('body').trigger( 'kp_auth_failed' );

					// Re-enable the form.
					$( 'body' ).trigger( 'updated_checkout' );
					$( 'form.checkout' ).removeClass( 'processing' );
					$( 'form.checkout' ).unblock();
					$( '.woocommerce-checkout-review-order-table' ).unblock();

					console.log('No authorization_token in response');
					$('form.woocommerce-checkout').removeClass( 'processing' ).unblock();
					$.ajax(
						klarna_payments_params.auth_failed_url,
						{
							type: "POST",
							dataType: "json",
							async: true,
							data: {
								show_form: response.show_form,
								order_id: json.order_id,
								nonce: klarna_payments_params.auth_failed_nonce
							},
						}
					);
				}
			});
		},

		klarnaPayForOrder: function( event ) {
			if( klarna_payments.isKlarnaPaymentsSelected() ) {
				event.preventDefault();
				klarna_payments.addresses = klarna_payments_params.addresses;
				klarna_payments.authorizeKlarnaOrder( klarna_payments_params.order_id );
			}
		},

		getClientToken: function() {
			if( $('#kp_client_token').length > 0 ) {
				return $('#kp_client_token').val();
			} else {
				return klarna_payments_params.client_token;
			}
		},

		orderSubmit: function( event ) {
			if( klarna_payments.isKlarnaPaymentsSelected() ) {
				event.preventDefault();

				if( $('form.checkout').is('.processing') ) {
					return false;
				}

				$('form.checkout').addClass('processing');
				$( '.woocommerce-checkout-review-order-table' ).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$.ajax({
					type: 'POST',
					url: klarna_payments_params.submit_order,
					data: $('form.checkout').serialize(),
					dataType: 'json',
					success: function( data ) {
						try {
							if ( 'success' === data.result ) {
								klarna_payments.logToFile( 'Successfully placed order. Starting authorization with Klarna' );	
								klarna_payments.addresses = data.addresses
								klarna_payments.authorizeKlarnaOrder( data.order_id );
							} else {
								throw 'Result failed';
							}
						} catch ( err ) {
							if ( data.messages )  {
								klarna_payments.logToFile( 'Checkout error | ' + data.messages );
								klarna_payments.failOrder( 'submission', data.messages );
							} else {
								klarna_payments.logToFile( 'Checkout error | ' + err );
								klarna_payments.failOrder( 'submission', '<div class="woocommerce-error">' + 'Checkout error' + '</div>' );
							}
						}
					},
					error: function( data ) {
						try {
							klarna_payments.logToFile( 'AJAX error | ' + JSON.stringify(data) );
						} catch( e ) {
							klarna_payments.logToFile( 'AJAX error | Failed to parse error message.' );
						}
						klarna_payments.failOrder( 'ajax-error', '<div class="woocommerce-error">Internal Server Error</div>' );
					}
				});
			}
		},

		/**
		 * Fails the order with Klarna on a checkout error and timeout.
		 * @param {string} event 
		 */
		failOrder: function( event, error_message ) {

			// Re-enable the form.
			$( 'body' ).trigger( 'updated_checkout' );
			$( 'form.checkout' ).removeClass( 'processing' );
			$( 'form.checkout' ).unblock();
			$( '.woocommerce-checkout-review-order-table' ).unblock();

			// Print error messages, and trigger checkout_error, and scroll to notices.
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			$( 'form.checkout' ).prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
			$( 'form.checkout' ).removeClass( 'processing' ).unblock();
			$( 'form.checkout' ).find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			$( document.body ).trigger( 'checkout_error' , [ error_message ] );
			$.scroll_to_notices( $('form.checkout') );
		},

		/**
		 * Logs the message to the klarna payments log in WooCommerce.
		 * @param {string} message 
		 */
		logToFile: function( message ) {
			$.ajax(
				{
					url: klarna_payments_params.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message: message,
						nonce: klarna_payments_params.log_to_file_nonce
					}
				}
			);
		},
		
	};
	klarna_payments.start();
	$('body').ready( function() {
		klarna_payments.setRadioButtonValues();
	});

	$('body').ajaxComplete( function() {
		klarna_payments.setRadioButtonValues();
	});

	$('body').on('click', 'input#place_order, button#place_order', function (e) {
		// No strict comparison: wp_localize_script() converts booleans to strings "1", respectively, "0".
		if( true == klarna_payments_params.pay_for_order ) {
			klarna_payments.klarnaPayForOrder( e );
		} else {
			klarna_payments.orderSubmit( e );
		}
	});
});
