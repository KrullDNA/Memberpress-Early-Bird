/* global jQuery */
( function ( $ ) {
	'use strict';

	$( function () {
		var $list = $( '.kdna-eb-rows' );
		var $template = $( '#kdna-eb-row-template' );

		// Add row button. Uses an __INDEX__ placeholder so PHP indexes stay
		// stable in the page source and JS just stamps the next number on.
		$( document ).on( 'click', '.kdna-eb-add-row', function ( e ) {
			e.preventDefault();
			if ( ! $template.length ) {
				return;
			}
			var next = parseInt( $list.attr( 'data-next-index' ), 10 );
			if ( isNaN( next ) ) {
				next = $list.find( '.kdna-eb-row' ).length;
			}
			var html = $template.html().replace( /__INDEX__/g, String( next ) );
			$list.append( html );
			$list.attr( 'data-next-index', String( next + 1 ) );
		} );

		// Remove row.
		$( document ).on( 'click', '.kdna-eb-remove-row', function ( e ) {
			e.preventDefault();
			var $row = $( this ).closest( '.kdna-eb-row' );
			// Always leave at least one row visible so the UI stays usable.
			if ( $list.find( '.kdna-eb-row' ).length <= 1 ) {
				$row.find( 'input, select' ).val( '' );
				$row.find( 'select' ).prop( 'selectedIndex', 0 );
				return;
			}
			$row.remove();
		} );

		// Time limit toggle. Shows only the relevant fields for the chosen
		// option and hides the others. Defaults to whichever radio is checked
		// on first load.
		function applyTimeLimitToggle() {
			var value = $( 'input[name="kdna_early_bird_time_limit_type"]:checked' ).val() || 'none';
			$( '.kdna-eb-time-limit-fields' ).hide();
			if ( 'none' !== value ) {
				$( '.kdna-eb-time-limit-' + value ).show();
			}
		}
		$( document ).on( 'change', '.kdna-eb-time-limit-toggle', applyTimeLimitToggle );
		applyTimeLimitToggle();
	} );
} )( jQuery );
