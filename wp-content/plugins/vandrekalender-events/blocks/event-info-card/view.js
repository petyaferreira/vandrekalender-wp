/**
 * Event Info Card — frontend behaviour.
 *
 * Clicking a route tab swaps the price/start-time/cutoff/GPX values shown
 * below it, reading them straight from the tab button's own data attributes
 * (set server-side). No fetch — every route's values are already in the
 * markup. Also dispatches `vk:route-change` so the Event Route Map block
 * (a separate, independent block) can highlight the matching track.
 */

/**
 * Initialise one info card instance.
 *
 * @param {HTMLElement} root The [data-vk-info-card] element.
 */
function initInfoCard(root) {
  const tabs = Array.from(root.querySelectorAll('.vk-info-card__tab'));

  if (!tabs.length) {
    return;
  }

  const price = root.querySelector('[data-vk-info-field="price"]');
  const startTime = root.querySelector('[data-vk-info-field="start-time"]');
  const cutoff = root.querySelector('[data-vk-info-field="cutoff"]');
  const gpxRow = root.querySelector('[data-vk-gpx-row]');
  const gpxLink = root.querySelector('[data-vk-info-field="gpx"]');

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => {
        t.classList.toggle('vk-info-card__tab--active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });

      if (price) {
        price.textContent = tab.dataset.vkPrice;
      }
      if (startTime) {
        startTime.textContent = tab.dataset.vkStartTime;
      }
      if (cutoff) {
        cutoff.textContent = tab.dataset.vkCutoff;
      }

      const gpxUrl = tab.dataset.vkGpxUrl || '';
      if (gpxLink) {
        gpxLink.setAttribute('href', gpxUrl);
      }
      if (gpxRow) {
        gpxRow.hidden = !gpxUrl;
      }

      document.dispatchEvent(
        new CustomEvent('vk:route-change', {
          detail: { id: tab.dataset.vkRouteId },
        })
      );
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('[data-vk-info-card]')
    .forEach(root => initInfoCard(root));
});
