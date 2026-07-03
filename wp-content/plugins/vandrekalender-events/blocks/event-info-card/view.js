/**
 * Event Info Card — frontend behaviour.
 *
 * Clicking a route tab swaps the price/start-time/cutoff values shown below
 * it, reading them straight from the tab button's own data attributes (set
 * server-side). No fetch — every route's values are already in the markup.
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
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('[data-vk-info-card]')
    .forEach(root => initInfoCard(root));
});
