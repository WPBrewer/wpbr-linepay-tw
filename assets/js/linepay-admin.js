jQuery(function ($) {
	'use strict';

	/**
	 * Object to handle LINE Pay admin functions.
	 */
	var linepay_admin = {
		/**
		 * Initialize.
		 */
		init: function () {
			console.log('line pay admin script init');
			$(document.body).on('change', '#linepay_tw_sandboxmode_enabled', function () {
				var sandbox_channel_id = $('#linepay_tw_sandbox_channel_id').parents('tr').eq(0),
					sandbox_channel_secret = $('#linepay_tw_sandbox_channel_secret').parents('tr').eq(0),

					channel_id = $('#linepay_tw_channel_id').parents('tr').eq(0),
					channel_secret = $('#linepay_tw_channel_secret').parents('tr').eq(0);


				if ($(this).is(':checked')) {
					sandbox_channel_id.show();
					sandbox_channel_secret.show();

					channel_id.hide();
					channel_secret.hide();

				} else {
					sandbox_channel_id.hide();
					sandbox_channel_secret.hide();

					channel_id.show();
					channel_secret.show();

				}
			});

			$('#linepay_tw_sandboxmode_enabled').trigger('change');
		}
	};

	linepay_admin.init();
});
