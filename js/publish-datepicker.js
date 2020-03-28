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
	var dt, datestring, currentGmt;

	var pattern = /(\d{2})\.(\d{2})\.(\d{4}) (\d{2})\:(\d{2})/;
	var now = new Date();
	var st = $( '#' + CUSScheduleUpdate.datepicker.elementid ).val();
	var time = $( '#cus_sc_publish_pubdate_time' ).find( ':selected' ).val() + ':' + $( 'select[name=cus_sc_publish_pubdate_time_mins]' ).find( ':selected' ).val();
	st += ' ' + time;

	currentGmt = $( '#cus_used_gmt' ).val();
	datestring = st.replace( pattern, '$3-$2-$1T$4:$5:00' );
	dt = new Date( datestring + currentGmt );

	if ( now.getTime() > dt.getTime() ) {
		$( '#pastmsg' ).show();
	} else {
		$( '#pastmsg' ).hide();
	}
};
