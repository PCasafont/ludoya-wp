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

	/**
	 * Lower case without accents, the form PHP wrote data-search in (ludoya_fold()).
	 *
	 * @param {string} text Any text.
	 * @return {string} Folded text.
	 */
	function fold( text ) {
		return String( text || '' ).normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();
	}

	/**
	 * Filters on a programme or a games list: chip groups plus a search box.
	 *
	 * The page arrives complete; this only hides what the visitor filtered out, so nothing depends
	 * on it. A day, an area or a kind is one chip per group; the search matches every word typed,
	 * in any order, against the title and the game. During the event the day opens on today — the
	 * question at the door is "what is on now" — and the visitor can switch with one tap.
	 *
	 * @param {Element} root The block.
	 */
	function filterable( root ) {
		var filters = root.querySelector( '[data-filters]' );
		if ( ! filters ) {
			return;
		}
		var items = root.querySelectorAll( '.ludoya-programme__item' );
		var search = filters.querySelector( '[data-filter-search]' );
		var count = filters.querySelector( '[data-count]' );
		var none = root.querySelector( '[data-none]' );
		var state = { day: '', area: '', kind: '', words: [] };
		var today = '';
		try {
			// en-CA formats a date as YYYY-MM-DD, the form the day keys are in.
			today = new Intl.DateTimeFormat( 'en-CA', { timeZone: root.getAttribute( 'data-tz' ) || undefined } ).format( new Date() );
		} catch ( e ) {
			today = '';
		}
		var unfold = root.querySelector( '[data-unfold]' );
		if ( unfold ) {
			unfold.addEventListener( 'click', function () {
				root.querySelectorAll( '[data-folded]' ).forEach( function ( grid ) {
					grid.removeAttribute( 'data-folded' );
				} );
				unfold.remove();
				unfold = null;
			} );
		}

		var tz = root.getAttribute( 'data-tz' ) || undefined;
		var alongOpened = false;
		var alongOpen = root.querySelector( '[data-along-open]' );
		if ( alongOpen ) {
			alongOpen.addEventListener( 'click', function () {
				alongOpened = true;
				root.classList.remove( 'is-now' );
				alongOpen.hidden = true;
			} );
		}
		var nowSection = null;

		function clock( ms ) {
			try {
				return new Intl.DateTimeFormat( document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit', timeZone: tz } ).format( new Date( ms ) );
			} catch ( e ) {
				return '';
			}
		}

		/**
		 * "Now and next" puts what is running first, under its own heading and marked as on, and
		 * leaves what is still to start under its hour. Without it, at 12:30 a game that began at
		 * 10:00 sat under "10:00" looking like a slot that had passed, or one still to book.
		 *
		 * @param {Array} running Items that started and have not ended.
		 * @return {number} How many were moved up.
		 */
		function showRunning( running ) {
			if ( nowSection ) {
				nowSection.remove();
				nowSection = null;
			}
			if ( ! running.length ) {
				return 0;
			}
			nowSection = document.createElement( 'section' );
			nowSection.className = 'ludoya-programme__section ludoya-programme__section--now';
			var heading = document.createElement( 'h3' );
			heading.className = 'ludoya-programme__day';
			heading.textContent = root.getAttribute( 'data-now-heading' ) || '';
			var grid = document.createElement( 'div' );
			grid.className = 'ludoya-events__grid';
			running.forEach( function ( item ) {
				var copy = item.cloneNode( true );
				copy.hidden = false;
				copy.classList.add( 'ludoya-programme__item--running' );
				// Started: the seats left are no longer an invitation to book.
				var seats = copy.querySelector( '.ludoya-tag--positive, .ludoya-tag--warning' );
				if ( seats ) {
					seats.className = 'ludoya-tag ludoya-tag--quiet';
					seats.textContent = root.getAttribute( 'data-started' ) || '';
				}
				var when = copy.querySelector( '.ludoya-card__when time' );
				var end = Date.parse( item.getAttribute( 'data-end' ) || '' );
				if ( when && ! isNaN( end ) ) {
					when.textContent = ( root.getAttribute( 'data-running' ) || '%s' ).replace( '%s', clock( end ) );
				}
				grid.appendChild( copy );
				item.hidden = true;
			} );
			nowSection.appendChild( heading );
			nowSection.appendChild( grid );
			var first = root.querySelector( '[data-section]' );
			if ( first ) {
				first.parentNode.insertBefore( nowSection, first );
			}
			return running.length;
		}

		function apply() {
			var shown = 0;
			var running = [];
			var now = Date.now();
			items.forEach( function ( item ) {
				var section = item.closest( '[data-section]' );
				var day = section ? section.getAttribute( 'data-day' ) : null;
				var text = item.getAttribute( 'data-search' ) || '';
				// "Now and next": today's entries that have not ended yet, plus what runs all along.
				var dayOk = ! state.day || null === day || day === state.day;
				if ( 'now' === state.day ) {
					var end = Date.parse( item.getAttribute( 'data-end' ) || '' );
					dayOk = null === day || ( day === today && ( isNaN( end ) || end > Date.now() ) );
				}
				var visible = dayOk &&
					( ! state.area || item.getAttribute( 'data-area' ) === state.area ) &&
					( ! state.kind || item.getAttribute( 'data-kind' ) === state.kind ) &&
					state.words.every( function ( word ) {
						return -1 !== text.indexOf( word );
					} );
				item.hidden = ! visible;
				if ( visible ) {
					shown++;
					var start = Date.parse( item.getAttribute( 'data-start' ) || '' );
					if ( 'now' === state.day && null !== day && ! isNaN( start ) && start <= now ) {
						running.push( item );
					}
				}
			} );
			showRunning( running );
			// A heading over nothing is noise: hide the hour and day headings left empty.
			root.querySelectorAll( '[data-slot], [data-section]:not(.ludoya-programme__section--now)' ).forEach( function ( group ) {
				group.hidden = ! group.querySelector( '.ludoya-programme__item:not([hidden])' );
			} );
			if ( count ) {
				count.textContent = 1 === shown ? count.getAttribute( 'data-one' ) : count.getAttribute( 'data-many' ).replace( '%d', shown );
			}
			if ( none ) {
				none.hidden = shown > 0;
			}
			// While filtering, nothing stays folded away: a match behind "Show more" would look missing.
			// "Now and next" on its own is the fair-day default, not a search: the drop-in band stays
			// folded so the timetable is not two screens down.
			var filtering = !! ( ( state.day && 'now' !== state.day ) || state.area || state.kind || state.words.length );
			root.classList.toggle( 'is-filtering', filtering );
			var nowOnly = 'now' === state.day && ! filtering;
			root.classList.toggle( 'is-now', nowOnly && ! alongOpened );
			if ( alongOpen ) {
				alongOpen.hidden = ! nowOnly || alongOpened;
			}
			if ( unfold ) {
				unfold.hidden = filtering;
			}
		}

		function choose( group, value ) {
			state[ group.getAttribute( 'data-filter' ) ] = value;
			group.querySelectorAll( '.ludoya-chip' ).forEach( function ( chip ) {
				chip.setAttribute( 'aria-pressed', chip.getAttribute( 'data-value' ) === value ? 'true' : 'false' );
			} );
		}

		filters.querySelectorAll( '[data-filter]' ).forEach( function ( group ) {
			group.addEventListener( 'click', function ( event ) {
				var chip = event.target.closest( '.ludoya-chip' );
				if ( ! chip ) {
					return;
				}
				choose( group, chip.getAttribute( 'data-value' ) );
				apply();
			} );
		} );

		if ( search ) {
			search.addEventListener( 'input', function () {
				state.words = fold( search.value ).split( ' ' ).filter( Boolean );
				apply();
			} );
		}

		if ( none ) {
			var reset = none.querySelector( '[data-reset]' );
			if ( reset ) {
				reset.addEventListener( 'click', function () {
					filters.querySelectorAll( '[data-filter]' ).forEach( function ( group ) {
						choose( group, '' );
					} );
					if ( search ) {
						search.value = '';
					}
					state.words = [];
					apply();
				} );
			}
		}

		// On a day of the event, offer "Now and next" and open on it; the visitor switches with one tap.
		var dayGroup = filters.querySelector( '[data-filter="day"]' );
		var nowChip = dayGroup ? dayGroup.querySelector( '[data-value="now"]' ) : null;
		if ( today && dayGroup && dayGroup.querySelector( '[data-value="' + today + '"]' ) ) {
			if ( nowChip ) {
				nowChip.hidden = false;
				choose( dayGroup, 'now' );
			} else {
				choose( dayGroup, today );
			}
		}

		var more = filters.querySelector( '[data-more-filters]' );
		if ( more && window.matchMedia && window.matchMedia( '(max-width: 600px)' ).matches ) {
			more.open = false;
		}

		filters.hidden = false;
		apply();
	}

	/**
	 * Keep a countdown ticking, and switch it to "on now" and "over" at the right moments.
	 *
	 * @param {Element} root The block.
	 */
	function countdown( root ) {
		var start = Date.parse( root.getAttribute( 'data-start' ) );
		var end = Date.parse( root.getAttribute( 'data-end' ) );
		if ( isNaN( start ) ) {
			return;
		}
		var parts = {};
		root.querySelectorAll( '[data-unit]' ).forEach( function ( node ) {
			parts[ node.getAttribute( 'data-unit' ) ] = node;
		} );
		var panes = {
			before: root.querySelector( '.ludoya-countdown__before' ),
			now: root.querySelector( '.ludoya-countdown__now' ),
			after: root.querySelector( '.ludoya-countdown__after' ),
		};
		var timer = null;

		function pad( n ) {
			return n < 10 ? '0' + n : String( n );
		}

		function tick() {
			var now = Date.now();
			var phase = now < start ? 'before' : ( now < end ? 'now' : 'after' );
			Object.keys( panes ).forEach( function ( key ) {
				if ( panes[ key ] ) {
					panes[ key ].hidden = key !== phase;
				}
			} );
			root.className = root.className.replace( /ludoya-countdown--\w+/, 'ludoya-countdown--' + phase );
			if ( 'before' !== phase ) {
				if ( 'after' === phase && timer ) {
					clearInterval( timer );
				}
				return;
			}
			var left = Math.floor( ( start - now ) / 1000 );
			if ( parts.days ) {
				parts.days.textContent = String( Math.floor( left / 86400 ) );
			}
			if ( parts.hours ) {
				parts.hours.textContent = pad( Math.floor( ( left % 86400 ) / 3600 ) );
			}
			if ( parts.minutes ) {
				parts.minutes.textContent = pad( Math.floor( ( left % 3600 ) / 60 ) );
			}
			if ( parts.seconds ) {
				parts.seconds.textContent = pad( left % 60 );
			}
		}

		tick();
		timer = setInterval( tick, 1000 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-ludoya-programme], [data-ludoya-games]' ).forEach( filterable );
		document.querySelectorAll( '[data-ludoya-countdown]' ).forEach( countdown );

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
