( function () {
	'use strict';

	/**
	 * Enforce "answer this" on required checkbox groups.
	 *
	 * A single checkbox can carry `required`, but a group cannot: the browser would demand every
	 * box, not at least one. So the template marks the group and this script holds a custom
	 * validity on the first box until any box is ticked — which keeps the browser's own bubble,
	 * focus behaviour and translations instead of reinventing them.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.ludoya-choices[data-required="1"]' ).forEach( function ( group ) {
			var boxes = group.querySelectorAll( 'input[type="checkbox"]' );
			if ( ! boxes.length ) {
				return;
			}

			function apply() {
				var any = Array.prototype.some.call( boxes, function ( box ) {
					return box.checked;
				} );
				boxes[ 0 ].setCustomValidity( any ? '' : group.dataset.message || '' );
			}

			boxes.forEach( function ( box ) {
				box.addEventListener( 'change', apply );
			} );
			apply();
		} );
	} );
}() );
