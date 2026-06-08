/**
 * Maps settings tab interactions.
 *
 * Loaded by Loomi_Settings_Page::enqueue_assets() when on the plugin settings
 * page and the active tab is 'maps'.
 *
 * Responsibilities:
 *  - Zoom preset buttons → set #loomi-maps-zoom value (13/15/17)
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function initZoomPresets() {
		var input   = document.getElementById( 'loomi-maps-zoom' );
		var buttons = document.querySelectorAll( '.loomi-zoom-preset[data-zoom]' );
		if ( ! input || ! buttons.length ) { return; }
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var z = btn.getAttribute( 'data-zoom' );
				if ( z !== null && z !== '' ) {
					input.value = z;
				}
			} );
		} );
	}

	ready( function () {
		initZoomPresets();
	} );
}() );
