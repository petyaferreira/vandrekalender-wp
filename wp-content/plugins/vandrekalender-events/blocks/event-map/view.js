/**
 * Event Map — frontend view.
 *
 * Lazy-loads Leaflet (and the marker-cluster plugin) from a CDN, then renders a
 * Denmark map with one pin per event that has coordinates. Pins are a single
 * brand colour. Reacts to filter changes and works inside a hidden tab
 * (re-measures itself when it becomes visible).
 */

const LEAFLET_VERSION = '1.9.4';
const CLUSTER_VERSION = '1.5.3';
const CDN = 'https://cdnjs.cloudflare.com/ajax/libs';

const ASSETS = {
	leafletCss: `${ CDN }/leaflet/${ LEAFLET_VERSION }/leaflet.min.css`,
	leafletJs: `${ CDN }/leaflet/${ LEAFLET_VERSION }/leaflet.min.js`,
	clusterCss: `${ CDN }/leaflet.markercluster/${ CLUSTER_VERSION }/MarkerCluster.min.css`,
	clusterDefaultCss: `${ CDN }/leaflet.markercluster/${ CLUSTER_VERSION }/MarkerCluster.Default.min.css`,
	clusterJs: `${ CDN }/leaflet.markercluster/${ CLUSTER_VERSION }/leaflet.markercluster.min.js`,
};

const dateFmt = new Intl.DateTimeFormat( 'da-DK', {
	weekday: 'short',
	day: 'numeric',
	month: 'short',
} );

/**
 * Append a stylesheet once.
 *
 * @param {string} href Stylesheet URL.
 */
function loadStyle( href ) {
	if ( document.querySelector( `link[href="${ href }"]` ) ) {
		return;
	}
	const link = document.createElement( 'link' );
	link.rel = 'stylesheet';
	link.href = href;
	document.head.appendChild( link );
}

/**
 * Load a script once, resolving when ready.
 *
 * @param {string} src Script URL.
 * @return {Promise} Resolves on load, rejects on error.
 */
function loadScript( src ) {
	return new Promise( ( resolve, reject ) => {
		const existing = document.querySelector( `script[src="${ src }"]` );
		if ( existing ) {
			if ( existing.dataset.loaded ) {
				resolve();
			} else {
				existing.addEventListener( 'load', () => resolve() );
				existing.addEventListener( 'error', reject );
			}
			return;
		}
		const script = document.createElement( 'script' );
		script.src = src;
		script.async = true;
		script.addEventListener( 'load', () => {
			script.dataset.loaded = '1';
			resolve();
		} );
		script.addEventListener( 'error', reject );
		document.head.appendChild( script );
	} );
}

/**
 * Ensure Leaflet (and, best effort, the cluster plugin) are available.
 *
 * @return {Promise} Resolves once Leaflet is ready.
 */
async function ensureLeaflet() {
	loadStyle( ASSETS.leafletCss );
	if ( ! window.L ) {
		await loadScript( ASSETS.leafletJs );
	}
	loadStyle( ASSETS.clusterCss );
	loadStyle( ASSETS.clusterDefaultCss );
	if ( window.L && ! window.L.markerClusterGroup ) {
		try {
			await loadScript( ASSETS.clusterJs );
		} catch ( e ) {
			// Clustering is optional; fall back to a plain layer group.
		}
	}
}

/**
 * Read filters from the URL.
 *
 * @return {Object} Filter values keyed by REST param name.
 */
function readFilters() {
	const params = new URLSearchParams( window.location.search );
	const filters = {};
	[ 'region', 'length', 'is_free', 'date_from', 'date_to' ].forEach( ( key ) => {
		const value = params.get( key );
		if ( value ) {
			filters[ key ] = value;
		}
	} );
	return filters;
}

/**
 * Build popup HTML for an event.
 *
 * @param {Object} event Event payload.
 * @return {string} Popup markup.
 */
function popupHtml( event ) {
	let date = '';
	if ( event.date ) {
		const parsed = new Date( event.date );
		date = isNaN( parsed ) ? event.date : dateFmt.format( parsed );
	}
	const dist = ( event.distances_km || [] ).length ? `${ event.distances_km.join( ', ' ) } km` : '';
	const price = event.is_free
		? 'Gratis'
		: event.price_from != null
		? `fra ${ Math.round( event.price_from ) } kr`
		: '';
	const meta = [ date, dist, price ].filter( Boolean ).join( ' · ' );

	return `<div class="vk-map__popup">
		<strong>${ event.title }</strong>
		${ meta ? `<span class="vk-map__popup-meta">${ meta }</span>` : '' }
		<a href="${ event.permalink }">Se detaljer</a>
	</div>`;
}

/**
 * Initialise one map instance.
 *
 * @param {HTMLElement} root The block wrapper element.
 */
async function initMap( root ) {
	const canvas = root.querySelector( '.vk-map__canvas' );
	const status = root.querySelector( '.vk-map__status' );
	const resetBtn = root.querySelector( '.vk-map__reset' );
	const restUrl = root.dataset.restUrl;
	const center = [ parseFloat( root.dataset.lat ), parseFloat( root.dataset.lng ) ];
	const zoom = parseInt( root.dataset.zoom, 10 );

	try {
		await ensureLeaflet();
	} catch ( e ) {
		status.textContent = 'Kortet kunne ikke indlæses.';
		return;
	}

	const L = window.L;
	const map = L.map( canvas, { scrollWheelZoom: false } ).setView( center, zoom );

	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '© OpenStreetMap',
		maxZoom: 18,
	} ).addTo( map );

	const pinIcon = L.divIcon( {
		className: 'vk-map__pin-wrap',
		html: '<span class="vk-map__pin"></span>',
		iconSize: [ 22, 22 ],
		iconAnchor: [ 11, 22 ],
		popupAnchor: [ 0, -20 ],
	} );

	const layer = L.markerClusterGroup ? L.markerClusterGroup() : L.layerGroup();
	map.addLayer( layer );

	let requestId = 0;
	let lastPoints = [];

	function fitToPoints() {
		if ( lastPoints.length ) {
			map.fitBounds( lastPoints, { padding: [ 30, 30 ], maxZoom: 11 } );
		} else {
			map.setView( center, zoom );
		}
	}

	function resetView() {
		map.setView( center, zoom );
	}
	resetBtn.addEventListener( 'click', resetView );

	async function load() {
		const current = ++requestId;
		const query = new URLSearchParams( { ...readFilters(), per_page: 100 } ).toString();
		status.hidden = false;
		status.textContent = 'Indlæser kort…';

		try {
			const response = await fetch( `${ restUrl }?${ query }` );
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}
			const events = await response.json();
			if ( current !== requestId ) {
				return;
			}

			layer.clearLayers();
			const points = [];
			( Array.isArray( events ) ? events : [] ).forEach( ( event ) => {
				if ( event.lat == null || event.lng == null || ( event.lat === 0 && event.lng === 0 ) ) {
					return;
				}
				const marker = L.marker( [ event.lat, event.lng ], { icon: pinIcon } );
				marker.bindPopup( popupHtml( event ) );
				layer.addLayer( marker );
				points.push( [ event.lat, event.lng ] );
			} );

			lastPoints = points;
			status.hidden = true;
			resetBtn.hidden = false;

			if ( points.length ) {
				fitToPoints();
			} else {
				resetView();
				status.hidden = false;
				status.textContent = 'Ingen vandreture med placering matcher dine filtre.';
			}
		} catch ( err ) {
			if ( current !== requestId ) {
				return;
			}
			status.hidden = false;
			status.textContent = 'Kunne ikke indlæse kortet. Prøv igen.';
		}
	}

	// Re-measure when the map becomes visible (e.g. when its tab is opened).
	// The first time it gets real size, re-fit, because bounds computed while
	// the container was hidden (zero-size) would be wrong.
	if ( 'ResizeObserver' in window ) {
		let sized = false;
		const observer = new ResizeObserver( () => {
			if ( canvas.offsetWidth > 0 && ! sized ) {
				sized = true;
				map.invalidateSize();
				fitToPoints();
			}
		} );
		observer.observe( canvas );
	}

	document.addEventListener( 'vk:filters-change', load );
	load();
}

document.addEventListener( 'DOMContentLoaded', () => {
	document.querySelectorAll( '.vk-map' ).forEach( ( root ) => initMap( root ) );
} );
