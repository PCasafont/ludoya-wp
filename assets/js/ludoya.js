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
	/**
	 * Load the venue map only when the visitor opens it.
	 *
	 * A closed <details> still fetches whatever <iframe> it holds, which would make a Google request
	 * on behalf of every visitor of every event page. So the template carries the address in
	 * data-src and it becomes the src the first time the map is unfolded.
	 */
	document.addEventListener( 'toggle', function ( event ) {
		var details = event.target;
		if ( ! details.classList || ! details.classList.contains( 'ludoya-map' ) || ! details.open ) {
			return;
		}
		var frame = details.querySelector( 'iframe[data-src]' );
		if ( frame && ! frame.getAttribute( 'src' ) ) {
			frame.setAttribute( 'src', frame.getAttribute( 'data-src' ) );
		}
	}, true );

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
