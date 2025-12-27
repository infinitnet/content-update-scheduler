(function ($) {
	'use strict';

	function getConfig() {
		return window.ContentUpdateSchedulerHomepageScheduler || {};
	}

	function scheduleHomepageChange() {
		var formData = {
			action: 'schedule_homepage_change',
			page_id: $('#new_homepage').val(),
			schedule_date: $('#schedule_date').val(),
			schedule_time: $('#schedule_time').val(),
			homepage_nonce: $('[name="homepage_nonce"]').val(),
		};

		$.post(ajaxurl, formData, function (response) {
			if (response && response.success) {
				location.reload();
				return;
			}
			var message = response && response.data ? response.data : 'Unknown error';
			alert('Error: ' + message);
		});
	}

	function cancelHomepageChange($button) {
		var config = getConfig();
		var promptText = config.confirmCancel || 'Are you sure?';

		if (!confirm(promptText)) {
			return;
		}

		var formData = {
			action: 'cancel_homepage_change',
			timestamp: $button.data('timestamp'),
			page_id: $button.data('page-id'),
			homepage_nonce: $('[name="homepage_nonce"]').val(),
		};

		$.post(ajaxurl, formData, function (response) {
			if (response && response.success) {
				location.reload();
				return;
			}
			var message = response && response.data ? response.data : 'Unknown error';
			alert('Error: ' + message);
		});
	}

	$(function () {
		$('#schedule-homepage-form').on('submit', function (e) {
			e.preventDefault();
			scheduleHomepageChange();
		});

		$('.cancel-homepage-change').on('click', function (e) {
			e.preventDefault();
			cancelHomepageChange($(this));
		});
	});
})(jQuery);

