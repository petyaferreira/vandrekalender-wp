/**
 * Event Calendar — month grid view.
 *
 * The grid only needs per-day counts, so each visible month is fetched from
 * the lightweight /events/days endpoint (cached per month; navigating to an
 * unseen month fetches it). The full event payloads are only fetched when a
 * day is clicked — one small /events request for that single date. This keeps
 * the calendar fast no matter how many events exist.
 *
 * Region / length / free filters are honoured (a filter change clears the
 * caches and refetches). The bar's date range is ignored here on purpose,
 * because this view has its own month navigation.
 */

const WEEKDAYS = ['Man', 'Tir', 'Ons', 'Tor', 'Fre', 'Lør', 'Søn'];

const monthFmt = new Intl.DateTimeFormat('da-DK', {
  month: 'long',
  year: 'numeric',
});
const longDateFmt = new Intl.DateTimeFormat('da-DK', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});

/**
 * Read region/length/free filters from the URL.
 *
 * @return {Object} Filter values keyed by REST param name.
 */
function readFilters() {
  const params = new URLSearchParams(window.location.search);
  const filters = {};
  ['region', 'length', 'is_free'].forEach(key => {
    const value = params.get(key);
    if (value) {
      filters[key] = value;
    }
  });
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
function key(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

/**
 * Price label for an event.
 *
 * @param {Object} event Event payload.
 * @return {string} Danish price label.
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
 * Fetch a URL and parse the JSON response, throwing on HTTP errors.
 *
 * @param {string} url Request URL.
 * @return {Promise<*>} Parsed JSON.
 */
async function fetchJson(url) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  return response.json();
}

/**
 * Initialise one calendar instance.
 *
 * @param {HTMLElement} root The block wrapper element.
 */
function initCalendar(root) {
  const restUrl = root.dataset.restUrl;
  const daysUrl = `${restUrl}/days`;
  const status = root.querySelector('.vk-calendar__status');
  const inner = root.querySelector('.vk-calendar__inner');
  const error = root.querySelector('.vk-calendar__error');

  const today = new Date();
  const todayKey = key(today.getFullYear(), today.getMonth(), today.getDate());

  let countsByDate = {}; // date key -> event count, merged across fetched months.
  let loadedMonths = new Set(); // "YYYY-MM" keys already fetched.
  let dayCache = {}; // date key -> events array for clicked days.
  let viewYear = today.getFullYear();
  let viewMonth = today.getMonth();
  let selectedKey = null;
  let dayLoading = false;
  let requestId = 0;

  function render() {
    inner.innerHTML = '';

    // Header with month label and navigation.
    const header = document.createElement('div');
    header.className = 'vk-calendar__header';
    const label = monthFmt.format(new Date(viewYear, viewMonth, 1));
    header.innerHTML = `
			<button type="button" class="vk-calendar__nav" data-dir="-1" aria-label="Forrige måned">‹</button>
			<h3 class="vk-calendar__month">${label.charAt(0).toUpperCase() + label.slice(1)}</h3>
			<button type="button" class="vk-calendar__nav" data-dir="1" aria-label="Næste måned">›</button>
		`;
    inner.appendChild(header);

    // Grid.
    const grid = document.createElement('div');
    grid.className = 'vk-calendar__grid';

    WEEKDAYS.forEach(name => {
      const head = document.createElement('div');
      head.className = 'vk-calendar__weekday';
      head.textContent = name;
      grid.appendChild(head);
    });

    const firstOffset = (new Date(viewYear, viewMonth, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const totalCells = Math.ceil((firstOffset + daysInMonth) / 7) * 7;

    for (let cell = 0; cell < totalCells; cell++) {
      const dayNum = cell - firstOffset + 1;
      const inMonth = dayNum >= 1 && dayNum <= daysInMonth;
      const day = document.createElement(inMonth ? 'button' : 'div');
      day.className = 'vk-calendar__day';

      if (!inMonth) {
        day.classList.add('is-other-month');
        grid.appendChild(day);
        continue;
      }

      const dateKey = key(viewYear, viewMonth, dayNum);
      const count = countsByDate[dateKey] || 0;
      day.type = 'button';
      day.dataset.dateKey = dateKey;

      if (dateKey === todayKey) {
        day.classList.add('is-today');
      }
      if (dateKey === selectedKey) {
        day.classList.add('is-selected');
      }

      let dot = '';
      if (count) {
        const size =
          count <= 2
            ? ' vk-calendar__dot--sm'
            : count > 6
              ? ' vk-calendar__dot--lg'
              : '';
        dot = `<span class="vk-calendar__dot${size}">${count}</span>`;
        day.classList.add('has-events');
      } else {
        day.disabled = true;
      }

      day.innerHTML = `<span class="vk-calendar__num">${dayNum}</span>${dot}`;
      grid.appendChild(day);
    }

    inner.appendChild(grid);

    // Selected day's events.
    const dayEvents = document.createElement('div');
    dayEvents.className = 'vk-calendar__day-events';
    if (selectedKey && dayLoading) {
      dayEvents.innerHTML = `<p class="vk-calendar__day-label">${longDateFmt.format(new Date(selectedKey))}…</p>`;
    } else if (selectedKey && dayCache[selectedKey]) {
      const events = dayCache[selectedKey];
      const heading = longDateFmt.format(new Date(selectedKey));
      const rows = events
        .map(event => {
          const dist = (event.distances_km || []).length
            ? `<span class="vk-calendar__event-dist">${event.distances_km.sort((a, b) => a - b).join(', ')} km</span>`
            : '';
          const price = priceLabel(event);
          return `<a class="vk-calendar__event" href="${event.permalink}">
						<span class="vk-calendar__event-title">${event.title}</span>
						${dist}
						${price ? `<span class="vk-calendar__event-price">${price}</span>` : ''}
					</a>`;
        })
        .join('');
      dayEvents.innerHTML = `<p class="vk-calendar__day-label">${heading} — ${events.length} vandreture</p>${rows}`;
    }
    inner.appendChild(dayEvents);

    // Wire navigation and day selection.
    header.querySelectorAll('.vk-calendar__nav').forEach(btn => {
      btn.addEventListener('click', () => {
        viewMonth += Number(btn.dataset.dir);
        if (viewMonth < 0) {
          viewMonth = 11;
          viewYear--;
        } else if (viewMonth > 11) {
          viewMonth = 0;
          viewYear++;
        }
        selectedKey = null;
        showMonth();
      });
    });

    grid.querySelectorAll('.vk-calendar__day.has-events').forEach(day => {
      day.addEventListener('click', () => selectDay(day.dataset.dateKey));
    });
  }

  /**
   * Render the current month, fetching its counts first if unseen.
   */
  async function showMonth() {
    const monthKey = `${viewYear}-${String(viewMonth + 1).padStart(2, '0')}`;
    if (loadedMonths.has(monthKey)) {
      render();
      return;
    }

    const current = ++requestId;
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    const query = new URLSearchParams({
      ...readFilters(),
      date_from: key(viewYear, viewMonth, 1),
      date_to: key(viewYear, viewMonth, daysInMonth),
    }).toString();

    status.hidden = false;
    error.hidden = true;

    try {
      const counts = await fetchJson(`${daysUrl}?${query}`);
      if (current !== requestId) {
        return;
      }

      Object.assign(countsByDate, counts || {});
      loadedMonths.add(monthKey);

      status.hidden = true;
      inner.hidden = false;
      render();
    } catch (err) {
      if (current !== requestId) {
        return;
      }
      status.hidden = true;
      inner.hidden = true;
      error.hidden = false;
    }
  }

  /**
   * Toggle a day selection, fetching that day's events on first click.
   *
   * @param {string} dateKey The clicked day (YYYY-MM-DD).
   */
  async function selectDay(dateKey) {
    if (selectedKey === dateKey) {
      selectedKey = null;
      render();
      return;
    }

    selectedKey = dateKey;

    if (!dayCache[dateKey]) {
      dayLoading = true;
      render();

      const query = new URLSearchParams({
        ...readFilters(),
        date_from: dateKey,
        date_to: dateKey,
        per_page: 100,
      }).toString();

      try {
        const events = await fetchJson(`${restUrl}?${query}`);
        dayCache[dateKey] = Array.isArray(events) ? events : [];
      } catch (err) {
        dayCache[dateKey] = [];
      }
      dayLoading = false;

      // The user may have clicked elsewhere while this was in flight.
      if (selectedKey !== dateKey) {
        return;
      }
    }

    render();
  }

  /**
   * Throw away everything fetched and reload the visible month.
   */
  function reload() {
    countsByDate = {};
    loadedMonths = new Set();
    dayCache = {};
    selectedKey = null;
    showMonth();
  }

  document.addEventListener('vk:filters-change', reload);
  showMonth();
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.vk-calendar').forEach(root => initCalendar(root));
});
