/**
 * Loomi Login Customizer — live preview handler.
 *
 * Listens to Customizer setting changes (bg color, logo dimensions) and rewrites
 * the inline <style id="loomi-login"> block in the iframe in real time. Logo
 * image swaps use transport=refresh so the page reloads to fetch the new URL.
 */
( function ( $, api ) {
	'use strict';
	if ( ! api ) { return; }

	var OPT = 'loomi_studio_setup_settings';

	function setting( key ) {
		return api( OPT + '[' + key + ']' );
	}

	function safeBg( raw ) {
		raw = String( raw || '' );
		return /^#[A-Fa-f0-9]{6}$/.test( raw ) ? raw : '#000000';
	}

	function safeDim( raw, fallback ) {
		var n = parseInt( raw, 10 );
		if ( isNaN( n ) || n < 50 ) { return fallback; }
		if ( n > 600 ) { return 600; }
		return n;
	}

	function readCurrentLogoBg() {
		// Read the background-image already set server-side on first render so width/height
		// updates can preserve it. If logo isn't set, this returns 'none'.
		var el = document.querySelector( '.login h1 a' );
		if ( ! el ) { return ''; }
		var bg = window.getComputedStyle( el ).backgroundImage || '';
		return ( bg && bg !== 'none' ) ? bg : '';
	}

	function rebuildStyle() {
		var enabled = !! setting( 'custom_login_enabled' )();
		var bg      = safeBg( setting( 'custom_login_bg_color' )() );
		var width   = safeDim( setting( 'custom_login_logo_width' )(),  320 );
		var height  = safeDim( setting( 'custom_login_logo_height' )(), 120 );

		var style = document.getElementById( 'loomi-login' );
		if ( ! style ) {
			style = document.createElement( 'style' );
			style.id = 'loomi-login';
			document.head.appendChild( style );
		}

		if ( ! enabled ) {
			style.textContent = '';
			return;
		}

		var css = 'body.login{background:' + bg + ' !important;}'
		        + '#nav a,#backtoblog a,.privacy-policy-link{color:#fff !important;}'
		        + '.login #login_error,.login .message,.login .success{color:#1d2327;}';

		var logoBg = readCurrentLogoBg();
		if ( logoBg ) {
			css += '.login h1 a{'
			    +  'background-image:' + logoBg + ' !important;'
			    +  'width:' + width + 'px !important;'
			    +  'height:' + height + 'px !important;'
			    +  'margin-bottom:60px !important;'
			    +  'background-size:contain !important;'
			    +  'background-position:center center !important;'
			    +  'background-repeat:no-repeat !important;'
			    +  '}';
		}

		style.textContent = css;
	}

	[
		'custom_login_enabled',
		'custom_login_bg_color',
		'custom_login_logo_width',
		'custom_login_logo_height'
	].forEach( function ( key ) {
		api( OPT + '[' + key + ']', function ( s ) {
			s.bind( rebuildStyle );
		} );
	} );

	// Initial sync once preview is ready.
	if ( api.preview ) {
		api.preview.bind( 'active', rebuildStyle );
	} else {
		rebuildStyle();
	}
}( window.jQuery, window.wp && window.wp.customize ) );
