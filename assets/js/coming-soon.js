/**
 * MMP Coming Soon — front-end behaviour.
 *
 * Keeps the copyright year current without a server round trip, so a cached
 * page never shows a stale year.
 */
(function () {
	'use strict';

	var year = String( new Date().getFullYear() );

	document.querySelectorAll( '[data-mmpcs-year]' ).forEach( function ( node ) {
		if ( node.textContent !== year ) {
			node.textContent = year;
		}
	} );
})();
