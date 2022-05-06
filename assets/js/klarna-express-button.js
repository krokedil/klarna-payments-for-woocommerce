
jQuery(function ($) {
	'use strict';

	if (klarna_payments_express_button_params === undefined) {
		return false;
	}

	window.klarnaExpressButtonAsyncCallback = function () {
		Klarna.ExpressButton.on('user-authenticated', function (callbackData) {
			console.log('Klarna Express Button', callbackData);
			$.ajax({
				type: 'POST',
				data: {
					message: callbackData,
					nonce: klarna_payments_express_button_params.express_button_nonce,
				},
				dataType: 'JSON',
				url: klarna_payments_express_button_params.express_button_url,
				success: function (response) {
					console.log('[SUCCESS] Express Button.')
					/* The data is the URL for the checkout page. */
					window.location.href = response['data'];
				},
				error: function (response) {
					/* The data is an error message. */
					console.warn('[ERROR] Express Button: ', response['data'])
				}
			});
		});
	}
});

