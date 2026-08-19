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

		var saved;
		try {
			saved = window.localStorage.getItem( 'mmpcsTab' );
		} catch ( e ) {
			saved = null;
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
	 * Media library picker for the logo.
	 */
	function initMedia() {
		var frame;

		document.querySelectorAll( '.mmpcs-media-pick' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
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
				} );

				frame.open();
			} );
		} );
	}

	$( function () {
		initTabs();
		initColors();
		initRanges();
		initRepeaters();
		initMedia();
	} );
}( window.jQuery ) );
