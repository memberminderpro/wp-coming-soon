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
			change: function () {},
			clear: function () {}
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
	} );
}( window.jQuery ) );
