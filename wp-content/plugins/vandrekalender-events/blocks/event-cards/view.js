/**
 * Event Cards — frontend view.
 *
 * Fetches events from the Vandrekalender REST API one page at a time and
 * renders them as cards. A "Vis flere" button appends the next page while the
 * API's X-WP-TotalPages header says more remain. Re-fetches from page 1
 * whenever the Event Filters block broadcasts a `vk:filters-change` event.
 * Filter state lives in the URL query string so it survives reloads and
 * shared links.
 */

const dateFmt = new Intl.DateTimeFormat('da-DK', {
  weekday: 'short',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
});

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
 * Format the price summary for an event.
 *
 * @param {Object} event Event payload from the REST API.
 * @return {string} Human-readable price label in Danish.
 */
function priceLabel(event) {
  if (event.is_free) {
    return 'Gratis';
  }
  if (event.price_from === null || event.price_from === undefined) {
    return '';
  }
  return `fra ${Math.round(event.price_from)} kr`;
}

/**
 * Build a single event card element.
 *
 * @param {Object} event Event payload from the REST API.
 * @return {HTMLElement} The card list item.
 */
function renderCard(event) {
  const li = document.createElement('li');
  li.className = 'vk-card';

  const link = document.createElement('a');
  link.className = 'vk-card__link';
  link.href = event.permalink;

  const img = document.createElement('div');
  img.className = 'vk-card__image';
  if (event.featured_image_url) {
    img.style.backgroundImage = `url(${event.featured_image_url})`;
  }
  link.appendChild(img);

  let date = '';
  if (event.date) {
    const parsed = new Date(event.date);
    date = isNaN(parsed) ? event.date : dateFmt.format(parsed);
  }

  const place = [event.place_name, event.municipality]
    .filter(Boolean)
    .join(', ');

  const distances = (event.distances_km || []).length
    ? `${event.distances_km.sort((a, b) => a - b).join(', ')} km`
    : '';

  const region = (event.taxonomies?.region || [])[0] || '';
  const price = priceLabel(event);

  const body = document.createElement('div');
  body.className = 'vk-card__body';
  body.innerHTML = `
		${date ? `<span class="vk-card__date">${date}</span>` : ''}
		<span class="vk-card__title">${event.title}</span>
		${place ? `<span class="vk-card__place">${place}</span>` : ''}
		<span class="vk-card__meta">
			${distances ? `<span class="vk-card__distance">${distances}</span>` : ''}
			${price ? `<span class="vk-card__price">${price}</span>` : ''}
			${region ? `<span class="vk-card__region">${region}</span>` : ''}
		</span>
	`;
  link.appendChild(body);

  li.appendChild(link);
  return li;
}

/**
 * Initialise one cards instance.
 *
 * @param {HTMLElement} root The block wrapper element.
 */
function initCards(root) {
  const restUrl = root.dataset.restUrl;
  const status = root.querySelector('.vk-cards__status');
  const list = root.querySelector('.vk-cards__list');
  const more = root.querySelector('.vk-cards__more');
  const empty = root.querySelector('.vk-cards__empty');
  let page = 1;
  let totalPages = 1;
  let requestId = 0;

  /**
   * Fetch one page and append its cards. Page 1 replaces the list.
   *
   * @param {number} pageToLoad 1-based page number.
   */
  async function load(pageToLoad) {
    const current = ++requestId;
    const query = new URLSearchParams({
      ...readFilters(),
      page: pageToLoad,
    }).toString();

    status.hidden = false;
    status.textContent = 'Indlæser vandreture…';
    empty.hidden = true;
    more.disabled = true;

    try {
      const response = await fetch(`${restUrl}?${query}`);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const events = await response.json();

      // Ignore stale responses if a newer request started meanwhile.
      if (current !== requestId) {
        return;
      }

      page = pageToLoad;
      totalPages = parseInt(response.headers.get('X-WP-TotalPages'), 10) || 1;

      if (pageToLoad === 1) {
        list.innerHTML = '';
      }

      if (pageToLoad === 1 && (!Array.isArray(events) || events.length === 0)) {
        list.hidden = true;
        status.hidden = true;
        more.hidden = true;
        empty.hidden = false;
        return;
      }

      const fragment = document.createDocumentFragment();
      (Array.isArray(events) ? events : []).forEach(event =>
        fragment.appendChild(renderCard(event))
      );
      list.appendChild(fragment);
      list.hidden = false;
      status.hidden = true;
      more.hidden = page >= totalPages;
      more.disabled = false;
    } catch (error) {
      if (current !== requestId) {
        return;
      }
      status.hidden = false;
      status.textContent = 'Kunne ikke indlæse vandreture. Prøv igen.';
      more.disabled = false;
      if (pageToLoad === 1) {
        list.hidden = true;
        more.hidden = true;
        empty.hidden = true;
      }
    }
  }

  more.addEventListener('click', () => load(page + 1));
  document.addEventListener('vk:filters-change', () => load(1));
  load(1);
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.vk-cards').forEach(root => initCards(root));
});
