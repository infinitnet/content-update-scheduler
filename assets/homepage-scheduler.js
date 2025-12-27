(function ($) {
	'use strict';

	function getConfig() {
		return window.ContentUpdateSchedulerHomepageScheduler || {};
	}

	function showNotice(type, message) {
		var $container = $('#cus-homepage-notices');
		if (!$container.length) {
			alert(message);
			return;
		}

		var $notice = $('<div/>', {
			class: 'notice notice-' + type + ' is-dismissible',
		});
		var $p = $('<p/>').text(message);
		$notice.append($p);
		$container.empty().append($notice);
	}

	function setLoading($context, isLoading) {
		var $spinner = $context.find('.spinner');
		if ($spinner.length) {
			$spinner.toggleClass('is-active', !!isLoading);
		}

		$context.find('button, input, select').prop('disabled', !!isLoading);
	}

	function scheduleHomepageChange() {
		var config = getConfig();
		if (typeof ajaxurl !== 'string' || !ajaxurl) {
			showNotice('error', config.errorMissingAjaxUrl || 'AJAX endpoint missing.');
			return null;
		}

		var nonce = $('[name="homepage_nonce"]').val();
		if (!nonce) {
			showNotice('error', config.errorMissingNonce || 'Security token missing. Please reload the page.');
			return null;
		}

		var formData = {
			action: 'schedule_homepage_change',
			page_id: $('#new_homepage').val(),
			schedule_date: $('#schedule_date').val(),
			schedule_time: $('#schedule_time').val(),
			homepage_nonce: nonce,
		};

		return $.post(ajaxurl, formData)
			.done(function (response) {
				if (response && response.success) {
					showNotice('success', config.scheduledSuccess || 'Scheduled.');
					location.reload(); // Keep existing behavior: reload to reflect the new schedule.
					return;
				}
				var message = response && response.data ? response.data : config.errorUnknown || 'Unknown error';
				showNotice('error', message);
			})
			.fail(function () {
				showNotice('error', config.errorRequestFailed || 'Request failed.');
			});
	}

	function cancelHomepageChange($button) {
		var config = getConfig();
		var promptText = config.confirmCancel || 'Are you sure?';

		if (typeof ajaxurl !== 'string' || !ajaxurl) {
			showNotice('error', config.errorMissingAjaxUrl || 'AJAX endpoint missing.');
			return null;
		}

		var nonce = $('[name="homepage_nonce"]').val();
		if (!nonce) {
			showNotice('error', config.errorMissingNonce || 'Security token missing. Please reload the page.');
			return null;
		}

		if (!confirm(promptText)) {
			return null;
		}

		var formData = {
			action: 'cancel_homepage_change',
			timestamp: $button.data('timestamp'),
			page_id: $button.data('page-id'),
			homepage_nonce: nonce,
		};

		return $.post(ajaxurl, formData)
			.done(function (response) {
				if (response && response.success) {
					showNotice('success', config.canceledSuccess || 'Canceled.');
					location.reload(); // Keep existing behavior: reload to reflect the new schedule.
					return;
				}
				var message = response && response.data ? response.data : config.errorUnknown || 'Unknown error';
				showNotice('error', message);
			})
			.fail(function () {
				showNotice('error', config.errorRequestFailed || 'Request failed.');
			});
	}

	$(function () {
		$('#schedule-homepage-form').on('submit', function (e) {
			e.preventDefault();
			var $form = $(this);
			setLoading($form, true);
			var req = scheduleHomepageChange();
			if (req && typeof req.always === 'function') {
				req.always(function () {
					setLoading($form, false);
				});
			} else {
				setLoading($form, false);
			}
		});

		$('.cancel-homepage-change').on('click', function (e) {
			e.preventDefault();
			var $button = $(this);
			var $wrap = $button.closest('.wrap');
			setLoading($wrap, true);
			var req = cancelHomepageChange($button);
			if (req && typeof req.always === 'function') {
				req.always(function () {
					setLoading($wrap, false);
				});
			} else {
				setLoading($wrap, false);
			}
		});
	});
})(jQuery);
