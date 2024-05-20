jQuery(function ($) {
	'use strict';
	const kp_admin = {
		openedIcon : 'dashicons-arrow-up-alt2',
		closedIcon : 'dashicons-arrow-down-alt2',

		init: function () {
			$(document).on('click', '.kp_settings__fields_toggle', this.toggle);
		},

		toggle: function (e) {
			e.preventDefault();
			const $this = $(this);

			// Get the data-field-key attribute
			const fieldKey = $this.data('field-key');

			// Get all fields with the same data-field-key attribute
			const $fields = $(`[data-field-key="${fieldKey}"]`);

			// Toggle the kp_settings__credentials_field kp_settings__credentials_field_hidden class
			$fields.toggleClass('kp_settings__credentials_field_hidden');

			// Toggle the icon
			$this.find('span').toggleClass(kp_admin.openedIcon).toggleClass(kp_admin.closedIcon);;
		}
	}

	kp_admin.init();
});
