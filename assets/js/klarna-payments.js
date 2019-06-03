/* global console, Klarna */
jQuery( function($) {
	'use strict';

	var klarna_payments = {
		authorization_response: {},
		iframe_loaded: false,
		show_form: false,
		klarna_container_selector: '#klarna_container_2',
		checkout_values: {},

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
			// Add page visibility listener to handle tab changes.
			klarna_payments.page_visibility_listener();

			// Store all billing and shipping values.
			$(document).ready(function() {
				$('#customer_details input, #customer_details select').each(function() {
					var fieldName = $(this).attr('name');
					var fieldValue = $(this).val();
					klarna_payments.checkout_values[ fieldName ] = fieldValue;
				});

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

				// If Klarna Payments is selected and iframe is not loaded yet, disable the form.
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					//$('#place_order').attr('disabled', true);
					klarna_payments.load().then(klarna_payments.loadHandler);
				}

				// Check if we need to hide the shipping fields
				klarna_payments.maybeHideShippingAddress();
			});

			/**
			 * Clear auth token if there's checkout error.
			 */
			$( document.body ).on( 'checkout_error', function() {
				$('input[name="klarna_payments_authorization_token"]').remove();
			});

			/**
			 * When any of the checkout form fields changes, if Klarna Payments is the selected option.
			 */
			$('form.checkout').on('change', '.woocommerce-billing-fields input, .woocommerce-billing-fields select', function() {
				// Make sure all WC required fields are populated.
				if (!klarna_payments.check_required_fields()) {
					//$('#place_order').attr('disabled', true);
				} else {
					$('#place_order').attr('disabled', false);
				}
			});

			/**
			 * Phone field changes. Has to be 5 characters or longer for KP to work.
			 */
			$('form.checkout').on('keyup', '#billing_phone', klarna_payments.debounce_changes(function() {
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					//$('#place_order').attr('disabled', true);
					if ($(this).val().length > 4) {
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
						klarna_payments.load().then(klarna_payments.loadHandler);
					}
				}
			}, 750));

			/**
			 * Billing company field changes.
			 */
			$('form.checkout').on('keyup', '#billing_company', klarna_payments.debounce_changes(function() {
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					$('#place_order').attr('disabled', true);
						klarna_payments.load().then(klarna_payments.loadHandler);
				}
			}, 750));

			/**
			 * When changing payment method.
 			 */
			$('form.checkout').on('change', 'input[name="payment_method"]', function() {
				// If Klarna Payments is selected and iframe is not loaded yet, disable the form. Also collapse any unselected Klarna Payments gateways.
				if (klarna_payments.isKlarnaPaymentsSelected()) {
					//$('#place_order').attr('disabled', true);
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


			/**
			 * Do this every 100ms in case browser auto-fill changes form fields.
			 */
			var checkFormInterval = setInterval(function () {
				if (klarna_payments.show_form) {
					clearInterval(checkFormInterval);
				}

				if (klarna_payments.isKlarnaPaymentsSelected()) {
					klarna_payments.check_changes();
				}
			}, 200);

			/**
			 * Hooking into WooCommerce.
			 *
			 * Firing Klarna.Credit.authorize(), then once it resolves, adding the hidden form field and re-submitting the form.
 			 */
			$('form.checkout').on('checkout_place_order', function() {
				if (!klarna_payments.isKlarnaPaymentsSelected()) {
					return true;
				}

				if ($('input[name="klarna_payments_authorization_token"]').length) {
					return true;
				}
				var required_fields_set = true;
				var failed_fields = [];
				$('.validate-required').find('input').each(function( nr, field ) {
					if( $(field).attr('type') !== 'hidden' && $(field).attr('id').indexOf( 'shipping' ) < 0 && $(field).attr('id').indexOf( 'account' ) < 0 ) {
						if( $(field).is(':checkbox') ) {
							if( $(field).is(":checked") !== true ) {
								required_fields_set = false;
								failed_fields.push( $(field).attr('id') );
								$(field).parents('p.form-row').addClass( 'woocommerce-invalid' );
							}
						} else if( $(field).val() === '' && $(field).parents('#payment').length < 0 ) {
							required_fields_set = false;
							failed_fields.push( $(field).attr('id') );
							$(field).parents('p.form-row').addClass( 'woocommerce-invalid' );
						}
					}
				});
				if( required_fields_set ) {
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
				} else {
					$('ul.woocommerce-error').remove();
					$('form.checkout').prepend( '<ul class="woocommerce-error" role="alert"></ul>' );
					var checkbox = false;
					$.each( failed_fields, function( key, value ) {
						if( $( '#' + value ).is(':checkbox') && checkbox === false ) {
							checkbox = true;
							$('ul.woocommerce-error').append( '<li>' + klarna_payments_params.failed_checkbox_validation_text + '</li>' );
						} else if( ! $( '#' + value ).is(':checkbox' ) ) {
							var field_name = $( '#' + value ).attr( 'name' );
							var field_label = $('label[for="' +  field_name +'"]').text().replace( '*', '' );
							$('ul.woocommerce-error').append( '<li><strong>' + field_label + '</strong>' + klarna_payments_params.failed_field_validation_text + '</li>' );
						}
					});
					var etop = $('form.checkout').offset().top;
					$('html, body').animate({
						scrollTop: etop
					}, 1000);
				}

				return false;
			});
		},

		load: function() {
			var klarna_payments_container_selector_id = '#' + klarna_payments.getSelectorContainerID();
			console.log(klarna_payments_container_selector_id);

			if (klarna_payments_container_selector_id) {
				var $defer = $.Deferred();

				var klarnaLoadedInterval = setInterval(function () {
					var Klarna = false;

					try {
						Klarna = window.Klarna;
					} catch (e) {
						console.debug(e);
					}

					if (Klarna && Klarna.Credit) {
						clearInterval(klarnaLoadedInterval);
						clearTimeout(klarnaLoadedTimeout);

						var options = {
							container: klarna_payments_container_selector_id,
							payment_method_category: klarna_payments.getSelectedPaymentCategory()
						};

						if ( 'US' === $('#billing_country').val() ) {
							var address = klarna_payments.get_address();

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
				if ( klarna_payments.check_required_fields() ) {
					$('#place_order').attr('disabled', false);
				}
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
			return $('input[name="payment_method"]:checked').parent().find('.klarna_payments_container').attr('id');
		},

		getSelectedPaymentCategory: function() {
			var selected_category = $('input[name="payment_method"]:checked').parent().find('.klarna_payments_container').data('payment_method_category');
			console.log( selected_category );
			return selected_category.replace('klarna_payments_', '');
		},

		authorize: function() {
			var $defer = $.Deferred();
			var address = klarna_payments.get_address();

			klarna_payments.authorization_response = {};

			try {
				Klarna.Credit.authorize(
					address,
					{payment_method_category: klarna_payments.getSelectedPaymentCategory()},
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

			if (typeof document.hidden !== 'undefined') { // Opera 12.10 and Firefox 18 and later support
				hidden = 'hidden';
				visibilityChange = 'visibilitychange';
			} else if (typeof document.msHidden !== 'undefined') {
				hidden = 'msHidden';
				visibilityChange = 'msvisibilitychange';
			} else if (typeof document.webkitHidden !== 'undefined') {
				hidden = 'webkitHidden';
				visibilityChange = 'webkitvisibilitychange';
			}

			// Warn if the browser doesn't support addEventListener or the Page Visibility API
			if (typeof document.addEventListener === 'undefined' || typeof document[hidden] === 'undefined') {
			} else {
				// Handle page visibility change
				document.addEventListener(visibilityChange, handleVisibilityChange, false);
			}

			function handleVisibilityChange() {
				if (! document[hidden]) {
					if (klarna_payments.isKlarnaPaymentsSelected()) {
						//$('body').trigger('update_checkout');
					}
				}
			}
		},

		get_address: function() {
			var address = {
				billing_address: {
					given_name : $(klarna_payments_params.default_checkout_fields.billing_given_name).val(),
					family_name : $(klarna_payments_params.default_checkout_fields.billing_family_name).val(),
					email : $(klarna_payments_params.default_checkout_fields.billing_email).val(),
					phone : $(klarna_payments_params.default_checkout_fields.billing_phone).val(),
					country : $(klarna_payments_params.default_checkout_fields.billing_country).val(),
					region : $(klarna_payments_params.default_checkout_fields.billing_region).val(),
					postal_code : ( klarna_payments_params.remove_postcode_spaces === 'yes' ) ? $(klarna_payments_params.default_checkout_fields.billing_postal_code).val().replace(/\s/g, '') : $(klarna_payments_params.default_checkout_fields.billing_postal_code).val(),
					city : $(klarna_payments_params.default_checkout_fields.billing_city).val(),
					street_address : $(klarna_payments_params.default_checkout_fields.billing_street_address).val(),
					street_address2 : $(klarna_payments_params.default_checkout_fields.billing_street_address2).val(),
					organization_name : ( 'b2b' === klarna_payments_params.customer_type ) ? $(klarna_payments_params.default_checkout_fields.billing_company).val() : '',
				},
				shipping_address: {}
			};

			address.shipping_address = $.extend({}, address.billing_address);

			if ( $( '#ship-to-different-address' ).find( 'input' ).is( ':checked' ) ) {
				address.shipping_address.given_name = $(klarna_payments_params.default_checkout_fields.shipping_given_name).val();
				address.shipping_address.family_name = $(klarna_payments_params.default_checkout_fields.shipping_family_name).val();
				address.shipping_address.country = $(klarna_payments_params.default_checkout_fields.shipping_country).val();
				address.shipping_address.region = $(klarna_payments_params.default_checkout_fields.shipping_region).val();
				address.shipping_address.postal_code = ( klarna_payments_params.remove_postcode_spaces === 'yes' ) ? $(klarna_payments_params.default_checkout_fields.shipping_postal_code).val().replace(/\s/g, '') : $(klarna_payments_params.default_checkout_fields.shipping_postal_code).val();
				address.shipping_address.city = $(klarna_payments_params.default_checkout_fields.shipping_city).val();
				address.shipping_address.street_address = $(klarna_payments_params.default_checkout_fields.shipping_street_address).val();
				address.shipping_address.street_address2 = $(klarna_payments_params.default_checkout_fields.shipping_street_address2).val();
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
		}
	};

	klarna_payments.start();
	$('body').ready( function() {
		klarna_payments.setRadioButtonValues();
	});
	$('body').ajaxComplete( function() {
		klarna_payments.setRadioButtonValues();
	});
});
