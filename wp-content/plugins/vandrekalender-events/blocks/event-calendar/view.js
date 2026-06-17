/**
 * Event Calendar — month grid view.
 *
 * Fetches events from the REST API, buckets them by date, and renders a
 * month grid where each day with events shows a dot sized by how many events
 * fall on it. Clicking a day lists that day's events below the grid. Month
 * navigation works on the cached data; a filter change refetches.
 *
 * Region / length / free filters are honoured. The bar's date range is ignored
 * here on purpose, because this view has its own month navigation.
 */

const WEEKDAYS = [ 'Man', 'Tir', 'Ons', 'Tor', 'Fre', 'Lør', 'Søn' ];

const monthFmt = new Intl.DateTimeFormat( 'da-DK', { month: 'long', year: 'numeric' } );
const longDateFmt = new Intl.DateTimeFormat( 'da-DK', {
	weekday: 'long',
	day: 'numeric',
	month: 'long',
} );

/**
 * Read region/length/free filters from the URL.
 *
 * @return {Object} Filter values keyed by REST param name.
 */
function readFilters() {
	const params = new URLSearchParams( window.location.search );
	const filters = {};
	[ 'region', 'length', 'is_free' ].forEach( ( key ) => {
		const value = params.get( key );
		if ( value ) {
			filters[ key ] = value;
		}
	} );
	return filters;
}

/**
 * Zero-padded date key (YYYY-MM-DD) in local time.
 *
 * @param {number} y Year.
 * @param {number} m Month index (0-11).
 * @param {number} d Day of month.
 * @return {string} Date key.
 */
function key( y, m, d ) {
	return `${ y }-${ String( m + 1 ).padStart( 2, '0' ) }-${ String( d ).padStart( 2, '0' ) }`;
}

/**
 * Price label for an event.
 *
 * @param {Object} event Event payload.
 * @return {string} Danish price label.
 */
function priceLabel( event ) {
	if ( event.is_free ) {
		return 'Gratis';
	}
	if ( event.price_from === null || event.price_from === undefined ) {
		return '';
	}
	return `fra ${ Math.round( event.price_from ) } kr`;
}

/**
 * Initialise one calendar instance.
 *
 * @param {HTMLElement} root The block wrapper element.
 */
function initCalendar( root ) {
	const restUrl = root.dataset.restUrl;
	const status = root.querySelector( '.vk-calendar__status' );
	const inner = root.querySelector( '.vk-calendar__inner' );
	const error = root.querySelector( '.vk-calendar__error' );

	const today = new Date();
	const todayKey = key( today.getFullYear(), today.getMonth(), today.getDate() );

	let byDate = {};
	let viewYear = today.getFullYear();
	let viewMonth = today.getMonth();
	let selectedKey = null;
	let requestId = 0;

	function render() {
		inner.innerHTML = '';

		// Header with month label and navigation.
		const header = document.createElement( 'div' );
		header.className = 'vk-calendar__header';
		const label = monthFmt.format( new Date( viewYear, viewMonth, 1 ) );
		header.innerHTML = `
			<button type="button" class="vk-calendar__nav" data-dir="-1" aria-label="Forrige måned">‹</button>
			<h3 class="vk-calendar__month">${ label.charAt( 0 ).toUpperCase() + label.slice( 1 ) }</h3>
			<button type="button" class="vk-calendar__nav" data-dir="1" aria-label="Næste måned">›</button>
		`;
		inner.appendChild( header );

		// Grid.
		const grid = document.createElement( 'div' );
		grid.className = 'vk-calendar__grid';

		WEEKDAYS.forEach( ( name ) => {
			const head = document.createElement( 'div' );
			head.className = 'vk-calendar__weekday';
			head.textContent = name;
			grid.appendChild( head );
		} );

		const firstOffset = ( new Date( viewYear, viewMonth, 1 ).getDay() + 6 ) % 7;
		const daysInMonth = new Date( viewYear, viewMonth + 1, 0 ).getDate();
		const totalCells = Math.ceil( ( firstOffset + daysInMonth ) / 7 ) * 7;

		for ( let cell = 0; cell < totalCells; cell++ ) {
			const dayNum = cell - firstOffset + 1;
			const inMonth = dayNum >= 1 && dayNum <= daysInMonth;
			const day = document.createElement( inMonth ? 'button' : 'div' );
			day.className = 'vk-calendar__day';

			if ( ! inMonth ) {
				day.classList.add( 'is-other-month' );
				grid.appendChild( day );
				continue;
			}

			const dateKey = key( viewYear, viewMonth, dayNum );
			const events = byDate[ dateKey ] || [];
			day.type = 'button';
			day.dataset.dateKey = dateKey;

			if ( dateKey === todayKey ) {
				day.classList.add( 'is-today' );
			}
			if ( dateKey === selectedKey ) {
				day.classList.add( 'is-selected' );
			}

			let dot = '';
			if ( events.length ) {
				const size = events.length <= 2 ? ' vk-calendar__dot--sm' : events.length > 6 ? ' vk-calendar__dot--lg' : '';
				dot = `<span class="vk-calendar__dot${ size }">${ events.length }</span>`;
				day.classList.add( 'has-events' );
			} else {
				day.disabled = true;
			}

			day.innerHTML = `<span class="vk-calendar__num">${ dayNum }</span>${ dot }`;
			grid.appendChild( day );
		}

		inner.appendChild( grid );

		// Selected day's events.
		const dayEvents = document.createElement( 'div' );
		dayEvents.className = 'vk-calendar__day-events';
		if ( selectedKey && byDate[ selectedKey ] ) {
			const events = byDate[ selectedKey ];
			const heading = longDateFmt.format( new Date( selectedKey ) );
			const rows = events
				.map( ( event ) => {
					const dist = ( event.distances_km || [] ).length
						? `<span class="vk-calendar__event-dist">${ event.distances_km.join( ', ' ) } km</span>`
						: '';
					const price = priceLabel( event );
					return `<a class="vk-calendar__event" href="${ event.permalink }">
						<span class="vk-calendar__event-title">${ event.title }</span>
						${ dist }
						${ price ? `<span class="vk-calendar__event-price">${ price }</span>` : '' }
					</a>`;
				} )
				.join( '' );
			dayEvents.innerHTML = `<p class="vk-calendar__day-label">${ heading } — ${ events.length } vandreture</p>${ rows }`;
		}
		inner.appendChild( dayEvents );

		// Wire navigation and day selection.
		header.querySelectorAll( '.vk-calendar__nav' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				viewMonth += Number( btn.dataset.dir );
				if ( viewMonth < 0 ) {
					viewMonth = 11;
					viewYear--;
				} else if ( viewMonth > 11 ) {
					viewMonth = 0;
					viewYear++;
				}
				selectedKey = null;
				render();
			} );
		} );

		grid.querySelectorAll( '.vk-calendar__day.has-events' ).forEach( ( day ) => {
			day.addEventListener( 'click', () => {
				selectedKey = selectedKey === day.dataset.dateKey ? null : day.dataset.dateKey;
				render();
			} );
		} );
	}

	async function load() {
		const current = ++requestId;
		const query = new URLSearchParams( { ...readFilters(), per_page: 100 } ).toString();

		status.hidden = false;
		error.hidden = true;

		try {
			const response = await fetch( `${ restUrl }?${ query }` );
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}
			const events = await response.json();
			if ( current !== requestId ) {
				return;
			}

			byDate = {};
			( Array.isArray( events ) ? events : [] ).forEach( ( event ) => {
				if ( ! event.date ) {
					return;
				}
				( byDate[ event.date ] = byDate[ event.date ] || [] ).push( event );
			} );

			status.hidden = true;
			inner.hidden = false;
			render();
		} catch ( err ) {
			if ( current !== requestId ) {
				return;
			}
			status.hidden = true;
			inner.hidden = true;
			error.hidden = false;
		}
	}

	document.addEventListener( 'vk:filters-change', load );
	load();
}

document.addEventListener( 'DOMContentLoaded', () => {
	document
		.querySelectorAll( '.vk-calendar' )
		.forEach( ( root ) => initCalendar( root ) );
} );
