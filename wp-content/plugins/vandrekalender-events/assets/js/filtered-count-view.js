/**
 * Filtered events count — frontend script for the [vk_filtered_count]
 * shortcode.
 *
 * Hydrates every .vk-filtered-count element: fetches the matching-events
 * count from the REST API and re-fetches whenever the Event Filters block
 * broadcasts a `vk:filters-change` event. Filter state lives in the URL query
 * string, same as the Event Cards block.
 *
 * Served as-is with no build step — registered in vandrekalender-events.php
 * and enqueued by the shortcode.
 */

const numberFmt = new Intl.NumberFormat('da-DK');

/**
 * Read the recognised filter params from the current URL.
 *
 * @return {Object} Filter values keyed by REST param name.
 */
function readFilters() {
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
 * Initialise one count instance.
 *
 * @param {HTMLElement} root The block wrapper element.
 */
function initCount(root) {
  const restUrl = root.dataset.restUrl;
  let requestId = 0;

  async function load() {
    const current = ++requestId;
    const query = new URLSearchParams(readFilters()).toString();
    const url = query ? `${restUrl}?${query}` : restUrl;

    try {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();

      // Ignore stale responses if a newer request started meanwhile.
      if (current !== requestId) {
        return;
      }

      root.textContent = numberFmt.format(data.count);
    } catch (error) {
      // Keep the server-rendered number on failure.
    }
  }

  document.addEventListener('vk:filters-change', load);
  // Refresh once on load in case the page came from a cache with stale params.
  load();
}

document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('.vk-filtered-count')
    .forEach(root => initCount(root));
});
