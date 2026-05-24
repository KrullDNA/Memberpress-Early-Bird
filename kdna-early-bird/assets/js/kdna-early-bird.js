/* KDNA Early Bird Pricing, live countdown.
   Looks for .kdna-early-bird-countdown--live elements with a data-end
   ISO timestamp and ticks each one once a second. Static days mode
   needs no JS. */

( function () {
	'use strict';

	function pad2( n ) {
		n = Math.max( 0, parseInt( n, 10 ) || 0 );
		return ( n < 10 ? '0' : '' ) + n;
	}

	function setText( root, selector, value ) {
		var node = root.querySelector( selector );
		if ( node ) {
			node.textContent = value;
		}
	}

	function update( el ) {
		var end = el.getAttribute( 'data-end' );
		if ( ! end ) {
			return false;
		}
		var endTime = Date.parse( end );
		if ( isNaN( endTime ) ) {
			return false;
		}
		var diff = endTime - Date.now();
		if ( diff < 0 ) {
			diff = 0;
		}

		var days    = Math.floor( diff / 86400000 );
		var hours   = Math.floor( ( diff % 86400000 ) / 3600000 );
		var minutes = Math.floor( ( diff % 3600000 ) / 60000 );
		var seconds = Math.floor( ( diff % 60000 ) / 1000 );

		setText( el, '.kdna-early-bird-countdown-days .kdna-early-bird-countdown-value', pad2( days ) );
		setText( el, '.kdna-early-bird-countdown-hours .kdna-early-bird-countdown-value', pad2( hours ) );
		setText( el, '.kdna-early-bird-countdown-minutes .kdna-early-bird-countdown-value', pad2( minutes ) );
		setText( el, '.kdna-early-bird-countdown-seconds .kdna-early-bird-countdown-value', pad2( seconds ) );

		return diff > 0;
	}

	function init() {
		var nodes = document.querySelectorAll( '.kdna-early-bird-countdown--live' );
		if ( ! nodes.length ) {
			return;
		}
		Array.prototype.forEach.call( nodes, function ( el ) {
			if ( el.getAttribute( 'data-kdna-eb-initialised' ) === '1' ) {
				return;
			}
			el.setAttribute( 'data-kdna-eb-initialised', '1' );

			var stillRunning = update( el );
			if ( ! stillRunning ) {
				return;
			}
			var timer = setInterval( function () {
				var running = update( el );
				if ( ! running ) {
					clearInterval( timer );
				}
			}, 1000 );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Re-run after Elementor's frontend renders a widget in the editor.
	if ( typeof window !== 'undefined' && window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction( 'frontend/element_ready/kdna-early-bird-pricing.default', function () {
					init();
				} );
			}
		} );
	}
} )();
