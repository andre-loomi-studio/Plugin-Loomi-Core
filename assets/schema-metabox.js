/**
 * Schema metabox interactions: section visibility toggle + areaServed/FAQ repeaters.
 *
 * Loaded by Loomi_Schema_Metabox::enqueue_assets() on post/page edit screens.
 * No PHP interpolation — i18n strings come from server-rendered DOM (button text)
 * or via LoomiSchemaMetabox global if injected via wp_localize_script.
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

	function initSectionToggle( root ) {
		var select   = root.querySelector( '#loomi_schema_type' );
		var sections = root.querySelectorAll( '[data-schema-section]' );
		if ( ! select ) {
			return;
		}

		function applyVisibility( value ) {
			sections.forEach( function ( section ) {
				if ( section.getAttribute( 'data-schema-section' ) === value ) {
					section.removeAttribute( 'hidden' );
				} else {
					section.setAttribute( 'hidden', '' );
				}
			} );
		}

		select.addEventListener( 'change', function () {
			applyVisibility( select.value );
		} );
		applyVisibility( select.value );
	}

	function initAreaRepeater( root ) {
		var repeater = root.querySelector( '[data-loomi-area-repeater]' );
		var addBtn   = root.querySelector( '[data-loomi-area-add]' );
		var template = root.querySelector( '#loomi-area-template' );
		if ( ! repeater || ! addBtn || ! template ) {
			return;
		}

		addBtn.addEventListener( 'click', function () {
			var clone = template.content.firstElementChild.cloneNode( true );
			repeater.appendChild( clone );
		} );

		repeater.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-loomi-area-remove]' );
			if ( ! btn ) { return; }
			var row = btn.closest( '.loomi-area-row' );
			if ( ! row ) { return; }
			removeOrClearAreaRow( repeater, row );
		} );
	}

	function removeOrClearAreaRow( repeater, row ) {
		var rows = repeater.querySelectorAll( '.loomi-area-row' );
		if ( rows.length <= 1 ) {
			var input = row.querySelector( 'input[name="loomi_schema[areaServed][]"]' );
			if ( input ) { input.value = ''; }
			return;
		}
		row.parentNode.removeChild( row );
	}

	function initFaqRepeater( root ) {
		var repeater = root.querySelector( '[data-loomi-faq-repeater]' );
		var addBtn   = root.querySelector( '[data-loomi-faq-add]' );
		var template = root.querySelector( '#loomi-faq-template' );
		if ( ! repeater || ! addBtn || ! template ) {
			return;
		}

		function reindex() {
			var rows = repeater.querySelectorAll( '.loomi-faq-row' );
			rows.forEach( function ( row, idx ) {
				var qField = row.querySelector( '[data-loomi-faq-field="question"]' );
				var aField = row.querySelector( '[data-loomi-faq-field="answer"]' );
				if ( qField ) { qField.setAttribute( 'name', 'loomi_schema[faq][' + idx + '][question]' ); }
				if ( aField ) { aField.setAttribute( 'name', 'loomi_schema[faq][' + idx + '][answer]' ); }
			} );
		}

		addBtn.addEventListener( 'click', function () {
			var clone = template.content.firstElementChild.cloneNode( true );
			repeater.appendChild( clone );
			reindex();
		} );

		repeater.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-loomi-faq-remove]' );
			if ( ! btn ) { return; }
			var row = btn.closest( '.loomi-faq-row' );
			if ( ! row ) { return; }
			removeOrClearFaqRow( repeater, row, reindex );
		} );
	}

	function removeOrClearFaqRow( repeater, row, reindex ) {
		var rows = repeater.querySelectorAll( '.loomi-faq-row' );
		if ( rows.length <= 1 ) {
			var q = row.querySelector( '[data-loomi-faq-field="question"]' );
			var a = row.querySelector( '[data-loomi-faq-field="answer"]' );
			if ( q ) { q.value = ''; }
			if ( a ) { a.value = ''; }
			return;
		}
		row.parentNode.removeChild( row );
		reindex();
	}

	function config() {
		return ( typeof window.LoomiSchemaMetabox !== 'undefined' ) ? window.LoomiSchemaMetabox : {};
	}

	function initPostPreviewButton( root ) {
		var btn = root.querySelector( '#loomi-schema-post-preview-btn' );
		var out = root.querySelector( '#loomi-schema-post-preview-output' );
		if ( ! btn || ! out ) {
			return;
		}
		var cfg = config();
		btn.addEventListener( 'click', function () {
			out.hidden = false;
			out.textContent = cfg.i18n && cfg.i18n.loading ? cfg.i18n.loading : 'Loading...';
			runPostPreview( root, out, cfg );
		} );
	}

	function runPostPreview( root, out, cfg ) {
		var fd = collectMetaboxFormData( root, cfg );
		fetch( cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) { renderPostPreviewResult( out, data ); } )
			.catch( function ( e ) { out.textContent = 'Erro: ' + e.message; } );
	}

	function collectMetaboxFormData( root, cfg ) {
		var fd = new FormData();
		fd.append( 'action', 'loomi_schema_post_preview' );
		fd.append( '_wpnonce', cfg.previewNonce || '' );
		fd.append( 'post_id', String( cfg.postId || 0 ) );

		root.querySelectorAll( '[name="loomi_schema_type"], [name^="loomi_schema["]' ).forEach( function ( el ) {
			if ( el.disabled ) { return; }
			if ( ( el.type === 'checkbox' || el.type === 'radio' ) && ! el.checked ) { return; }
			fd.append( el.name, el.value );
		} );
		return fd;
	}

	function renderPostPreviewResult( out, data ) {
		if ( data && data.success && data.data && typeof data.data.jsonld !== 'undefined' ) {
			out.textContent = JSON.stringify( data.data.jsonld, null, 2 );
			return;
		}
		var msg = ( data && data.data && data.data.message ) ? data.data.message : 'erro desconhecido';
		out.textContent = 'Erro: ' + msg;
	}

	function initCustomJsonGuard( root ) {
		var textarea = root.querySelector( '#loomi_schema_custom_json' );
		var error    = root.querySelector( '.loomi-schema-custom-json-error' );
		if ( ! textarea || ! error ) {
			return;
		}
		var check = function () {
			var value = ( textarea.value || '' ).trim().toLowerCase();
			if ( value.length > 0 && value.indexOf( '<script' ) === 0 ) {
				error.hidden = false;
			} else {
				error.hidden = true;
			}
		};
		textarea.addEventListener( 'input', check );
		textarea.addEventListener( 'blur', check );
	}

	ready( function () {
		var root = document.querySelector( '.loomi-schema-metabox' );
		if ( ! root ) { return; }
		initSectionToggle( root );
		initAreaRepeater( root );
		initFaqRepeater( root );
		initCustomJsonGuard( root );
		initPostPreviewButton( root );
	} );
}() );
