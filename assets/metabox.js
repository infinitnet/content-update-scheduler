(function ($) {
	'use strict';

	function initMetabox(config) {
		if (!config || !config.metaname) {
			return;
		}

		var metaname = config.metaname;
		var wpTimezoneOffset = Number(config.timezoneOffsetHours || 0); // Hours from UTC.
		var wpTimezoneString = String(config.timezoneString || '');

		function fieldId(suffix) {
			return '#' + metaname + '_' + suffix;
		}

		var $monthField = $(fieldId('month'));
		var $dayField = $(fieldId('day'));
		var $yearField = $(fieldId('year'));
		var $timeField = $(fieldId('time'));

		// Defensive: only activate validation if the metabox fields exist.
		if (!$monthField.length || !$dayField.length || !$yearField.length || !$timeField.length) {
			return;
		}

		function checkDate() {
			// Hide all messages first.
			$('#pastmsg, #invalidmsg, #successmsg').hide();

			var month = $monthField.val();
			var day = $dayField.val();
			var year = $yearField.val();
			var time = $timeField.val();

			if (typeof time === 'string') {
				time = time.trim();
			}

			// Validate inputs.
			if (!month || !day || !year || !time) {
				$('#invalidmsg').show();
				return false;
			}

			// Validate time format (HH:mm).
			var timePattern = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/;
			if (!timePattern.test(time)) {
				$('#invalidmsg').show();
				return false;
			}

			// Validate ranges.
			var monthInt = parseInt(month, 10);
			var dayInt = parseInt(day, 10);
			var yearInt = parseInt(year, 10);
			var currentYear = new Date().getFullYear();

			if (monthInt < 1 || monthInt > 12) {
				$('#invalidmsg').show();
				return false;
			}

			if (dayInt < 1 || dayInt > 31) {
				$('#invalidmsg').show();
				return false;
			}

			if (yearInt < currentYear || yearInt > currentYear + 10) {
				$('#invalidmsg').show();
				return false;
			}

			// Check if it's a valid date (catches Feb 30, etc.).
			var testDate = new Date(yearInt, monthInt - 1, dayInt);
			if (
				testDate.getMonth() !== monthInt - 1 ||
				testDate.getDate() !== dayInt ||
				testDate.getFullYear() !== yearInt
			) {
				$('#invalidmsg').show();
				return false;
			}

			// Create the full datetime.
			var timeParts = time.split(':');
			if (timeParts.length !== 2) {
				$('#invalidmsg').show();
				return false;
			}

			// Create dates (JavaScript interprets as browser's local timezone).
			var selectedDate = new Date(
				yearInt,
				monthInt - 1,
				dayInt,
				parseInt(timeParts[0], 10),
				parseInt(timeParts[1], 10)
			);

			var now = new Date();

			if (Number.isNaN(selectedDate.getTime())) {
				$('#invalidmsg').show();
				return false;
			}

			// Don't block saving for past dates: server-side will normalize to +5 minutes.
			if (selectedDate <= now) {
				$('#pastmsg').show();
				return true;
			}

			$('#successmsg').show();
			return true;
		}

		function updateCurrentTime() {
			var now = new Date();
			// Convert UTC time to WordPress timezone.
			var wpTime = new Date(now.getTime() + wpTimezoneOffset * 60 * 60 * 1000);

			var options = {
				year: 'numeric',
				month: 'long',
				day: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
				hour12: false,
				timeZone: 'UTC', // Display in UTC to avoid browser conversion.
			};

			var timeStr = wpTime.toLocaleString('en-US', options);
			if (wpTimezoneString) {
				timeStr += ' ' + wpTimezoneString;
			}

			$('#current-wordpress-time').text(timeStr);
		}

		$(fieldId('month') + ', ' + fieldId('day') + ', ' + fieldId('year') + ', ' + fieldId('time')).on(
			'change',
			checkDate
		);

		checkDate(); // Initial check.

		// Update immediately and then every minute.
		updateCurrentTime();
		setInterval(updateCurrentTime, 60000);

		// Prevent form submission if date validation fails.
		$('form#post').on('submit', function (e) {
			if (checkDate()) {
				return;
			}

			e.preventDefault();
			var $messages = $('#validation-messages');
			if ($messages.length) {
				$('html, body').animate(
					{
						scrollTop: $messages.offset().top - 100,
					},
					500
				);
			}
		});
	}

	$(function () {
		initMetabox(window.ContentUpdateSchedulerMetabox || null);
	});
})(jQuery);
