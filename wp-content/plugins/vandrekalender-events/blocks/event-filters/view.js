/**
 * Event Filters — frontend view.
 *
 * Reads the filter controls, syncs them to the URL query string, and broadcasts
 * a `vk:filters-change` event that the Event Calendar block listens for. The URL
 * is the single source of truth, so reloads and shared links keep their state.
 */

/**
 * Collect the active filter values from one filters form.
 *
 * @param {HTMLElement} form The filters form element.
 * @return {Object} Filter values keyed by REST param name.
 */
function collect(form) {
  const filters = {};

  const region = form.querySelector('[data-filter="region"]');
  if (region && region.value) {
    filters.region = region.value;
  }

  const lengths = Array.from(
    form.querySelectorAll('[data-filter="length"][aria-pressed="true"]')
  ).map(pill => pill.dataset.value);
  if (lengths.length) {
    filters.length = lengths.join(',');
  }

  const from = form.querySelector('[data-filter="date_from"]');
  if (from && from.value) {
    filters.date_from = from.value;
  }

  const to = form.querySelector('[data-filter="date_to"]');
  if (to && to.value) {
    filters.date_to = to.value;
  }

  const free = form.querySelector('[data-filter="is_free"]');
  if (free && free.checked) {
    filters.is_free = 'true';
  }

  return filters;
}

/**
 * Push filter values into the URL and notify the calendar.
 *
 * @param {Object} filters Filter values keyed by REST param name.
 */
function publish(filters) {
  const params = new URLSearchParams(filters);
  const query = params.toString();
  const url = query
    ? `${window.location.pathname}?${query}`
    : window.location.pathname;
  window.history.replaceState({}, '', url);

  document.dispatchEvent(
    new CustomEvent('vk:filters-change', { detail: filters })
  );
}

/**
 * Apply the current URL params to the controls so the UI matches the link.
 *
 * @param {HTMLElement} form The filters form element.
 */
function hydrateFromUrl(form) {
  const params = new URLSearchParams(window.location.search);

  const region = form.querySelector('[data-filter="region"]');
  if (region && params.get('region')) {
    region.value = params.get('region');
  }

  const lengths = (params.get('length') || '').split(',').filter(Boolean);
  form.querySelectorAll('[data-filter="length"]').forEach(pill => {
    pill.setAttribute(
      'aria-pressed',
      lengths.includes(pill.dataset.value) ? 'true' : 'false'
    );
  });

  const from = form.querySelector('[data-filter="date_from"]');
  if (from && params.get('date_from')) {
    from.value = params.get('date_from');
  }

  const to = form.querySelector('[data-filter="date_to"]');
  if (to && params.get('date_to')) {
    to.value = params.get('date_to');
  }

  const free = form.querySelector('[data-filter="is_free"]');
  if (free) {
    free.checked = params.get('is_free') === 'true';
  }
}

/**
 * Initialise one filters form.
 *
 * @param {HTMLElement} form The filters form element.
 */
function initFilters(form) {
  hydrateFromUrl(form);

  form.addEventListener('change', () => publish(collect(form)));

  form.querySelectorAll('[data-filter="length"]').forEach(pill => {
    pill.addEventListener('click', () => {
      const pressed = pill.getAttribute('aria-pressed') === 'true';
      pill.setAttribute('aria-pressed', pressed ? 'false' : 'true');
      publish(collect(form));
    });
  });

  const reset = form.querySelector('[data-filter-reset]');
  if (reset) {
    reset.addEventListener('click', () => {
      form.reset();
      form
        .querySelectorAll('[data-filter="length"]')
        .forEach(pill => pill.setAttribute('aria-pressed', 'false'));
      publish({});
    });
  }

  // Avoid full-page submits if the form is ever wrapped in a submit context.
  form.addEventListener('submit', event => event.preventDefault());
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.vk-filters').forEach(form => initFilters(form));
});
