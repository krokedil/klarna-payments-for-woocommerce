jQuery( function($) {
	'use strict';

	$('h3.wc-settings-sub-title').append(' <a href="#">(expand)</a>');

	$('h3.wc-settings-sub-title + table.form-table').hide();

	$('h3.wc-settings-sub-title a').addClass('collapsed');

	$('h3.wc-settings-sub-title a').click(function(e) {
		e.preventDefault();

		if ($(this).hasClass('collapsed')) {
			$(this).parent().next().show();
			$(this).removeClass('collapsed');
			$(this).text('(collapse)');
		} else {
			$(this).parent().next().hide();
			$(this).addClass('collapsed');
			$(this).text('(expand)');
		}
	});
});
