/**
 * Month Quick Filter — frontend view.
 *
 * Without JavaScript a chip is a plain link. With it, a click sets the
 * `date_from` / `date_to` URL params, keeps the other filters, drops `side`,
 * and dispatches `vk:filters-change` with all the filters, as Event Filters
 * does; the results blocks and the Event Filters date inputs follow. Clicking
 * the active chip clears both dates.
 */

const FILTER_KEYS = [
  'region',
  'organiser',
  'length',
  'date_from',
  'date_to',
  'is_free',
];

/**
 * Mark the chip whose month is exactly the URL's date range.
 *
 * @param {HTMLElement} root The block element.
 */
function markActive(root) {
  const params = new URLSearchParams(window.location.search);
  root.querySelectorAll('.vk-months__chip').forEach(chip => {
    const active =
      params.get('date_from') === chip.dataset.from &&
      params.get('date_to') === chip.dataset.to;
    if (active) {
      chip.setAttribute('aria-current', 'true');
    } else {
      chip.removeAttribute('aria-current');
    }
  });
}

/**
 * Write the filters to the URL and tell the other blocks.
 *
 * @param {Object} filters Filter values keyed by REST param name.
 */
function publish(filters) {
  const query = new URLSearchParams(filters).toString();
  window.history.replaceState(
    {},
    '',
    query ? `${window.location.pathname}?${query}` : window.location.pathname
  );
  document.dispatchEvent(
    new CustomEvent('vk:filters-change', { detail: filters })
  );
}

/**
 * Initialise one chip row.
 *
 * @param {HTMLElement} root The block element.
 */
function init(root) {
  root.addEventListener('click', event => {
    const chip = event.target.closest('.vk-months__chip');
    if (!chip || event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();

    const params = new URLSearchParams(window.location.search);
    const filters = {};
    FILTER_KEYS.forEach(key => {
      if (params.get(key)) {
        filters[key] = params.get(key);
      }
    });

    if (chip.getAttribute('aria-current') === 'true') {
      delete filters.date_from;
      delete filters.date_to;
    } else {
      filters.date_from = chip.dataset.from;
      filters.date_to = chip.dataset.to;
    }

    publish(filters);
  });

  // Also follows the Event Filters date inputs and its reset button.
  document.addEventListener('vk:filters-change', () => markActive(root));
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.vk-months').forEach(init);
});
