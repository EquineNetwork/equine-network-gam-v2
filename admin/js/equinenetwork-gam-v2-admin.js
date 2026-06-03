(function ($) {
	'use strict';

	var pendingCredentials = null;

	function engamSetStatus(msg, type) {
		var $s = $('#engam-api-status');
		$s.text(msg).removeClass('success error info').addClass(type).show();
	}

	// Handle file selected via input or drop.
	window.engamHandleFile = function (file) {
		if (!file || file.type !== 'application/json' && !file.name.endsWith('.json')) {
			engamSetStatus('Please upload a .json file.', 'error');
			return;
		}

		var reader = new FileReader();
		reader.onload = function (e) {
			try {
				var data = JSON.parse(e.target.result);
				if (!data.client_email || !data.private_key) {
					engamSetStatus('This doesn\'t look like a valid service account key file.', 'error');
					return;
				}
				pendingCredentials = e.target.result;

				// Update upload area to show file is ready.
				$('#engam-upload-label').html(
					'<strong style="font-size:12px;display:block">✅ ' + file.name + '</strong>' +
					'<span style="font-size:11px;color:#555">' + data.client_email + '</span>'
				);
				$('#engam-upload-area').css('border-color', '#050505').css('background', '#f0ffe0');
				$('#engam-save-credentials').prop('disabled', false).css({'pointer-events':'auto','opacity':'1'});
				engamSetStatus('File loaded — click Save Credentials to store it.', 'info');
			} catch (err) {
				engamSetStatus('Could not parse JSON file: ' + err.message, 'error');
			}
		};
		reader.readAsText(file);
	};

	// Handle drag-and-drop.
	window.engamHandleDrop = function (event) {
		event.preventDefault();
		document.getElementById('engam-upload-area').style.borderColor = '#bbb';
		var file = event.dataTransfer.files[0];
		if (file) engamHandleFile(file);
	};

	// Save credentials via AJAX.
	$('#engam-save-credentials').on('click', function () {
		if (!pendingCredentials) {
			engamSetStatus('Please select a JSON file first.', 'error');
			return;
		}
		engamSetStatus('Saving…', 'info');
		$.post(engamV2.ajaxUrl, {
			action:      'engam_v2_save_credentials',
			nonce:       engamV2.nonce,
			credentials: pendingCredentials,
		}, function (res) {
			engamSetStatus(res.message, res.success ? 'success' : 'error');
			if (res.success) {
				pendingCredentials = null;
				$('#engam-save-credentials').prop('disabled', true).css({'pointer-events':'none','opacity':'.4'});
				$('#engam-test-connection').css({'opacity':'1','pointer-events':'auto','border-color':'#111'});
			}
		});
	});

	// Test connection.
	$('#engam-test-connection').on('click', function () {
		engamSetStatus('Testing connection to GAM…', 'info');
		$.post(engamV2.ajaxUrl, {
			action: 'engam_v2_test_connection',
			nonce:  engamV2.nonce,
		}, function (res) {
			engamSetStatus(res.message, res.success ? 'success' : 'error');
		});
	});

	// Refresh cache.
	$('#engam-refresh-cache').on('click', function () {
		engamSetStatus('Refreshing line items from GAM…', 'info');
		$.post(engamV2.ajaxUrl, {
			action: 'engam_v2_refresh_cache',
			nonce:  engamV2.nonce,
		}, function (res) {
			engamSetStatus(res.message, res.success ? 'success' : 'error');
		});
	});

})(jQuery);
