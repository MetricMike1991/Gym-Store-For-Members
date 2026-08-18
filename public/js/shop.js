(function ($) {
	'use strict';

	$(document).on('click', '.gsfm-toggle', function () {
		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}

		var productId = $btn.data('product');
		var currentlyRequested = String($btn.data('requested')) === '1';
		var want = currentlyRequested ? 0 : 1;

		$btn.prop('disabled', true);

		$.post(GSFM.ajax, {
			action: 'gsfm_toggle',
			nonce: GSFM.nonce,
			product_id: productId,
			want: want
		}).done(function (res) {
			if (res && res.success) {
				if (res.data.requested) {
					$btn.addClass('is-requested').data('requested', '1');
				} else {
					$btn.removeClass('is-requested').data('requested', '0');
				}
			} else {
				window.alert((res && res.data && res.data.message) || 'Something went wrong.');
			}
		}).fail(function () {
			window.alert('Request failed. Please try again.');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});
})(jQuery);
