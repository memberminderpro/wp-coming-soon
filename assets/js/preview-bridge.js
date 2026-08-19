/**
 * Preview bridge.
 *
 * Loaded only into a preview rendering, never into the page a visitor sees.
 * Accepts CSS custom property updates from the settings screen so that colour
 * changes apply as they are dragged, without re-rendering the document.
 *
 * Anything that changes markup -- text, buttons, logos, aurora blobs -- is not
 * handled here; the settings screen re-posts the form for those.
 */
( function () {
	'use strict';

	/**
	 * Only ever accept custom properties, and only ones that are ours.
	 *
	 * The page this runs in is same-origin with the admin screen, so a message
	 * arriving from anywhere else has no business setting anything on it.
	 *
	 * @param {string} name Property name.
	 * @return {boolean} Whether it may be applied.
	 */
	function allowed( name ) {
		return 'string' === typeof name && /^--mmpcs-[a-z0-9-]+$/.test( name );
	}

	window.addEventListener( 'message', function ( event ) {
		if ( event.origin !== window.location.origin ) {
			return;
		}

		var data = event.data;

		if ( ! data || 'mmpcs-preview-vars' !== data.type || ! data.vars ) {
			return;
		}

		var root = document.documentElement;

		Object.keys( data.vars ).forEach( function ( name ) {
			if ( ! allowed( name ) ) {
				return;
			}

			var value = data.vars[ name ];

			// Colours and lengths only. Anything containing a brace, a
			// semicolon or a url() is refused rather than reasoned about.
			if ( 'string' !== typeof value || /[;{}]|url\(/i.test( value ) ) {
				return;
			}

			root.style.setProperty( name, value );
		} );
	} );

	// Tell the opener we are ready, so it can push the current values into a
	// window that finished loading after the last change was made.
	if ( window.parent !== window || window.opener ) {
		var target = window.opener || window.parent;

		try {
			target.postMessage( { type: 'mmpcs-preview-ready' }, window.location.origin );
		} catch ( e ) {
			// A cross-origin opener is not ours to talk to; nothing to do.
		}
	}
}() );
