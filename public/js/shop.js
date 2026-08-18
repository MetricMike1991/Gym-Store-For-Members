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

	// Branded login / register panel.
	function initAccess() {
		var $wrap = $('.gsfm-access');
		if (!$wrap.length) {
			return;
		}

		$wrap.on('click', '.gsfm-tab', function () {
			var tab = $(this).data('tab');
			$wrap.find('.gsfm-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$wrap.find('.gsfm-access-form').hide();
			$wrap.find('.gsfm-access-form[data-form="' + tab + '"]').show();
			$wrap.find('.gsfm-access-msg').removeClass('is-error').text('');
		});

		$wrap.on('submit', '.gsfm-access-form', function (e) {
			e.preventDefault();
			var $form = $(this);
			var which = $form.data('form');
			var $msg = $wrap.find('.gsfm-access-msg');
			var $submit = $form.find('button[type="submit"]');
			$msg.removeClass('is-error').text('');
			$submit.prop('disabled', true);

			var data = $form.serializeArray().reduce(function (acc, f) {
				acc[f.name] = f.value;
				return acc;
			}, {});
			data.action = (which === 'login') ? 'gsfm_login' : 'gsfm_register';
			data.nonce = GSFM.nonce;

			$.post(GSFM.ajax, data).done(function (res) {
				if (res && res.success) {
					window.location.href = res.data.redirect;
				} else {
					$msg.addClass('is-error').text((res && res.data && res.data.message) || 'Something went wrong.');
					$submit.prop('disabled', false);
				}
			}).fail(function () {
				$msg.addClass('is-error').text('Network error. Please try again.');
				$submit.prop('disabled', false);
			});
		});
	}
	$(initAccess);

	// Load the member's request panel via AJAX (cache/Elementor-proof).
	function loadMyRequests() {
		var $mount = $('.gsfm-myreq-mount');
		if (!$mount.length) {
			return;
		}
		$.post(GSFM.ajax, { action: 'gsfm_my_requests', nonce: GSFM.nonce }).done(function (res) {
			if (res && res.success) {
				$mount.html(res.data.html || '');
			}
		});
	}
	$(loadMyRequests);

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
					showConfirm($btn.data('mode'));
				} else {
					$btn.removeClass('is-requested').data('requested', '0');
				}
				loadMyRequests();
			} else {
				window.alert((res && res.data && res.data.message) || 'Something went wrong.');
			}
		}).fail(function () {
			window.alert('Request failed. Please try again.');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});

	// Remove an item directly from the "YOUR list" summary.
	$(document).on('click', '.gsfm-myreq-remove', function () {
		var $btn = $(this);
		var $item = $btn.closest('.gsfm-myreq-item');
		var productId = $btn.data('product');
		$btn.prop('disabled', true);

		$.post(GSFM.ajax, {
			action: 'gsfm_toggle',
			nonce: GSFM.nonce,
			product_id: productId,
			want: 0
		}).done(function (res) {
			if (res && res.success) {
				var $wrap = $item.closest('.gsfm-myreq');
				$item.remove();
				// Keep any matching shop button in sync.
				$('.gsfm-toggle[data-product="' + productId + '"]').removeClass('is-requested').data('requested', '0');
				if (!$wrap.find('.gsfm-myreq-item').length) {
					$wrap.find('.gsfm-myreq-list').hide();
					$wrap.find('.gsfm-myreq-empty').show();
				}
			} else {
				window.alert((res && res.data && res.data.message) || 'Could not remove that.');
				$btn.prop('disabled', false);
			}
		}).fail(function () {
			window.alert('Network error. Please try again.');
			$btn.prop('disabled', false);
		});
	});

	// Confirmation popup shown after a member requests an item.
	function showConfirm(mode) {
		var content = (mode === 'vending')
			? {
				title: 'Interest registered',
				body: 'You&rsquo;ve expressed interest in this product. We&rsquo;ll consider adding it to the vending machine in the next order.'
			}
			: {
				title: 'Added to your next order',
				body: 'You&rsquo;ve asked us to bring this item in for you in the next order. You&rsquo;ll get a text when it arrives &mdash; usually within a few days.'
			};

		var $modal = $('#gsfm-modal');
		if (!$modal.length) {
			$modal = $(
				'<div id="gsfm-modal" class="gsfm-modal-overlay">' +
				'  <div class="gsfm-modal">' +
				'    <div class="gsfm-modal-check">&#10003;</div>' +
				'    <h3 class="gsfm-modal-title"></h3>' +
				'    <p class="gsfm-modal-body"></p>' +
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
		$modal.find('.gsfm-modal-title').text(content.title);
		$modal.find('.gsfm-modal-body').html(content.body);
		// Force reflow so the transition runs.
		$modal[0].offsetHeight;
		$modal.addClass('is-open');
	}
})(jQuery);
