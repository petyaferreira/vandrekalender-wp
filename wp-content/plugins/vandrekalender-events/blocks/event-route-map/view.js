/**
 * Event Route Map — frontend view.
 *
 * Plain classic script, not the Interactivity API: the single event page is
 * not a router region, so there is no <head>-morphing hazard to guard
 * against and no shared state to coordinate (see docs/route-gpx-plan.md,
 * PR 3b). Lazy-loads Leaflet and the leaflet-gpx plugin from a CDN, draws
 * one L.GPX layer per route that has a file, and shows only the currently
 * selected route's track (matching the Event Info Card's active tab on
 * first paint, then the `vk:route-change` event it dispatches on every
 * click — blocks/event-info-card/view.js), with a "Show all routes" toggle
 * button to see every track together.
 */

const LEAFLET_VERSION = '1.9.4';
const GPX_PLUGIN_VERSION = '2.2.0';
const CDN = 'https://cdnjs.cloudflare.com/ajax/libs';

const ASSETS = {
  leafletJs: `${CDN}/leaflet/${LEAFLET_VERSION}/leaflet.min.js`,
  gpxJs: `${CDN}/leaflet-gpx/${GPX_PLUGIN_VERSION}/gpx.min.js`,
};

// Distinct per-route colours, cycling if there are more routes than colours.
const ROUTE_COLORS = [
  '#2D5F3F', // forest
  '#D97535', // amber
  '#1E6FA5', // fjord
  '#7A5230', // bark
  '#527A50', // fern
];

/**
 * Load a script once, resolving when ready.
 *
 * @param {string} src Script URL.
 * @return {Promise} Resolves on load, rejects on error.
 */
function loadScript(src) {
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${src}"]`);
    if (existing) {
      if (existing.dataset.loaded) {
        resolve();
      } else {
        existing.addEventListener('load', () => resolve());
        existing.addEventListener('error', reject);
      }
      return;
    }
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.addEventListener('load', () => {
      script.dataset.loaded = '1';
      resolve();
    });
    script.addEventListener('error', reject);
    document.head.appendChild(script);
  });
}

/**
 * Ensure Leaflet and the leaflet-gpx plugin are available.
 *
 * @return {Promise} Resolves once both are ready.
 */
async function ensureLeafletGpx() {
  if (!window.L) {
    await loadScript(ASSETS.leafletJs);
  }
  if (!window.L.GPX) {
    await loadScript(ASSETS.gpxJs);
  }
}

/**
 * A plain, single-colour marker icon — leaflet-gpx's own default start/end
 * markers point at GitHub-hosted images (see docs/route-gpx-plan.md), so
 * these replace them instead of disabling them outright.
 *
 * @param {string} modifier 'start' or 'end', for the CSS modifier class.
 * @return {L.DivIcon}
 */
function plainMarkerIcon(modifier) {
  return window.L.divIcon({
    className: `vk-route-map__marker vk-route-map__marker--${modifier}`,
    html: '<span></span>',
    iconSize: [14, 14],
    iconAnchor: [7, 7],
  });
}

/**
 * Initialise one route map instance.
 *
 * @param {HTMLElement} root The `.vk-route-map[data-vk-routes]` element.
 */
function initRouteMap(root) {
  let routes;
  try {
    routes = JSON.parse(root.dataset.vkRoutes || '[]');
  } catch {
    return;
  }

  if (!Array.isArray(routes) || !routes.length) {
    return;
  }

  const canvas = root.querySelector('.vk-route-map__canvas');
  if (!canvas) {
    return;
  }

  (async () => {
    try {
      await ensureLeafletGpx();
    } catch {
      return;
    }

    const L = window.L;
    const map = L.map(canvas);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 18,
    }).addTo(map);

    const layers = new Map();
    let loadedCount = 0;

    const fitAll = () => {
      const bounds = L.latLngBounds([]);
      layers.forEach(layer => {
        if (layer.getBounds().isValid()) {
          bounds.extend(layer.getBounds());
        }
      });
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [16, 16] });
      }
    };

    // `currentId` starts as the Event Info Card's default active route
    // (`data-vk-initial-route-id`, the first route in event_routes order —
    // see render.php), not null, so first paint already matches the card
    // instead of showing every track until the first tab click. `showAll`
    // is the toggle button's state; selecting a route always turns it off
    // again — the toggle is an override the reader reaches for on top of a
    // selection, not a separate mode that survives switching routes.
    let currentId = root.dataset.vkInitialRouteId || null;
    let showAll = false;
    // Tracks whether a route with no track has ever been reached by an
    // actual click, as opposed to being the initial default (see below).
    let hasInteracted = false;

    const toggleButton = root.querySelector('[data-vk-route-map-toggle]');

    // Shows either every route (`showAll`) or only `currentId`, hiding the
    // rest — not just dimming them, to match how every other tab-driven
    // value in the Event Info Card (price, start time, cutoff) fully
    // replaces on switch rather than leaving the previous selection
    // lingering. A tab is clicked for every route, including ones with no
    // GPX file, so `currentId` can be a real route with no matching layer
    // here: that hides every track. On a genuine click this leaves the
    // current view as is (an empty map beats snapping back to show
    // unrelated tracks, which read as a broken click); but if the *initial*
    // route (the info card's default active tab) has no track, nothing has
    // set the map's view yet, so falling back to fitAll() beats leaving a
    // blank, unset Leaflet canvas.
    const render = () => {
      const activeId =
        !showAll && currentId && layers.has(currentId) ? currentId : null;

      layers.forEach((layer, layerId) => {
        const shouldShow = showAll || !currentId || layerId === activeId;
        if (shouldShow && !map.hasLayer(layer)) {
          layer.addTo(map);
        } else if (!shouldShow && map.hasLayer(layer)) {
          map.removeLayer(layer);
        }
      });

      if (showAll || !currentId || (currentId && !activeId && !hasInteracted)) {
        fitAll();
      } else if (activeId) {
        const active = layers.get(activeId);
        if (active.getBounds().isValid()) {
          map.fitBounds(active.getBounds(), { padding: [16, 16] });
        }
      }
    };

    routes.forEach((route, index) => {
      const color = ROUTE_COLORS[index % ROUTE_COLORS.length];

      const gpxLayer = new L.GPX(route.gpx_url, {
        async: true,
        polyline_options: { color },
        markers: {
          startIcon: plainMarkerIcon('start'),
          endIcon: plainMarkerIcon('end'),
        },
      }).on('loaded', () => {
        loadedCount += 1;
        if (loadedCount === routes.length) {
          render();
        }
      });

      layers.set(String(route.id), gpxLayer);
    });

    document.addEventListener('vk:route-change', event => {
      hasInteracted = true;
      currentId =
        event.detail && event.detail.id ? String(event.detail.id) : null;
      showAll = false;
      if (toggleButton) {
        toggleButton.setAttribute('aria-pressed', 'false');
      }
      render();
    });

    if (toggleButton) {
      toggleButton.addEventListener('click', () => {
        showAll = !showAll;
        toggleButton.setAttribute('aria-pressed', String(showAll));
        render();
      });
    }
  })();
}

document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('.vk-route-map[data-vk-routes]')
    .forEach(initRouteMap);
});
