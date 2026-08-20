/**
 * MMP Coming Soon — options screen behaviour.
 *
 * Tabs, repeaters, colour pickers, media picker and range readouts. jQuery is
 * used only because WordPress's colour picker requires it.
 */
( function ( $ ) {
	'use strict';

	var strings = window.mmpcsAdmin || {};

	/**
	 * Panel tabs.
	 */
	function initTabs() {
		var tabs = document.querySelectorAll( '.mmpcs-tab' );

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				var target = tab.dataset.tab;

				document.querySelectorAll( '.mmpcs-tab' ).forEach( function ( t ) {
					t.classList.toggle( 'is-active', t === tab );
				} );

				document.querySelectorAll( '.mmpcs-panel' ).forEach( function ( panel ) {
					panel.classList.toggle( 'is-active', panel.dataset.panel === target );
				} );

				try {
					window.localStorage.setItem( 'mmpcsTab', target );
				} catch ( e ) {
					// Storage unavailable; tab simply will not persist.
				}
			} );
		} );

		var requested = new URLSearchParams( window.location.search ).get( 'mmpcs_tab' );
		var saved;

		if ( requested ) {
			saved = requested;
		} else {
			try {
				saved = window.localStorage.getItem( 'mmpcsTab' );
			} catch ( e ) {
				saved = null;
			}
		}

		if ( saved ) {
			var restore = document.querySelector( '.mmpcs-tab[data-tab="' + saved + '"]' );
			if ( restore ) {
				restore.click();
			}
		}
	}

	/**
	 * Turn a text input into a colour picker.
	 *
	 * @param {Element} input Target input.
	 */
	function initColor( input ) {
		if ( input.dataset.mmpcsColor === 'done' ) {
			return;
		}

		input.dataset.mmpcsColor = 'done';

		$( input ).wpColorPicker( {
			change: function ( event, ui ) {
				// The input's own value lags the picker during a drag, so take
				// the colour from the picker itself.
				input.value = ui.color.toString();
				onColorChanged( input );
			},
			clear: function () {
				onColorChanged( input );
			}
		} );
	}

	/**
	 * Colour pickers across the screen.
	 */
	function initColors( scope ) {
		( scope || document ).querySelectorAll( '.mmpcs-color' ).forEach( initColor );
	}

	/**
	 * Range inputs with a live readout.
	 */
	function initRanges() {
		document.querySelectorAll( '.mmpcs-range' ).forEach( function ( range ) {
			var output = range.parentNode.querySelector( '.mmpcs-output' );

			if ( ! output ) {
				return;
			}

			var sync = function () {
				output.textContent = range.value + ( range.dataset.suffix || '' );
			};

			range.addEventListener( 'input', sync );
			sync();
		} );
	}

	/**
	 * Give every field in a repeater a unique index so PHP receives a list.
	 *
	 * @param {Element} repeater Repeater wrapper.
	 */
	function reindex( repeater ) {
		var rows = repeater.querySelectorAll( '.mmpcs-rows > .mmpcs-row' );

		rows.forEach( function ( row, index ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[(?:__i__|\d+)\]\[/, '[' + index + '][' );
			} );
		} );
	}

	/**
	 * Repeater add, remove and reorder.
	 */
	function initRepeaters() {
		document.querySelectorAll( '.mmpcs-repeater' ).forEach( function ( repeater ) {
			var rows = repeater.querySelector( '.mmpcs-rows' );
			var add  = repeater.querySelector( '.mmpcs-add' );

			if ( add ) {
				add.addEventListener( 'click', function () {
					var tpl = document.getElementById( add.dataset.template );

					if ( ! tpl ) {
						return;
					}

					var html = tpl.innerHTML;

					if ( add.dataset.base ) {
						html = html.split( '__base__' ).join( add.dataset.base );
					}

					var holder = document.createElement( 'div' );
					holder.innerHTML = html.trim();

					var row = holder.querySelector( '.mmpcs-row' );

					if ( ! row ) {
						return;
					}

					rows.appendChild( row );
					reindex( repeater );
					initColors( row );

					var first = row.querySelector( 'input[type="text"], input[type="url"]' );
					if ( first ) {
						first.focus();
					}
				} );
			}

			repeater.addEventListener( 'click', function ( event ) {
				var row = event.target.closest( '.mmpcs-row' );

				if ( ! row || ! rows.contains( row ) ) {
					return;
				}

				if ( event.target.closest( '.mmpcs-remove' ) ) {
					event.preventDefault();

					if ( window.confirm( strings.confirmRow || 'Remove this row?' ) ) {
						row.remove();
						reindex( repeater );
					}

					return;
				}

				if ( event.target.closest( '.mmpcs-up' ) ) {
					event.preventDefault();

					if ( row.previousElementSibling ) {
						rows.insertBefore( row, row.previousElementSibling );
						reindex( repeater );
					}

					return;
				}

				if ( event.target.closest( '.mmpcs-down' ) ) {
					event.preventDefault();

					if ( row.nextElementSibling ) {
						rows.insertBefore( row.nextElementSibling, row );
						reindex( repeater );
					}
				}
			} );

			reindex( repeater );
		} );
	}

	/**
	 * Media library picker.
	 *
	 * Delegated from the document rather than bound per button, because logo
	 * rows are cloned from a template after this runs and a bound-at-init
	 * handler would never reach them.
	 */
	function initMedia() {
		var frame;

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.mmpcs-media-pick' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			var input = button.parentNode.querySelector( 'input' );

			if ( ! input ) {
				return;
			}

			frame = window.wp.media( {
				title: strings.mediaTitle || 'Choose a logo',
				button: { text: strings.mediaButton || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				input.value = attachment.url;

				// The library knows the real dimensions, so use them rather
				// than loading the file again just to measure it.
				if ( attachment.width ) {
					applyNaturalWidth( input, attachment.width );
				}

				syncLogoRow( input.closest( '.mmpcs-row--logo' ) );
				syncButtonRow( input.closest( '.mmpcs-row' ) );
			} );

			frame.open();
		} );
	}

	/**
	 * Show an image's natural width as a reference, and adopt it as the width
	 * when the author has not chosen one yet.
	 *
	 * @param {HTMLElement} input   The URL field.
	 * @param {number}      natural Natural width in pixels.
	 */
	function applyNaturalWidth( input, natural ) {
		var row = input.closest( '.mmpcs-row--logo' );

		if ( ! row || ! natural ) {
			return;
		}

		var hint  = row.querySelector( '[data-logo-natural]' );
		var width = row.querySelector( '[data-logo-width]' );

		if ( hint ) {
			hint.textContent = ( strings.naturalWidth || 'Original: %s px wide' )
				.replace( '%s', String( natural ) );
		}

		// Only ever a starting point: an author's own number is never
		// overwritten, and the stored range still applies.
		if ( width && ! width.value ) {
			width.value = Math.min( 800, Math.max( 40, natural ) );
		}
	}

	/**
	 * Measure a pasted URL, which has no attachment record to ask.
	 *
	 * @param {HTMLElement} input The URL field.
	 */
	function measurePastedImage( input ) {
		if ( ! input.value ) {
			return;
		}

		var probe = new Image();

		probe.onload = function () {
			applyNaturalWidth( input, probe.naturalWidth );
		};

		probe.src = input.value;
	}

	/**
	 * Keep a collapsed row's summary telling the truth about its contents.
	 *
	 * @param {HTMLElement} row A logo row.
	 */
	function syncLogoRow( row ) {
		if ( ! row ) {
			return;
		}

		var url   = row.querySelector( '[data-logo-url]' );
		var alt   = row.querySelector( '[data-logo-alt]' );
		var slot  = row.querySelector( '[data-logo-position]' );
		var thumb = row.querySelector( '[data-logo-thumb]' );
		var name  = row.querySelector( '[data-logo-name]' );
		var label = row.querySelector( '[data-logo-slot]' );

		if ( thumb && url ) {
			thumb.src = url.value;
			thumb.hidden = ! url.value;
		}

		if ( name && alt ) {
			name.textContent = alt.value || strings.untitled || 'Untitled logo';
		}

		if ( label && slot ) {
			label.textContent = slot.options[ slot.selectedIndex ].text;
		}
	}

	/**
	 * Reveal an arrangement control only for slots that actually hold more
	 * than one logo, since arrangement means nothing to a slot holding one.
	 */
	function syncLogoLayouts() {
		var block = document.querySelector( '[data-logo-layouts]' );

		if ( ! block ) {
			return;
		}

		var counts = {};

		document.querySelectorAll( '.mmpcs-row--logo' ).forEach( function ( row ) {
			var url  = row.querySelector( '[data-logo-url]' );
			var slot = row.querySelector( '[data-logo-position]' );

			if ( ! url || ! slot || ! url.value ) {
				return;
			}

			counts[ slot.value ] = ( counts[ slot.value ] || 0 ) + 1;
		} );

		var anyShared = false;

		block.querySelectorAll( '[data-slot]' ).forEach( function ( control ) {
			var shared = counts[ control.dataset.slot ] > 1;

			control.hidden = ! shared;
			anyShared = anyShared || shared;
		} );

		block.hidden = ! anyShared;
	}

	var MODES = [ 'name', 'text', 'image' ];

	/**
	 * Which mode a row is in, read from its values.
	 *
	 * Nothing stores the mode: an image is what makes a button an image button,
	 * so the values are the truth and cannot drift from a stored flag.
	 *
	 * @param {HTMLElement} row A button row.
	 * @return {string} One of MODES.
	 */
	function buttonMode( row ) {
		var image = row.querySelector( '[data-button-image]' );
		var label = row.querySelector( '[data-button-label]' );

		if ( image && image.value.trim() ) {
			return 'image';
		}

		if ( label && label.value.trim() ) {
			return 'text';
		}

		return 'name';
	}

	/**
	 * Put a row into a mode.
	 *
	 * The modes are exclusive, so entering one empties the field belonging to
	 * the other -- otherwise a hidden image would still be what the page
	 * rendered. What was typed is kept on the element, so going back restores
	 * it for as long as the page is open.
	 *
	 * @param {HTMLElement} row  A button row.
	 * @param {string}      mode One of MODES.
	 */
	function setButtonMode( row, mode ) {
		var label = row.querySelector( '[data-button-label]' );
		var image = row.querySelector( '[data-button-image]' );

		[ [ label, 'text' ], [ image, 'image' ] ].forEach( function ( pair ) {
			var input = pair[0];
			var owner = pair[1];

			if ( ! input ) {
				return;
			}

			if ( mode === owner ) {
				if ( ! input.value && input.dataset.stashed ) {
					input.value = input.dataset.stashed;
				}

				return;
			}

			if ( input.value ) {
				input.dataset.stashed = input.value;
			}

			input.value = '';
		} );

		syncButtonRow( row, mode );

		// Land the caret where the work is.
		var focusTarget = row.querySelector( '[data-field="' + mode + '"] input' );

		if ( focusTarget ) {
			focusTarget.focus();
		}
	}

	/**
	 * Reconcile one button row with its mode: which fields show, which segment
	 * is checked, and what a screen reader would announce.
	 *
	 * @param {HTMLElement} row     A button row.
	 * @param {string}      [force] Mode to apply, when it cannot be read back
	 *                              off the values yet -- choosing Image before
	 *                              an image has been picked, for instance.
	 */
	function syncButtonRow( row, force ) {
		if ( ! row ) {
			return;
		}

		var group = row.querySelector( '[data-button-mode]' );

		if ( ! group ) {
			return;
		}

		var mode = force || row.dataset.mode || buttonMode( row );

		row.dataset.mode = mode;

		MODES.forEach( function ( name ) {
			var wrap = row.querySelector( '[data-field="' + name + '"]' );

			if ( wrap ) {
				wrap.hidden = name !== mode;
			}
		} );

		var style = row.querySelector( '[data-field="style"]' );

		if ( style ) {
			// An image is the button, so a style variant has nothing to act on.
			style.hidden = 'image' === mode;
		}

		group.querySelectorAll( '[data-mode]' ).forEach( function ( option ) {
			var on = option.dataset.mode === mode;

			option.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			option.tabIndex = on ? 0 : -1;
		} );

		var nameField  = row.querySelector( '[data-button-name]' );
		var labelField = row.querySelector( '[data-button-label]' );

		announceButton(
			row,
			nameField ? nameField.value.trim() : '',
			labelField ? labelField.value.trim() : '',
			'image' === mode
		);
	}

	/**
	 * Say plainly what the rendered button will be called.
	 *
	 * The rule is WCAG 2.5.3: where text is visible, the accessible name has to
	 * contain it, or someone using voice control cannot activate what they can
	 * see. The renderer enforces that; this explains it at the moment the
	 * mismatch is created rather than leaving it to be found by an audit.
	 *
	 * @param {HTMLElement} row      The row.
	 * @param {string}      name     The name field.
	 * @param {string}      label    The optional button text.
	 * @param {boolean}     hasImage Whether the row is in image mode.
	 */
	function announceButton( row, name, label, hasImage ) {
		var out = row.querySelector( '[data-button-announce]' );

		if ( ! out ) {
			return;
		}

		out.classList.remove( 'is-warning' );

		/*
		 * With no image and no separate text the button says its own name, and
		 * the line would only repeat the field above it.
		 */
		if ( ! name || ( ! hasImage && ! label ) ) {
			out.textContent = '';
			out.hidden = true;

			return;
		}

		out.hidden = false;

		if ( hasImage || ! label ) {
			out.textContent = ( strings.announced || '%s' ).replace( '%s', name );

			return;
		}

		var contained = name.toLowerCase().indexOf( label.toLowerCase() ) !== -1;

		if ( ! contained ) {
			out.classList.add( 'is-warning' );
			out.textContent = ( strings.announced || '%s' ).replace( '%s', label ) +
				' — ' + ( strings.announceClash || '' );

			return;
		}

		out.textContent = ( strings.announced || '%s' ).replace( '%s', name );
	}

	/**
	 * Button rows: the mode control, and everything it implies.
	 */
	function initButtons() {
		var form = document.querySelector( '.mmpcs-form' ) || document;

		form.addEventListener( 'click', function ( event ) {
			var option = event.target.closest( '[data-mode]' );

			if ( ! option ) {
				return;
			}

			event.preventDefault();
			setButtonMode( option.closest( '.mmpcs-row' ), option.dataset.mode );
		} );

		// A radiogroup is expected to move between its options with the arrow
		// keys, with only the checked one in the tab order.
		form.addEventListener( 'keydown', function ( event ) {
			var option = event.target.closest( '[data-mode]' );

			if ( ! option ) {
				return;
			}

			var keys = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 };

			if ( ! ( event.key in keys ) ) {
				return;
			}

			event.preventDefault();

			var row  = option.closest( '.mmpcs-row' );
			var next = MODES.indexOf( option.dataset.mode ) + keys[ event.key ];

			if ( next < 0 ) {
				next = MODES.length - 1;
			} else if ( next >= MODES.length ) {
				next = 0;
			}

			setButtonMode( row, MODES[ next ] );
			row.querySelector( '[data-mode="' + MODES[ next ] + '"]' ).focus();
		} );

		form.addEventListener( 'input', function ( event ) {
			var row = event.target.closest( '.mmpcs-row' );

			if ( row ) {
				syncButtonRow( row, row.dataset.mode );
			}
		} );

		// Choosing from the media library sets a value in script, which fires
		// no input event of its own.
		form.addEventListener( 'change', function ( event ) {
			var row = event.target.closest( '.mmpcs-row' );

			if ( row ) {
				syncButtonRow( row, row.dataset.mode );
			}
		} );

		document.querySelectorAll( '.mmpcs-row' ).forEach( function ( row ) {
			syncButtonRow( row );
		} );
	}

	/**
	 * Wire the logo repeater's own behaviour on top of the generic one.
	 */
	function initLogos() {
		var form = document.querySelector( '.mmpcs-form' ) || document;

		form.addEventListener( 'input', function ( event ) {
			var row = event.target.closest( '.mmpcs-row--logo' );

			if ( ! row ) {
				return;
			}

			syncLogoRow( row );
			syncLogoLayouts();
		} );

		form.addEventListener( 'change', function ( event ) {
			var row = event.target.closest( '.mmpcs-row--logo' );

			if ( ! row ) {
				return;
			}

			if ( event.target.matches( '[data-logo-url]' ) ) {
				measurePastedImage( event.target );
			}

			syncLogoRow( row );
			syncLogoLayouts();
		} );

		// Adding or removing a row changes the counts too.
		form.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.mmpcs-add, .mmpcs-remove, .mmpcs-up, .mmpcs-down' ) ) {
				window.setTimeout( syncLogoLayouts, 0 );
			}
		} );

		syncLogoLayouts();
	}

	/*
	 * Settings that are nothing but a CSS custom property on the rendered page.
	 * These can be pushed into an open preview without re-rendering it, which
	 * is what makes dragging a colour picker feel live. Everything absent from
	 * this map changes markup and takes the slower path.
	 */
	var CSS_VARS = {
		'[palette][accent]': '--mmpcs-accent',
		'[palette][accent_hover]': '--mmpcs-accent-hover',
		'[palette][ink]': '--mmpcs-ink',
		'[palette][navy]': '--mmpcs-navy',
		'[palette][crimson]': '--mmpcs-crimson',
		'[palette][offwhite]': '--mmpcs-offwhite'
	};

	/* Fields belonging to options.php, never forwarded to the preview. */
	var RESERVED_FIELDS = [ '_wpnonce', '_wp_http_referer', 'option_page', 'action' ];

	var PREVIEW_WIDTH  = 1440;
	var PREVIEW_HEIGHT = 900;
	var FRAME_NAME     = 'mmpcs_preview_frame';
	var POPOUT_NAME    = 'mmpcs_preview_window';
	var STORAGE_KEY    = 'mmpcsPreviewOpen';

	var previewTimer;
	var popout;

	/**
	 * The settings form, or null on a screen without one.
	 *
	 * @return {HTMLElement|null} The form.
	 */
	function settingsForm() {
		return document.querySelector( '.mmpcs-form' );
	}

	/**
	 * Which custom property, if any, a field maps to.
	 *
	 * @param {HTMLElement} field A form field.
	 * @return {string|null} The property name.
	 */
	function cssVarFor( field ) {
		var name = field.getAttribute( 'name' ) || '';
		var key  = name.replace( strings.optionKey || '', '' );

		return CSS_VARS[ key ] || null;
	}

	/**
	 * Every custom property the current form state implies.
	 *
	 * @return {Object} Property name to value.
	 */
	function collectVars() {
		var form = settingsForm();
		var vars = {};

		if ( ! form ) {
			return vars;
		}

		form.querySelectorAll( '[name]' ).forEach( function ( field ) {
			var prop = cssVarFor( field );

			if ( prop && field.value ) {
				vars[ prop ] = field.value;
			}
		} );

		/*
		 * The page background is not a field of its own: the renderer uses the
		 * aurora's base colour when the aurora is on and the ink colour when it
		 * is off, so the same rule has to be applied here or the instant path
		 * would disagree with a re-render.
		 */
		var auroraOn = form.querySelector( '[name$="[aurora][enabled]"]' );
		var base     = form.querySelector( '[name$="[aurora][base]"]' );
		var ink      = form.querySelector( '[name$="[palette][ink]"]' );

		if ( auroraOn && auroraOn.checked && base && base.value ) {
			vars['--mmpcs-page-bg'] = base.value;
		} else if ( ink && ink.value ) {
			vars['--mmpcs-page-bg'] = ink.value;
		}

		return vars;
	}

	/**
	 * Push custom properties into every open preview.
	 */
	function pushVars() {
		var message = { type: 'mmpcs-preview-vars', vars: collectVars() };
		var frame   = document.querySelector( '[data-preview-frame]' );

		if ( frame && frame.contentWindow ) {
			frame.contentWindow.postMessage( message, window.location.origin );
		}

		if ( popout && ! popout.closed ) {
			popout.postMessage( message, window.location.origin );
		}
	}

	/**
	 * A colour changed: instant where it is only a custom property, a full
	 * re-render where it is not -- the aurora's blob colours are markup.
	 *
	 * @param {HTMLElement} input The colour field.
	 */
	function onColorChanged( input ) {
		if ( cssVarFor( input ) || input.name.indexOf( '[aurora][base]' ) !== -1 ) {
			pushVars();

			return;
		}

		schedulePreview();
	}

	/**
	 * Post the current, unsaved form state to one named target.
	 *
	 * A real form post rather than fetch(): the response is a whole HTML
	 * document, and a form can address an iframe or a named window directly
	 * without any of it passing through this page.
	 *
	 * @param {string} target Name of the frame or window to render into.
	 */
	function postPreview( target ) {
		var form = settingsForm();

		if ( ! form || ! strings.previewUrl ) {
			return;
		}

		var carrier = document.createElement( 'form' );

		carrier.method = 'post';
		carrier.action = strings.previewUrl;
		carrier.target = target;
		carrier.style.display = 'none';

		var nonce = document.createElement( 'input' );

		nonce.type  = 'hidden';
		nonce.name  = '_wpnonce';
		nonce.value = strings.previewNonce || '';
		carrier.appendChild( nonce );

		// FormData reflects exactly what a save would send, including which
		// checkboxes are unticked and how the repeaters are currently ordered.
		new window.FormData( form ).forEach( function ( value, key ) {
			if ( 'string' !== typeof value ) {
				return;
			}

			/*
			 * settings_fields() adds the plumbing options.php needs, and every
			 * piece of it collides with this endpoint: a second _wpnonce would
			 * override the one above and fail the check, and action=update
			 * would beat the action in the URL and misroute the request
			 * entirely. The settings themselves are all this endpoint wants.
			 */
			if ( RESERVED_FIELDS.indexOf( key ) !== -1 ) {
				return;
			}

			var field = document.createElement( 'input' );

			field.type  = 'hidden';
			field.name  = key;
			field.value = value;
			carrier.appendChild( field );
		} );

		document.body.appendChild( carrier );
		carrier.submit();
		document.body.removeChild( carrier );
	}

	/**
	 * Re-render every open preview, at most once per quiet moment.
	 */
	function schedulePreview() {
		window.clearTimeout( previewTimer );

		previewTimer = window.setTimeout( function () {
			var pane = document.querySelector( '[data-mmpcs-preview]' );

			setPreviewState( strings.previewUpdating );

			if ( pane && ! pane.classList.contains( 'is-collapsed' ) ) {
				postPreview( FRAME_NAME );
			}

			if ( popout && ! popout.closed ) {
				postPreview( POPOUT_NAME );
			}
		}, 400 );
	}

	/**
	 * @param {string} text Status text.
	 */
	function setPreviewState( text ) {
		var state = document.querySelector( '[data-preview-state]' );

		if ( state ) {
			state.textContent = text || '';
		}
	}

	/**
	 * Scale the full-width rendering down to whatever room the pane has.
	 */
	function fitPreview() {
		var stage = document.querySelector( '[data-preview-stage]' );
		var frame = document.querySelector( '[data-preview-frame]' );

		if ( ! stage || ! frame ) {
			return;
		}

		var scale = stage.clientWidth / PREVIEW_WIDTH;

		if ( ! scale ) {
			return;
		}

		frame.style.width     = PREVIEW_WIDTH + 'px';
		frame.style.height    = PREVIEW_HEIGHT + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		stage.style.height    = ( PREVIEW_HEIGHT * scale ) + 'px';
	}

	/**
	 * The live preview pane.
	 */
	function initPreview() {
		var pane = document.querySelector( '[data-mmpcs-preview]' );
		var form = settingsForm();

		if ( ! pane || ! form ) {
			return;
		}

		var toggle = pane.querySelector( '[data-preview-toggle]' );
		var frame  = pane.querySelector( '[data-preview-frame]' );
		var layout = document.querySelector( '[data-mmpcs-layout]' );

		// Collapsed state is a preference, so it outlives the page.
		var collapsed = 'false' === window.localStorage.getItem( STORAGE_KEY );


		/**
		 * Reflect the collapsed state on both the pane and the grid around it.
		 * The grid is what actually hands the width back to the settings.
		 *
		 * @param {boolean} isCollapsed Whether the pane is collapsed.
		 */
		function applyCollapsed( isCollapsed ) {
			pane.classList.toggle( 'is-collapsed', isCollapsed );

			if ( layout ) {
				layout.classList.toggle( 'is-preview-collapsed', isCollapsed );
			}

			if ( toggle ) {
				toggle.setAttribute( 'aria-expanded', isCollapsed ? 'false' : 'true' );
			}
		}

		/*
		 * True only when the pane was collapsed to make room for the popped-out
		 * window. A pane the author collapsed themselves stays collapsed when
		 * that window closes -- their choice is not ours to undo.
		 */
		var collapsedForPopout = false;

		/**
		 * Open the preview in its own window, or raise the one already open.
		 *
		 * Two triggers share this: the button in the pane, and the one above
		 * the tabs. Once a window exists, both simply bring it forward, which
		 * is the useful thing when it is buried behind other windows.
		 */
		function popOutPreview() {
			if ( popout && ! popout.closed ) {
				popout.focus();

				return;
			}

			// Blank and named, so the same form post can address it.
			popout = window.open( '', POPOUT_NAME, 'width=1280,height=860' );

			if ( ! popout ) {
				return;
			}

			postPreview( POPOUT_NAME );
			popout.focus();

			// The preview is in its own window now, so the pane is just taking
			// up room.
			if ( ! pane.classList.contains( 'is-collapsed' ) ) {
				collapsedForPopout = true;
				applyCollapsed( true );
			}

			var watch = window.setInterval( function () {
				if ( popout && ! popout.closed ) {
					return;
				}

				window.clearInterval( watch );

				if ( collapsedForPopout ) {
					collapsedForPopout = false;
					applyCollapsed( false );
					fitPreview();
					schedulePreview();
				}
			}, 1000 );
		}

		applyCollapsed( collapsed );

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var nowCollapsed = ! pane.classList.contains( 'is-collapsed' );

				applyCollapsed( nowCollapsed );
				window.localStorage.setItem( STORAGE_KEY, nowCollapsed ? 'false' : 'true' );

				if ( ! nowCollapsed ) {
					fitPreview();
					schedulePreview();
				}
			} );
		}

		/*
		 * Both live in the page: the one in the pane and the one above the
		 * tabs. The latter stays a real link to the nonce-gated preview URL, so
		 * it still does something useful with no JavaScript.
		 */
		document.querySelectorAll( '[data-preview-popout]' ).forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				popOutPreview();
			} );
		} );

		if ( frame ) {
			frame.addEventListener( 'load', function () {
				setPreviewState( strings.previewCurrent );
			} );
		}

		// A preview that has just finished loading asks for the current
		// colours, so a window opened after the last edit is not stale.
		window.addEventListener( 'message', function ( event ) {
			if ( event.origin !== window.location.origin ) {
				return;
			}

			if ( event.data && 'mmpcs-preview-ready' === event.data.type ) {
				pushVars();
			}
		} );

		form.addEventListener( 'input', function ( event ) {
			// Colour fields have their own instant path via the picker.
			if ( event.target.classList.contains( 'mmpcs-color' ) ) {
				return;
			}

			schedulePreview();
		} );

		form.addEventListener( 'change', function ( event ) {
			if ( event.target.classList.contains( 'mmpcs-color' ) ) {
				return;
			}

			schedulePreview();
		} );

		if ( window.ResizeObserver ) {
			new window.ResizeObserver( fitPreview ).observe( pane );
		} else {
			window.addEventListener( 'resize', fitPreview );
		}

		fitPreview();

		if ( ! collapsed ) {
			schedulePreview();
		}
	}

	/**
	 * The import button appears once there is a file to import.
	 *
	 * Offering it against an empty field only leads to a round trip that comes
	 * back saying to choose a file. The server still checks, since the button
	 * is not the guard -- it is only the invitation.
	 */
	function initImportGate() {
		var file   = document.querySelector( '[data-import-file]' );
		var submit = document.querySelector( '[data-import-submit]' );

		if ( ! file || ! submit ) {
			return;
		}

		function sync() {
			submit.hidden = ! ( file.files && file.files.length );
		}

		file.addEventListener( 'change', sync );

		// A reloaded page can arrive with a file still selected.
		sync();
	}

	/**
	 * The undo notice: shown on the Presets tab, dismissible, and gone after a
	 * while if it is left alone.
	 *
	 * It is only the reminder. The Presets panel carries a standing Undo control
	 * for as long as there is something to undo, so nothing is lost when this
	 * disappears.
	 */
	function initUndoNotice() {
		var notice = document.querySelector( '[data-mmpcs-undo]' );

		if ( ! notice ) {
			return;
		}

		var timer;
		var dismissed = false;

		function hide() {
			notice.hidden = true;
			window.clearTimeout( timer );
		}

		function sync() {
			var active = document.querySelector( '.mmpcs-tab.is-active' );
			var onTab  = active && 'presets' === active.dataset.tab;

			if ( dismissed || ! onTab ) {
				hide();

				return;
			}

			notice.hidden = false;

			window.clearTimeout( timer );
			timer = window.setTimeout( hide, parseInt( notice.dataset.timeout, 10 ) || 20000 );
		}

		// WordPress adds the dismiss button itself; catching the click keeps it
		// dismissed rather than having a tab switch bring it back.
		notice.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '.notice-dismiss' ) ) {
				dismissed = true;
				hide();
			}
		} );

		document.querySelectorAll( '.mmpcs-tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', sync );
		} );

		sync();
	}

	/**
	 * Confirm anything destructive before it submits.
	 */
	function initConfirms() {
		document.querySelectorAll( '[data-confirm]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( el.dataset.confirm ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	$( function () {
		initTabs();
		initConfirms();
		initColors();
		initRanges();
		initRepeaters();
		initMedia();
		initLogos();
		initButtons();
		initImportGate();
		initUndoNotice();
		initPreview();
	} );
}( window.jQuery ) );
