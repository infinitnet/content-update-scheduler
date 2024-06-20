var CUSScheduleUpdate = CUSScheduleUpdate || {};

jQuery( document ).ready( function( $ ) {
	var options = {
		dayNamesMin: CUSScheduleUpdate.datepicker.daynames, // Infused by wp_localize script
		monthNames: CUSScheduleUpdate.datepicker.monthnames, // Infused by wp_localize script
		dateFormat: CUSScheduleUpdate.datepicker.dateformat, // Infused by wp_localize script
		minDate: new Date(),
		showOtherMonths: true,
		firstDay: 1,
		altField: '#' + CUSScheduleUpdate.datepicker.elementid,
		altFormat: 'dd.mm.yy'
	};

	$( '#' + CUSScheduleUpdate.datepicker.displayid ).datepicker( options );

	$( '#publish' ).val( CUSScheduleUpdate.text.save );

	$( '#' + CUSScheduleUpdate.datepicker.elementid ).on( 'change', function( evt ) {
		CUSScheduleUpdate.checkTime();
	} );

	$( '#cus_sc_publish_pubdate_time' ).on( 'change', function( evt ) {
		CUSScheduleUpdate.checkTime();
	} );

	$( 'select[name=cus_sc_publish_pubdate_time_mins]' ).on( 'change', function( evt ) {
		CUSScheduleUpdate.checkTime();
	} );
} );

CUSScheduleUpdate.checkTime = function() {
	var $ = jQuery;

	// WordPress timezone offset is already handled server-side
	var offsetMins = 0;

	var now = new Date();
	
	var dateString = $( '#' + CUSScheduleUpdate.datepicker.elementid ).val();
	var timeHour = $( '#cus_sc_publish_pubdate_time' ).val();
	var timeMin = $( 'select[name=cus_sc_publish_pubdate_time_mins]' ).val();
	
    var dateParts = dateString.split('.');
    var selectedDateTime = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeHour, timeMin);

    // Adjust for WordPress timezone
    selectedDateTime.setMinutes(selectedDateTime.getMinutes() - offsetMins);
    now.setMinutes(now.getMinutes() - offsetMins);

    if (now > selectedDateTime) {
        $('#pastmsg').show();
    } else {
        $('#pastmsg').hide();
    }
};
