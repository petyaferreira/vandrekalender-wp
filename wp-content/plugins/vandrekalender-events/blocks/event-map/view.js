/**
 * Event Map — frontend view module (Interactivity API).
 *
 * Leaflet needs the client, so unlike the Event Cards block this one only
 * partially server-renders: render.php embeds the map config and the initial
 * pin payload in the block context, and this module lazy-loads Leaflet (and
 * the marker-cluster plugin) from a CDN and drops the embedded pins — no REST
 * request on first paint. A filter change refetches pins from the
 * /events/locations endpoint. The map re-measures itself when it becomes
 * visible (e.g. inside a hidden tab).
 *
 * The filters listener is attached in `callbacks.init` rather than a
 * `data-wp-on-document--` directive because the directive parser rejects the
 * colon in the event name.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const LEAFLET_VERSION = '1.9.4';
const CLUSTER_VERSION = '1.5.3';
const CDN = 'https://cdnjs.cloudflare.com/ajax/libs';

const ASSETS = {
  leafletCss: `${CDN}/leaflet/${LEAFLET_VERSION}/leaflet.min.css`,
  leafletJs: `${CDN}/leaflet/${LEAFLET_VERSION}/leaflet.min.js`,
  clusterCss: `${CDN}/leaflet.markercluster/${CLUSTER_VERSION}/MarkerCluster.min.css`,
  clusterDefaultCss: `${CDN}/leaflet.markercluster/${CLUSTER_VERSION}/MarkerCluster.Default.min.css`,
  clusterJs: `${CDN}/leaflet.markercluster/${CLUSTER_VERSION}/leaflet.markercluster.min.js`,
};

const dateFmt = new Intl.DateTimeFormat('da-DK', {
  weekday: 'short',
  day: 'numeric',
  month: 'short',
});

// Leaflet instances per block wrapper, so actions (resetView) can reach the
// map created in callbacks.init. Leaflet objects are not serialisable, so
// they cannot live in the context itself.
const instances = new WeakMap();

/**
 * Append a stylesheet once.
 *
 * @param {string} href Stylesheet URL.
 */
function loadStyle(href) {
  if (document.querySelector(`link[href="${href}"]`)) {
    return;
  }
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = href;
  document.head.appendChild(link);
}

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
 * Ensure Leaflet (and, best effort, the cluster plugin) are available.
 *
 * @return {Promise} Resolves once Leaflet is ready.
 */
async function ensureLeaflet() {
  loadStyle(ASSETS.leafletCss);
  if (!window.L) {
    await loadScript(ASSETS.leafletJs);
  }
  loadStyle(ASSETS.clusterCss);
  loadStyle(ASSETS.clusterDefaultCss);
  if (window.L && !window.L.markerClusterGroup) {
    try {
      await loadScript(ASSETS.clusterJs);
    } catch (e) {
      // Clustering is optional; fall back to a plain layer group.
    }
  }
}

/**
 * Read filters from the URL.
 *
 * @return {Object} Filter values keyed by REST param name.
 */
function readUrlFilters() {
  const params = new URLSearchParams(window.location.search);
  const filters = {};
  ['region', 'length', 'is_free', 'date_from', 'date_to'].forEach(key => {
    const value = params.get(key);
    if (value) {
      filters[key] = value;
    }
  });
  return filters;
}

/**
 * Build popup HTML for an event.
 *
 * @param {Object} event Event payload.
 * @return {string} Popup markup.
 */
function popupHtml(event) {
  let date = '';
  if (event.date) {
    const parsed = new Date(event.date);
    date = isNaN(parsed) ? event.date : dateFmt.format(parsed);
  }
  const dist = (event.distances_km || []).length
    ? `${event.distances_km.join(', ')} km`
    : '';
  const price = event.is_free
    ? 'Gratis'
    : event.price_from != null
      ? `fra ${Math.round(event.price_from)} kr`
      : '';
  const meta = [date, dist, price].filter(Boolean).join(' · ');

  return `<div class="vk-map__popup">
		<strong>${event.title}</strong>
		${meta ? `<span class="vk-map__popup-meta">${meta}</span>` : ''}
		<a href="${event.permalink}">Se detaljer</a>
	</div>`;
}

store('vandrekalender/event-map', {
  actions: {
    resetView() {
      const ctx = getContext();
      const { ref } = getElement();
      const instance = instances.get(ref.closest('.vk-map'));
      if (instance) {
        instance.map.setView([ctx.lat, ctx.lng], ctx.zoom);
      }
    },
  },
  callbacks: {
    init() {
      const ctx = getContext();
      const { ref: root } = getElement();
      const canvas = root.querySelector('.vk-map__canvas');

      let instance = null;
      let pendingReload = false;
      let cancelled = false;
      let needsFit = false;
      let requestId = 0;

      const setStatus = text => {
        ctx.statusText = text || '';
        ctx.statusHidden = !text;
      };

      const readFilters = () => ({ ...readUrlFilters(), ...ctx.presets });

      function fitToPoints() {
        // Bounds computed against a hidden (zero-size) map would be wrong;
        // remember to re-fit when the map becomes visible instead.
        if (canvas.offsetWidth === 0) {
          needsFit = true;
          return;
        }
        needsFit = false;
        if (instance.lastPoints.length) {
          instance.map.fitBounds(instance.lastPoints, {
            padding: [30, 30],
            maxZoom: 11,
          });
        } else {
          instance.map.setView([ctx.lat, ctx.lng], ctx.zoom);
        }
      }

      function renderPins(events) {
        const L = window.L;
        instance.layer.clearLayers();
        const points = [];
        (Array.isArray(events) ? events : []).forEach(event => {
          if (
            event.lat == null ||
            event.lng == null ||
            (event.lat === 0 && event.lng === 0)
          ) {
            return;
          }
          const marker = L.marker([event.lat, event.lng], {
            icon: instance.pinIcon,
          });
          marker.bindPopup(popupHtml(event), { closeButton: false });

          let closeTimer;
          const cancelClose = () => clearTimeout(closeTimer);
          const scheduleClose = () => {
            closeTimer = setTimeout(() => marker.closePopup(), 200);
          };

          marker.on('mouseover', function () {
            cancelClose();
            this.openPopup();
          });
          marker.on('mouseout', scheduleClose);

          marker.on('popupopen', function () {
            const popupEl = this.getPopup().getElement();
            popupEl.addEventListener('mouseenter', cancelClose);
            popupEl.addEventListener('mouseleave', scheduleClose);
          });

          instance.layer.addLayer(marker);
          points.push([event.lat, event.lng]);
        });

        instance.lastPoints = points;
        ctx.resetHidden = false;

        if (points.length) {
          setStatus('');
          fitToPoints();
        } else {
          instance.map.setView([ctx.lat, ctx.lng], ctx.zoom);
          setStatus('Ingen vandreture med placering matcher dine filtre.');
        }
      }

      async function load() {
        const current = ++requestId;
        setStatus('Indlæser kort…');

        try {
          const query = new URLSearchParams(readFilters()).toString();
          const response = await fetch(
            `${ctx.restUrl}${query ? `?${query}` : ''}`
          );
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          const events = await response.json();
          if (current !== requestId || cancelled) {
            return;
          }
          renderPins(events);
        } catch (err) {
          if (current !== requestId || cancelled) {
            return;
          }
          setStatus('Kunne ikke indlæse kortet. Prøv igen.');
        }
      }

      const onFiltersChange = () => {
        if (instance) {
          load();
        } else {
          // Leaflet is still loading; render fresh pins once it is ready
          // instead of the (now stale) server-embedded ones.
          pendingReload = true;
        }
      };
      document.addEventListener('vk:filters-change', onFiltersChange);

      // Re-measure when the map becomes visible. Leaflet initialised inside a
      // hidden tab has zero size, so on the Tabs block's `vk:tab-shown` event
      // the map re-measures and runs any fit that was deferred while it was
      // hidden. A ResizeObserver covers non-tab layout changes the same way.
      const remeasure = () => {
        if (!instance || canvas.offsetWidth === 0) {
          return;
        }
        instance.map.invalidateSize();
        if (needsFit) {
          fitToPoints();
        }
      };
      document.addEventListener('vk:tab-shown', remeasure);
      const observer =
        'ResizeObserver' in window
          ? new ResizeObserver(() => {
              if (needsFit) {
                remeasure();
              }
            })
          : null;
      if (observer) {
        observer.observe(canvas);
      }

      (async () => {
        try {
          await ensureLeaflet();
        } catch (e) {
          setStatus('Kortet kunne ikke indlæses.');
          return;
        }
        if (cancelled) {
          return;
        }

        const L = window.L;
        const map = L.map(canvas, { scrollWheelZoom: true }).setView(
          [ctx.lat, ctx.lng],
          ctx.zoom
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap',
          maxZoom: 18,
        }).addTo(map);

        const pinIcon = L.divIcon({
          className: 'vk-map__pin-wrap',
          html: '<span class="vk-map__pin"></span>',
          iconSize: [22, 22],
          iconAnchor: [11, 22],
          popupAnchor: [0, -20],
        });

        const layer = L.markerClusterGroup
          ? L.markerClusterGroup()
          : L.layerGroup();
        map.addLayer(layer);

        instance = { map, layer, pinIcon, lastPoints: [] };
        instances.set(root, instance);

        if (pendingReload) {
          load();
        } else {
          renderPins(ctx.locations);
        }
      })();

      return () => {
        cancelled = true;
        requestId++;
        document.removeEventListener('vk:filters-change', onFiltersChange);
        document.removeEventListener('vk:tab-shown', remeasure);
        if (observer) {
          observer.disconnect();
        }
        if (instance) {
          instance.map.remove();
          instance = null;
        }
        instances.delete(root);
      };
    },
  },
});
