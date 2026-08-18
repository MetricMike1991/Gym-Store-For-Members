(function ($) {
	'use strict';

	var $btn, $status, $progress, $fill, $text;

	function ajax(action) {
		return $.post(GSFM_ADMIN.ajax, { action: action, nonce: GSFM_ADMIN.nonce });
	}

	function setError(msg) {
		$status.css('color', '#b32d2e').text(msg || 'Something went wrong.');
		$btn.prop('disabled', false);
	}

	function render(d) {
		$progress.show();
		if (d.phase === 'discover') {
			$fill.css('width', '8%');
			$text.text('Discovering products… found ' + (d.discovered || 0));
			return;
		}
		var total = d.total || 0;
		var pct = total > 0 ? Math.round((d.processed / total) * 100) : 0;
		$fill.css('width', pct + '%');
		$text.text(
			d.processed + ' / ' + total + ' products — ' +
			d.new + ' new, ' + d.updated + ' updated, ' + d.skipped + ' skipped'
		);
	}

	function finish(d) {
		$progress.show();
		$fill.css('width', '100%');
		$status.css('color', '#1a7f37').text(
			'Done — ' + (d.new || 0) + ' new, ' + (d.updated || 0) + ' updated, ' + (d.skipped || 0) + ' skipped.'
		);
		if (d.processed || d.new || d.updated) {
			$text.text('Complete. Reloading…');
			setTimeout(function () { location.reload(); }, 1400);
		} else {
			$text.text('Complete.');
			$btn.prop('disabled', false);
		}
	}

	function step() {
		ajax('gsfm_scrape_step').done(function (res) {
			if (!res || !res.success) {
				setError(res && res.data && res.data.message);
				return;
			}
			if (res.data.status === 'done') {
				finish(res.data);
			} else {
				render(res.data);
				step();
			}
		}).fail(function () {
			setError('Network error during scrape. Reload and try again.');
		});
	}

	function start() {
		$btn.prop('disabled', true);
		$status.css('color', '').text('Starting…');
		$progress.show();
		$fill.css('width', '4%');
		$text.text('Starting…');

		ajax('gsfm_scrape_start').done(function (res) {
			if (!res || !res.success) {
				setError(res && res.data && res.data.message);
				return;
			}
			if (res.data.status === 'done') {
				finish(res.data);
			} else {
				render(res.data);
				step();
			}
		}).fail(function () {
			setError('Could not start scrape.');
		});
	}

	$(function () {
		$btn = $('#gsfm-scrape');
		$status = $('#gsfm-scrape-status');
		$progress = $('#gsfm-progress');
		$fill = $('#gsfm-bar-fill');
		$text = $('#gsfm-progress-text');

		if ($btn.length) {
			$btn.on('click', start);

			// Resume a job already running (e.g. after a page reload).
			ajax('gsfm_scrape_status').done(function (res) {
				if (res && res.success && res.data.status === 'running') {
					$btn.prop('disabled', true);
					render(res.data);
					step();
				}
			});
		}

		// Test Connection button on Settings page.
		$('#gsfm-test-btn').on('click', function () {
			var $out = $('#gsfm-test-result');
			$out.show().css('color', '#555').text('Fetching…');
			$.post(GSFM_ADMIN.ajax, {
				action: 'gsfm_test_connection',
				nonce: GSFM_ADMIN.nonce
			}).done(function (res) {
				if (!res || !res.success) {
					$out.css('color', '#b32d2e').text((res && res.data && res.data.message) || 'Test failed.');
					return;
				}
				var d = res.data;
				var lines = [
					'URL:           ' + d.url,
					'HTTP status:   ' + d.http_status,
					'Page title:    ' + d.page_title,
					'Cookie set:    ' + (d.cookie_set ? 'Yes' : 'No — paste session cookie first'),
					'Login wall:    ' + (d.login_wall ? '⚠ YES — cookie not accepted or expired' : 'No ✓'),
					'Links found:   ' + d.total_links,
					'Product links: ' + d.product_links + (d.product_links === 0 ? '  ← check product link pattern' : ' ✓'),
				];
				if (d.sample_products && d.sample_products.length) {
					lines.push('');
					lines.push('Sample product URLs:');
					d.sample_products.forEach(function (u) { lines.push('  ' + u); });
				}
				var ok = !d.login_wall && d.product_links > 0;
				$out.css('color', ok ? '#1a7f37' : '#7a5c00').html(lines.join('<br>'));
			}).fail(function () {
				$out.css('color', '#b32d2e').text('Network error.');
			});
		});

		// Live margin recalculation on the products table.
		function recalcMargin($row) {
			var cost = parseFloat($row.data('cost')) || 0;
			var rrp = parseFloat($row.find('.gsfm-rrp').val()) || 0;
			var sale = parseFloat($row.find('.gsfm-sale').val()) || 0;
			var vat = parseFloat($row.find('.gsfm-vat').val()) || 0;
			var eff = (sale > 0 && sale < rrp) ? sale : rrp;
			var vatAmount = vat > 0 ? eff * (vat / (100 + vat)) : 0;
			var net = eff - vatAmount;
			var profit = net - cost;
			var margin = eff > 0 ? Math.round((profit / eff) * 100) : 0;

			$row.find('.gsfm-profit').text('€' + profit.toFixed(2));
			var $cell = $row.find('.gsfm-margin');
			$cell.text(margin + '%');
			$cell.css('color', profit < 0 ? '#b32d2e' : (margin < 15 ? '#7a5c00' : '#1a7f37'));
			$row.find('.gsfm-profit').css('color', profit < 0 ? '#b32d2e' : '#1a7f37');
		}
		$(document).on('input', '.gsfm-rrp, .gsfm-sale, .gsfm-vat', function () {
			recalcMargin($(this).closest('.gsfm-prow'));
		});

		// AI RRP lookup per product.
		$(document).on('click', '.gsfm-rrp-lookup', function () {
			var $btn = $(this);
			var $row = $btn.closest('.gsfm-prow');
			var original = $btn.text();
			$btn.prop('disabled', true).text('…');
			$.post(GSFM_ADMIN.ajax, {
				action: 'gsfm_lookup_rrp',
				nonce: GSFM_ADMIN.nonce,
				product_id: $btn.data('product')
			}).done(function (res) {
				if (res && res.success) {
					$row.find('.gsfm-rrp').val(res.data.rrp);
					if (res.data.vat != null) {
						$row.find('.gsfm-vat').val(res.data.vat);
					}
					recalcMargin($row);
				} else {
					window.alert((res && res.data && res.data.message) || 'Lookup failed.');
				}
			}).fail(function () {
				window.alert('Network error during lookup.');
			}).always(function () {
				$btn.prop('disabled', false).text(original);
			});
		});

		// Bulk RRP lookup: fetch pending IDs, then process in small batches.
		$('#gsfm-bulk-rrp').on('click', function () {
			var $btn = $(this);
			var $st = $('#gsfm-bulk-rrp-status');
			$btn.prop('disabled', true);
			$st.css('color', '#555').text('Finding products…');

			$.post(GSFM_ADMIN.ajax, { action: 'gsfm_rrp_pending', nonce: GSFM_ADMIN.nonce })
				.done(function (res) {
					if (!res || !res.success) {
						$st.css('color', '#b32d2e').text((res && res.data && res.data.message) || 'Failed.');
						$btn.prop('disabled', false);
						return;
					}
					var ids = res.data.ids || [];
					if (!ids.length) {
						$st.css('color', '#1a7f37').text('All products already have an RRP.');
						$btn.prop('disabled', false);
						return;
					}
					runBatches(ids, ids.length, $btn, $st);
				})
				.fail(function () {
					$st.css('color', '#b32d2e').text('Network error.');
					$btn.prop('disabled', false);
				});
		});

		function runBatches(queue, total, $btn, $st) {
			var BATCH = 4;
			var done = total - queue.length;

			if (!queue.length) {
				$st.css('color', '#1a7f37').text('Done — ' + total + ' RRPs looked up. Reloading…');
				setTimeout(function () { location.reload(); }, 1200);
				return;
			}

			var slice = queue.slice(0, BATCH);
			$st.text('Looking up ' + (done + 1) + '–' + (done + slice.length) + ' of ' + total + '…');

			$.post(GSFM_ADMIN.ajax, { action: 'gsfm_rrp_batch', nonce: GSFM_ADMIN.nonce, ids: slice })
				.done(function (res) {
					if (res && res.success && res.data.results) {
						res.data.results.forEach(function (r) {
							if (r.rrp != null) {
								var $row = $('.gsfm-rrp-lookup[data-product="' + r.id + '"]').closest('.gsfm-prow');
								$row.find('.gsfm-rrp').val(r.rrp);
								if (r.vat != null) {
									$row.find('.gsfm-vat').val(r.vat);
								}
								recalcMargin($row);
							}
						});
					}
					runBatches(queue.slice(BATCH), total, $btn, $st);
				})
				.fail(function () {
					$st.css('color', '#b32d2e').text('Network error — stopped. Reload to see progress so far.');
					$btn.prop('disabled', false);
				});
		}
	});
})(jQuery);
