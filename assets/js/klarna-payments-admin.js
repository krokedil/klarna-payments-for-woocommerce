jQuery(function ($) {
	'use strict';
	const kp_admin = {
		openedIcon: "dashicons-arrow-up-alt2",
		closedIcon: "dashicons-arrow-down-alt2",

		toggleTestModeSelector: "#woocommerce_klarna_payments_testmode",
		toggleEuSelector: "#woocommerce_klarna_payments_combine_eu_credentials",

		init: function () {
			$(document).on(
				"click",
				".kp_settings__fields_toggle",
				this.openCredentials
			);
			$(document).on(
				"change",
				this.toggleTestModeSelector,
				this.toggleTest
			);
			$(document).on("change", this.toggleEuSelector, this.toggleEu);
		},

		openCredentials: function (e) {
			e.preventDefault();
			const $this = $(this);
			const $td = $this.parent().parent().find("td");
			console.log($td);

			// Toggle the kp_settings__credentials_field kp_settings__credentials_field_hidden class
			$td.toggleClass("kp_settings__credentials_field_hidden");

			// Toggle the icon
			$this
				.find("span")
				.toggleClass(kp_admin.openedIcon)
				.toggleClass(kp_admin.closedIcon);
		},

		toggleEu: function () {
			const eu = $(kp_admin.toggleEuSelector).is(":checked");

			const $wrappers = $(".kp_settings__credentials");
			const $euRegion = $wrappers.filter("[data-eu-region]");
			const $euCountry = $wrappers.filter("[data-eu-country]");

			if (eu) {
				$euRegion.show();
				$euCountry.hide();
			} else {
				$euRegion.hide();
				$euCountry.show();
			}
		},

		toggleTest: function () {
			const test = $(kp_admin.toggleTestModeSelector).is(":checked");

			const $wrappers = $(".kp_settings__credentials");
			const $prod = $wrappers.find(
				".kp_settings__production_credentials"
			);
			const $test = $wrappers.find(".kp_settings__test_credentials");

			if (test) {
				$prod.hide();
				$test.show();
			} else {
				$prod.show();
				$test.hide();
			}
		},
	};

	kp_admin.init();
});
