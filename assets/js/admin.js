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
	 * rate limit shared with the front end. Arrow keys walk the results, Enter picks the marked
	 * one (or searches, when nothing is marked yet), Escape closes the list, and so does clicking
	 * anywhere else.
	 */
	function bindPickers() {
		document.querySelectorAll( '.ludoya-picker' ).forEach( function ( picker, pickerIndex ) {
			var input = picker.querySelector( 'input[type="search"]' );
			var results = picker.querySelector( '.ludoya-picker__results' );
			var chosen = picker.querySelector( '.ludoya-picker__chosen' );
			var target = document.getElementById( picker.dataset.target );
			var timer = null;
			var active = -1;

			if ( ! input || ! results || ! target ) {
				return;
			}

			results.id = results.id || 'ludoya-picker-results-' + pickerIndex;
			results.setAttribute( 'role', 'listbox' );
			input.setAttribute( 'role', 'combobox' );
			input.setAttribute( 'aria-controls', results.id );
			input.setAttribute( 'aria-expanded', 'false' );
			input.setAttribute( 'autocomplete', 'off' );

			function close() {
				results.innerHTML = '';
				active = -1;
				input.setAttribute( 'aria-expanded', 'false' );
			}

			function choose( item ) {
				target.value = item.id;
				chosen.textContent = item.label;
				input.value = '';
				close();
			}

			function options() {
				return results.querySelectorAll( 'li[role="option"]' );
			}

			function mark( index ) {
				var items = options();
				if ( ! items.length ) {
					return;
				}
				active = ( index + items.length ) % items.length;
				items.forEach( function ( li, i ) {
					li.classList.toggle( 'is-active', i === active );
					li.setAttribute( 'aria-selected', i === active ? 'true' : 'false' );
				} );
				items[ active ].scrollIntoView( { block: 'nearest' } );
			}

			function render( items ) {
				close();
				if ( ! items.length ) {
					results.textContent = ludoyaAdmin.noResults;
					return;
				}
				items.forEach( function ( item ) {
					var li = document.createElement( 'li' );
					li.setAttribute( 'role', 'option' );
					var button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'button-link';
					button.tabIndex = -1;
					button.textContent = item.label;
					button.addEventListener( 'click', function () {
						choose( item );
					} );
					li.appendChild( button );
					li.ludoyaItem = item;
					results.appendChild( li );
				} );
				input.setAttribute( 'aria-expanded', 'true' );
			}

			function search() {
				var query = input.value.trim();
				if ( query.length < 2 ) {
					close();
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

			input.addEventListener( 'keydown', function ( event ) {
				if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
					event.preventDefault();
					mark( active + ( 'ArrowDown' === event.key ? 1 : -1 ) );
					return;
				}
				if ( 'Escape' === event.key ) {
					close();
					return;
				}
				// Enter picks the marked result; with nothing marked it searches now instead of
				// submitting the whole event form.
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					var items = options();
					if ( active >= 0 && items[ active ] && items[ active ].ludoyaItem ) {
						choose( items[ active ].ludoyaItem );
						return;
					}
					window.clearTimeout( timer );
					search();
				}
			} );

			document.addEventListener( 'click', function ( event ) {
				if ( ! picker.contains( event.target ) ) {
					close();
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
