jQuery(function ($) {
	'use strict';

	var titles = $('h3.wc-settings-sub-title');
	var tables = $('h3.wc-settings-sub-title + table.form-table');
	var submit = $('.wrap.woocommerce p.submit');

	titles.append(' <a href="#" style="font-size:12px; font-weight: normal; text-decoration: none">[expand]</a>');
	tables.css('marginLeft', '20px').hide();
	titles.find('a').addClass('collapsed');
	titles.find('a').click(function (e) {
		e.preventDefault();

		if ($(this).hasClass('collapsed')) {
			$(this).parent().next().show();
			$(this).removeClass('collapsed');
			$(this).text('[collapse]');
		} else {
			$(this).parent().next().hide();
			$(this).addClass('collapsed');
			$(this).text('[expand]');
		}
	});

	titles.first().before('<hr style="margin-top:2em;margin-bottom:2em" />');
	titles.last().before('<hr style="margin-top:2em;margin-bottom:2em" />');
	tables.last().after('<hr style="margin-top:2em;margin-bottom:2em" />');
});

/*document.addEventListener('DOMContentLoaded', () => {
	// On page start, check if Testmode is enabled
	const testmode_checkbox = document.getElementById('woocommerce_klarna_payments_testmode');
	testmode_checkbox.addEventListener('change', () => {
		checkButtonValues();
	})

	let test_merchat_ids = document.querySelectorAll('[id^=woocommerce_klarna_payments_test_merchant_]')
	let test_shared_secrets = document.querySelectorAll('[id^=woocommerce_klarna_payments_test_shared_]')

	let test_id_array = [];
	let test_secret_array = [];

	// Handle Merchant ID input fields logic
	test_merchat_ids.forEach(element => {
		element.addEventListener('change', () => {
			if (element.value != '') {
				if (!test_id_array.includes(element.id.slice(-2))) {
					test_id_array.push(element.id.slice(-2))
				}
				checkButtonValues();
			} else {
				for (var i = test_id_array.length - 1; i >= 0; i--) {
					if (test_id_array[i] === element.id.slice(-2)) {
						test_id_array.splice(i, 1);
					}
				}
				checkButtonValues();
			}
		})

		if (element.value != '') {
			test_id_array.push(element.id.slice(-2))
		}
	});

	// Handle Shared Secret Key input fields logic
	test_shared_secrets.forEach(element => {
		element.addEventListener('change', () => {
			if (element.value != '') {
				if (!test_secret_array.includes(element.id.slice(-2))) {
					test_secret_array.push(element.id.slice(-2))
				}
				checkButtonValues();
			} else {
				for (var i = test_secret_array.length - 1; i >= 0; i--) {
					if (test_secret_array[i] === element.id.slice(-2)) {
						test_secret_array.splice(i, 1);
					}
				}
				checkButtonValues();
			}
		})

		if (element.value != '') {
			test_secret_array.push(element.id.slice(-2))
		}
	});

	// Show/Hide Submit button logic
	const checkButtonValues = () => {
		if (testmode_checkbox.checked == true) {
			const found = test_id_array.some(r => test_secret_array.includes(r))
			if (found !== true) {
				alert('Please insert both test credentials for at least one country')
				save_button = document.getElementsByName('save');
				save_button[0].setAttribute('disabled', true);
			} else {
				save_button = document.getElementsByName('save');
				save_button[0].removeAttribute('disabled');
			}
		} else {
			save_button = document.getElementsByName('save');
			save_button[0].removeAttribute('disabled');
		}
	}
})*/
