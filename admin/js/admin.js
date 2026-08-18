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

		if (!$btn.length) {
			return;
		}

		$btn.on('click', start);

		// Resume a job already running (e.g. after a page reload).
		ajax('gsfm_scrape_status').done(function (res) {
			if (res && res.success && res.data.status === 'running') {
				$btn.prop('disabled', true);
				render(res.data);
				step();
			}
		});
	});
})(jQuery);
