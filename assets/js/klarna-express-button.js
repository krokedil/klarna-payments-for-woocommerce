
jQuery(function ($) {
	'use strict';

	if (klarna_payments_express_button_params === undefined) {
		return false;
	}

	/* 
	* If you reload the page or when the mini-cart is updated, the klarna-placement-button might disappear. 
	* In this case, we have to force re-paint the button. This is most likely happening because the window object
	* uses cached data instead of dynamically refetching the klarna-express-button.
	*/
	$(document.body).on('updated_cart_totals added_to_cart removed_from_cart', function () {
		Klarna.ExpressButton.refreshButtons();
	});

	window.klarnaExpressButtonAsyncCallback = function () {
		Klarna.ExpressButton.refreshButtons();

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
					console.log('[SUCCESS] Express Button.');
					/* The data is the URL for the checkout page. */
					window.location.href = response['data'];
				},
				error: function (response) {
					/* The data is an error message. */
					console.warn('[ERROR] Express Button: ', response['data']);
				}
			});
		});
	}
});

