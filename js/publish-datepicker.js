var CUSScheduleUpdate = CUSScheduleUpdate || {};

jQuery(document).ready(function($) {
    var options = {
        dayNamesMin: CUSScheduleUpdate.datepicker.daynames,
        monthNames: CUSScheduleUpdate.datepicker.monthnames,
        dateFormat: CUSScheduleUpdate.datepicker.dateformat,
        minDate: new Date(),
        showOtherMonths: true,
        firstDay: 0, // Start week on Sunday
        altField: '#' + CUSScheduleUpdate.datepicker.elementid,
        altFormat: 'dd.mm.yy',
        onSelect: function(dateText, inst) {
            CUSScheduleUpdate.checkTime();
        }
    };

    $('#' + CUSScheduleUpdate.datepicker.displayid).datepicker(options);

    $('#publish').val(CUSScheduleUpdate.text.save);

    $('#' + CUSScheduleUpdate.datepicker.elementid).on('change', CUSScheduleUpdate.checkTime);
    $('#cus_sc_publish_pubdate_time, select[name=cus_sc_publish_pubdate_time_mins]').on('change', CUSScheduleUpdate.checkTime);

    // Initial check
    CUSScheduleUpdate.checkTime();
});

CUSScheduleUpdate.checkTime = function() {
    var $ = jQuery;

    var now = new Date();
    var siteTimezone = CUSScheduleUpdate.siteTimezone;
    var dateString = $('#' + CUSScheduleUpdate.datepicker.elementid).val();
    var timeHour = $('#cus_sc_publish_pubdate_time').val();
    var timeMin = $('select[name=cus_sc_publish_pubdate_time_mins]').val();

    var dateParts = dateString.split('.');
    var selectedDateTime = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeHour, timeMin);

    // Convert the selected date/time to the site's timezone
    var siteTime = new Date(selectedDateTime.toLocaleString('en-US', { timeZone: siteTimezone }));

    if (now > siteTime) {
        $('#pastmsg').show();
    } else {
        $('#pastmsg').hide();
    }
};
