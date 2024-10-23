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
				this.toggleCredentials
			);

			$(document).on(
				"click",
				".kp_settings__section_toggle",
				this.toggleSection
			);

			$(document).on(
				"change",
				this.toggleTestModeSelector,
				this.toggleTest
			);

			$(document).on(
				"change",
				"#woocommerce_klarna_payments_kec_theme, #woocommerce_klarna_payments_kec_shape",
				this.changeKecPreview
			);

			$(document).on(
				"change",
				"#woocommerce_klarna_payments_placement_data_key_product, #woocommerce_klarna_payments_onsite_messaging_theme_product, #woocommerce_klarna_payments_placement_data_key_cart, #woocommerce_klarna_payments_onsite_messaging_theme_cart",
				this.changeOsmPreview
			);

			$(document).on("change", this.toggleEuSelector, this.toggleEu);

			// Trigger the change event to set the initial state.
			$(this.toggleTestModeSelector).trigger("change");

			// Update all previews on page load.
			this.updatePreviews();
		},

		toggleCredentials: function (e) {
			e.preventDefault();
			const $this = $(this);
			const $td = $this.parent().parent().find("td");

			// Toggle the kp_settings__credentials_field kp_settings__credentials_field_hidden class
			$td.toggleClass("kp_settings__credentials_field_hidden");

			// Toggle the icon
			$this
				.find("span")
				.toggleClass(kp_admin.openedIcon)
				.toggleClass(kp_admin.closedIcon);
		},

		toggleSection: function (e) {
			e.preventDefault();
			const $this = $(this);
			const $section = $this.closest(".kp_settings__section");
			// Get all the children of the section that is the toggle button.
			const $toggle = $section.find(".kp_settings__section_toggle");
			const $gradient = $section.find(".kp_settings__content_gradient");

			$section.find("table").toggleClass("kp_settings__section_content_hidden");
			$section.find(".kp_settings__section_previews").toggleClass("kp_settings__section_content_hidden");
			$gradient.toggle();

			// Toggle the icon
			$toggle
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

		changeKecPreview: function () {
			let theme = $("#woocommerce_klarna_payments_kec_theme").val();
			let shape = $("#woocommerce_klarna_payments_kec_shape").val();

			if( '' === theme || 'default' === theme ) {
				theme = 'dark';
			}

			const $img = $(
				"#klarna-payments-settings-kec_settings .kp_settings__section_previews img"
			);

			const src = $img.attr("src").replace(/preview-(.*).png/, `preview-${shape}-${theme}.png`);

			$img.attr("src", src);
		},

		changeOsmPreview: function (e) {
			const type = e.target.id.includes("product") ? "product" : "cart";

			let placement = $(`#woocommerce_klarna_payments_placement_data_key_${type}`).val();
			let theme = $(`#woocommerce_klarna_payments_onsite_messaging_theme_${type}`).val();

			const $previewImgs = $(
				`#klarna-payments-settings-onsite_messaging .kp_settings__section_previews img`
			);

			// If we are changing the cart, its the first image, else the second.
			const index = type === "cart" ? 0 : 1;
			const $img = $previewImgs.eq(index);

			// Get the img src.
			const src = $img.attr("src");

			// Split on the last / to get the path and the filename.
			const parts = src.split("/");
			const path = parts.slice(0, -1).join("/");

			if ( 'default' === theme || 'custom' === theme || '' === theme ) {
				theme = 'light';
			}

			if ("" === placement) {
				placement = "credit-promotion-badge";
			}

			const filename = `preview-${type}-${theme}-${placement}.jpg`;

			$img.attr("src", `${path}/${filename}`);
		},

		updatePreviews: function () {
			const $previewTargets = $("#woocommerce_klarna_payments_kec_theme, #woocommerce_klarna_payments_kec_shape, #woocommerce_klarna_payments_placement_data_key_product, #woocommerce_klarna_payments_onsite_messaging_theme_product, #woocommerce_klarna_payments_placement_data_key_cart, #woocommerce_klarna_payments_onsite_messaging_theme_cart");
			$previewTargets.trigger("change");
		}
	};

	kp_admin.init();
});
