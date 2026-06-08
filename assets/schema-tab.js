/**
 * Schema settings tab interactions.
 *
 * Loaded by Loomi_Settings_Page::enqueue_assets() when on the plugin settings
 * page. Reads runtime config from `LoomiSchemaTab` global injected via
 * wp_localize_script (nonce, ajaxUrl, optionPrefix, i18n strings).
 *
 * Responsibilities:
 *  - JSON-LD preview button (AJAX POST to admin-ajax.php)
 *  - openingHours repeater (add/remove rows, reindex names)
 *  - Geo lat/lon comma-to-dot normalization (pt-PT locale UX)
 *  - sameAs textarea URL validation (client-side hint)
 *  - Country select "Other" toggle
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

	function config() {
		return ( typeof window.LoomiSchemaTab !== 'undefined' ) ? window.LoomiSchemaTab : {};
	}

	function initPreview() {
		var btn = document.getElementById( 'loomi-schema-preview-btn' );
		var out = document.getElementById( 'loomi-schema-preview-output' );
		if ( ! btn || ! out ) {
			return;
		}
		var cfg = config();
		btn.addEventListener( 'click', function () {
			out.style.display = 'block';
			out.textContent   = cfg.i18n && cfg.i18n.loading ? cfg.i18n.loading : 'Loading...';
			runPreview( btn, out, cfg );
		} );
	}

	function runPreview( btn, out, cfg ) {
		var form = btn.closest( 'form' );
		if ( ! form ) {
			out.textContent = cfg.i18n && cfg.i18n.formMissing ? cfg.i18n.formMissing : 'Error: form not found.';
			return;
		}
		var fd = buildPreviewFormData( form, cfg );
		fetch( cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) { renderPreviewResult( out, data ); } )
			.catch( function ( e ) { out.textContent = 'Erro: ' + e.message; } );
	}

	function buildPreviewFormData( form, cfg ) {
		var fd = new FormData();
		fd.append( 'action', 'loomi_schema_preview' );
		fd.append( '_wpnonce', cfg.nonce );

		form.querySelectorAll( '[name^="' + cfg.optionPrefix + '"]' ).forEach( function ( el ) {
			if ( el.disabled ) { return; }
			if ( ( el.type === 'checkbox' || el.type === 'radio' ) && ! el.checked ) { return; }
			var translated = el.name.replace( cfg.optionPrefix, 'loomi_schema_global' );
			fd.append( translated, el.value );
		} );
		return fd;
	}

	function renderPreviewResult( out, data ) {
		if ( data && data.success ) {
			out.textContent = JSON.stringify( data.data.jsonld, null, 2 );
			return;
		}
		var msg = ( data && data.data && data.data.message ) ? data.data.message : 'desconhecido';
		out.textContent = 'Erro: ' + msg;
	}

	function initHoursRepeater() {
		var container = document.querySelector( '.loomi-schema-hours' );
		if ( ! container ) { return; }
		var template = document.getElementById( 'loomi-schema-hours-template' );
		var addBtn   = document.querySelector( '.loomi-schema-hours__add' );
		if ( ! template || ! addBtn ) { return; }

		addBtn.addEventListener( 'click', function () { onAddHoursRow( container, template ); } );
		container.addEventListener( 'click', function ( e ) { onClickInsideHours( e, container ); } );
		updateHoursRemoveButtons( container );
	}

	function onAddHoursRow( container, template ) {
		var clone = template.content.cloneNode( true );
		var desc  = container.querySelector( 'p.description' );
		if ( desc ) {
			container.insertBefore( clone, desc );
		} else {
			container.appendChild( clone );
		}
		reindexHoursRows( container );
		updateHoursRemoveButtons( container );
	}

	function onClickInsideHours( e, container ) {
		var removeBtn = e.target.closest( '.loomi-schema-hours__remove' );
		if ( ! removeBtn ) { return; }
		var row = removeBtn.closest( '.loomi-schema-hours__row' );
		if ( ! row ) { return; }
		var rows = container.querySelectorAll( '.loomi-schema-hours__row' );
		if ( rows.length <= 1 ) { return; }
		row.remove();
		reindexHoursRows( container );
		updateHoursRemoveButtons( container );
	}

	function reindexHoursRows( container ) {
		var rows = container.querySelectorAll( '.loomi-schema-hours__row' );
		rows.forEach( function ( row, idx ) {
			row.setAttribute( 'data-row-index', idx );
			row.querySelectorAll( '[name*="[openingHours]["]' ).forEach( function ( input ) {
				input.name = input.name
					.replace( /\[openingHours\]\[\d+\]/, '[openingHours][' + idx + ']' )
					.replace( /\[openingHours\]\[__INDEX__\]/, '[openingHours][' + idx + ']' );
			} );
		} );
	}

	function updateHoursRemoveButtons( container ) {
		var rows = container.querySelectorAll( '.loomi-schema-hours__row' );
		rows.forEach( function ( row ) {
			var btn = row.querySelector( '.loomi-schema-hours__remove' );
			if ( btn ) {
				btn.style.display = rows.length > 1 ? '' : 'none';
			}
		} );
	}

	function initGeoCommaToDot() {
		document.querySelectorAll( 'input[name*="[geo][latitude]"], input[name*="[geo][longitude]"]' )
			.forEach( function ( el ) {
				el.addEventListener( 'input', function ( e ) {
					if ( e.target.value.indexOf( ',' ) !== -1 ) {
						e.target.value = e.target.value.replace( ',', '.' );
					}
				} );
			} );
	}

	function initSameAsValidation() {
		var ta      = document.querySelector( 'textarea[name*="[sameAs]"]' );
		var errList = document.querySelector( '.loomi-sameas-errors' );
		if ( ! ta || ! errList ) { return; }
		var validate = function () { validateSameAs( ta, errList ); };
		ta.addEventListener( 'blur', validate );
		if ( ta.value.trim() !== '' ) {
			validate();
		}
	}

	function validateSameAs( ta, errList ) {
		var errors = [];
		ta.value.split( '\n' ).forEach( function ( line, idx ) {
			var trimmed = line.trim();
			if ( trimmed === '' ) { return; }
			if ( ! /^https?:\/\/[^\s]+$/i.test( trimmed ) ) {
				errors.push( 'Linha ' + ( idx + 1 ) + ': URL inválida (' + trimmed.substring( 0, 40 ) + ')' );
			}
		} );

		if ( errors.length === 0 ) {
			errList.style.display = 'none';
			errList.innerHTML = '';
			return;
		}
		errList.innerHTML = errors.map( function ( e ) {
			return '<li>' + e.replace( /</g, '&lt;' ).replace( />/g, '&gt;' ) + '</li>';
		} ).join( '' );
		errList.style.display = 'block';
	}

	function initCountryOtherToggle() {
		var sel   = document.getElementById( 'loomi-schema-country' );
		var other = document.getElementById( 'loomi-schema-country-other' );
		if ( ! sel || ! other ) { return; }
		var sync = function () {
			other.style.display = ( sel.value === 'other' ) ? 'inline-block' : 'none';
		};
		sel.addEventListener( 'change', sync );
	}

	function parseMapsUrl( url ) {
		if ( typeof url !== 'string' || url === '' ) { return null; }
		var m = url.match( /@(-?\d+\.\d+),(-?\d+\.\d+)/ );
		if ( m ) { return { lat: m[ 1 ], lng: m[ 2 ] }; }
		m = url.match( /!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/ );
		if ( m ) { return { lat: m[ 1 ], lng: m[ 2 ] }; }
		return null;
	}

	function setFeedback( el, message, isError ) {
		if ( ! el ) { return; }
		el.textContent = message;
		el.className = 'loomi-extract-feedback' + ( isError ? ' is-error' : ' is-success' );
	}

	function initMapsCoordsExtractor() {
		var input    = document.getElementById( 'loomi-schema-maps-url' );
		var btn      = document.getElementById( 'loomi-schema-extract-coords' );
		var feedback = document.querySelector( '.loomi-extract-feedback' );
		if ( ! input || ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var parsed = parseMapsUrl( input.value.trim() );
			if ( ! parsed ) {
				setFeedback( feedback, 'Não foi possível extrair coordenadas deste URL. Cole o link completo do Google Maps (com @lat,lng ou !3d!4d).', true );
				return;
			}
			var latEl = document.querySelector( 'input[name$="[geo][latitude]"]' );
			var lngEl = document.querySelector( 'input[name$="[geo][longitude]"]' );
			if ( ! latEl || ! lngEl ) {
				setFeedback( feedback, 'Campos de latitude/longitude não encontrados na página.', true );
				return;
			}
			latEl.value = parsed.lat;
			lngEl.value = parsed.lng;
			setFeedback( feedback, 'Coordenadas extraídas: ' + parsed.lat + ', ' + parsed.lng, false );
		} );
	}

	function initPriceRangePicker() {
		var buttons = document.querySelectorAll( '.loomi-pricerange-btn[data-price]' );
		if ( ! buttons.length ) { return; }
		var input = document.querySelector( 'input[name$="[priceRange]"]' );
		if ( ! input ) { return; }
		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				input.value = btn.getAttribute( 'data-price' ) || '';
			} );
		} );
	}

	ready( function () {
		initPreview();
		initHoursRepeater();
		initGeoCommaToDot();
		initSameAsValidation();
		initCountryOtherToggle();
		initMapsCoordsExtractor();
		initPriceRangePicker();
	} );
}() );
