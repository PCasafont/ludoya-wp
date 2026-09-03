/* global ludoyaAdmin */
( function () {
	'use strict';

	/**
	 * Confirm the destructive links before they navigate.
	 */
	function guardDestructiveLinks() {
		document.querySelectorAll( '.ludoya-delete, .ludoya-delete-template' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( ludoyaAdmin.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Copy this element's data-copy to the clipboard, and say so.
	 *
	 * The async clipboard API needs a secure context, which a WordPress served over plain http is
	 * not, so there is a scratch-textarea fallback. Neither path depends on the surrounding markup,
	 * because these buttons sit in a settings table and in the rows of the events list alike.
	 */
	function bindCopyButtons() {
		document.querySelectorAll( '.ludoya-copy' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				var text = button.dataset.copy;
				var original = button.textContent;

				function confirmCopied() {
					button.textContent = ludoyaAdmin.copied;
					window.setTimeout( function () {
						button.textContent = original;
					}, 1500 );
				}

				function copyViaTextarea() {
					var scratch = document.createElement( 'textarea' );
					scratch.value = text;
					scratch.setAttribute( 'readonly', '' );
					scratch.style.position = 'fixed';
					scratch.style.left = '-9999px';
					document.body.appendChild( scratch );
					scratch.select();
					try {
						document.execCommand( 'copy' );
						confirmCopied();
					} catch ( e ) {
						window.prompt( ludoyaAdmin.copyManually, text );
					}
					document.body.removeChild( scratch );
				}

				if ( window.navigator.clipboard && window.isSecureContext ) {
					window.navigator.clipboard.writeText( text ).then( confirmCopied, copyViaTextarea );
					return;
				}
				copyViaTextarea();
			} );
		} );
	}

	/**
	 * Keep the table select showing only the tables of the chosen location.
	 */
	function bindSpotFilter() {
		var location = document.getElementById( 'ludoya-location' );
		var spot = document.getElementById( 'ludoya-spot' );
		if ( ! location || ! spot ) {
			return;
		}

		function apply() {
			var chosen = location.value;
			Array.prototype.forEach.call( spot.options, function ( option ) {
				if ( ! option.value ) {
					return;
				}
				var belongs = option.dataset.location === chosen;
				option.hidden = ! belongs;
				option.disabled = ! belongs;
				if ( ! belongs && spot.value === option.value ) {
					spot.value = '';
				}
			} );
		}

		location.addEventListener( 'change', apply );
		apply();
	}

	/**
	 * Search-as-you-type pickers for games and people.
	 *
	 * Debounced, because every keystroke would otherwise cost an API call against a per-minute
	 * rate limit shared with the front end.
	 */
	function bindPickers() {
		document.querySelectorAll( '.ludoya-picker' ).forEach( function ( picker ) {
			var input = picker.querySelector( 'input[type="search"]' );
			var results = picker.querySelector( '.ludoya-picker__results' );
			var chosen = picker.querySelector( '.ludoya-picker__chosen' );
			var target = document.getElementById( picker.dataset.target );
			var timer = null;

			if ( ! input || ! results || ! target ) {
				return;
			}

			function choose( item ) {
				target.value = item.id;
				chosen.textContent = item.label;
				results.innerHTML = '';
				input.value = '';
			}

			function render( items ) {
				results.innerHTML = '';
				if ( ! items.length ) {
					results.textContent = ludoyaAdmin.noResults;
					return;
				}
				items.forEach( function ( item ) {
					var li = document.createElement( 'li' );
					var button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'button-link';
					button.textContent = item.label;
					button.addEventListener( 'click', function () {
						choose( item );
					} );
					li.appendChild( button );
					results.appendChild( li );
				} );
			}

			function search() {
				var query = input.value.trim();
				if ( query.length < 2 ) {
					results.innerHTML = '';
					return;
				}
				results.textContent = ludoyaAdmin.searching;

				var url = ludoyaAdmin.ajaxUrl
					+ '?action=' + encodeURIComponent( picker.dataset.endpoint )
					+ '&nonce=' + encodeURIComponent( ludoyaAdmin.nonce )
					+ '&q=' + encodeURIComponent( query );

				window.fetch( url, { credentials: 'same-origin' } )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( payload ) {
						if ( ! payload.success ) {
							results.textContent = payload.data && payload.data.message
								? payload.data.message
								: ludoyaAdmin.noResults;
							return;
						}
						render( payload.data );
					} )
					.catch( function () {
						results.textContent = ludoyaAdmin.noResults;
					} );
			}

			input.addEventListener( 'input', function () {
				window.clearTimeout( timer );
				timer = window.setTimeout( search, 350 );
			} );

			// Enter in a picker should search, not submit the whole event form.
			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					window.clearTimeout( timer );
					search();
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		guardDestructiveLinks();
		bindCopyButtons();
		bindSpotFilter();
		bindPickers();
	} );
}() );
