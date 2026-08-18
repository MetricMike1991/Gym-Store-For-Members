(function ($) {
	'use strict';

	// Drop countdown banner.
	function initCountdown() {
		var $cd = $('.gsfm-countdown');
		if (!$cd.length) {
			return;
		}
		var deadline = parseInt($cd.data('deadline'), 10);
		if (!deadline) {
			return;
		}

		function tick() {
			var diff = deadline - Date.now();
			if (diff <= 0) {
				$cd.find('.gsfm-cd-open').hide();
				$cd.find('.gsfm-cd-closed').show();
				clearInterval(timer);
				return;
			}
			var s = Math.floor(diff / 1000);
			var d = Math.floor(s / 86400);
			var h = Math.floor((s % 86400) / 3600);
			var m = Math.floor((s % 3600) / 60);
			var sec = s % 60;
			$cd.find('.gsfm-cd-d').text(d);
			$cd.find('.gsfm-cd-h').text(h);
			$cd.find('.gsfm-cd-m').text(m);
			$cd.find('.gsfm-cd-s').text(sec);
		}
		tick();
		var timer = setInterval(tick, 1000);
	}

	$(initCountdown);

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
					showConfirm();
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

	// Confirmation popup shown after a member requests an item.
	function showConfirm() {
		var $modal = $('#gsfm-modal');
		if (!$modal.length) {
			$modal = $(
				'<div id="gsfm-modal" class="gsfm-modal-overlay">' +
				'  <div class="gsfm-modal">' +
				'    <div class="gsfm-modal-check">&#10003;</div>' +
				'    <h3>Added to your next order</h3>' +
				'    <p>You&rsquo;ve asked us to bring this item in for you in the next order. ' +
				'You&rsquo;ll get a text when it arrives &mdash; usually within a few days.</p>' +
				'    <p class="gsfm-modal-note">Changed your mind? Just tap the button again to remove it.</p>' +
				'    <button type="button" class="gsfm-btn gsfm-modal-ok">Got it</button>' +
				'  </div>' +
				'</div>'
			).appendTo('body');
			$modal.on('click', function (e) {
				if (e.target === this || $(e.target).hasClass('gsfm-modal-ok')) {
					$modal.removeClass('is-open');
				}
			});
		}
		// Force reflow so the transition runs.
		$modal[0].offsetHeight;
		$modal.addClass('is-open');
	}
})(jQuery);
