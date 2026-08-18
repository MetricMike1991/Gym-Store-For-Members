(function ($) {
	'use strict';

	$('#gsfm-scrape').on('click', function () {
		var $btn = $(this);
		var $status = $('#gsfm-scrape-status');
		$btn.prop('disabled', true);
		$status.text('Scraping…');

		$.post(GSFM_ADMIN.ajax, {
			action: 'gsfm_scrape',
			nonce: GSFM_ADMIN.nonce
		}).done(function (res) {
			if (res && res.success) {
				$status.css('color', '#1a7f37').text(
					'Done — ' + res.data.seen + ' found, ' + res.data.new + ' new, ' + res.data.updated + ' updated.'
				);
				setTimeout(function () { location.reload(); }, 1200);
			} else {
				$status.css('color', '#b32d2e').text((res && res.data && res.data.message) || 'Scrape failed.');
			}
		}).fail(function () {
			$status.css('color', '#b32d2e').text('Request failed.');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});
})(jQuery);
