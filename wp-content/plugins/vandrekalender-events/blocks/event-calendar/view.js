/**
 * Event Calendar — frontend view module (Interactivity API).
 *
 * The month grid and the selected day's events are fully server-rendered (see
 * render.php). This module only triggers re-renders through
 * `@wordpress/interactivity-router`: month navigation writes the `maaned` URL
 * param, clicking a day toggles the `dag` param, and a `vk:filters-change`
 * event from the Event Filters block re-navigates with the new filters. The
 * router fetches the page and swaps this block's router region, so the
 * calendar markup lives in one place — render.php.
 *
 * The filters listener is attached in `callbacks.init` rather than a
 * `data-wp-on-document--` directive because the directive parser rejects the
 * colon in the event name.
 */

import { store, getElement } from '@wordpress/interactivity';

/**
 * Fetch and morph a URL's router region into the page.
 *
 * @param {string} url Path plus query string to navigate to.
 */
function* navigate(url) {
  if (state.isNavigating) {
    return;
  }
  state.isNavigating = true;
  try {
    const { actions } = yield import('@wordpress/interactivity-router');
    yield actions.navigate(url);
  } finally {
    state.isNavigating = false;
  }
}

const { state, actions } = store('vandrekalender/event-calendar', {
  state: {
    isNavigating: false,
  },
  actions: {
    // The clicked button carries its payload in a plain data attribute (not
    // data-wp-context): the router morphs reused elements in place and their
    // context keeps its initial value, so context would go stale after the
    // first navigation.
    *goToMonth() {
      const { month } = getElement().ref.dataset;
      const url = new URL(window.location);
      url.searchParams.set('maaned', month);
      url.searchParams.delete('dag');
      yield* navigate(`${url.pathname}${url.search}`);
    },
    *selectDay() {
      const { day } = getElement().ref.dataset;
      const url = new URL(window.location);
      if (url.searchParams.get('dag') === day) {
        url.searchParams.delete('dag');
      } else {
        url.searchParams.set('dag', day);
      }
      yield* navigate(`${url.pathname}${url.search}`);
    },
    *refresh() {
      // The filters block writes the new filter state into the URL (dropping
      // `maaned` and `dag`) before dispatching, so this resets the calendar
      // to the current month — deliberately the same URL the Event Cards
      // block navigates to, so the two concurrent navigations cannot land on
      // different pages.
      yield* navigate(`${window.location.pathname}${window.location.search}`);
    },
  },
  callbacks: {
    init() {
      const onFiltersChange = () => actions.refresh();
      document.addEventListener('vk:filters-change', onFiltersChange);
      return () =>
        document.removeEventListener('vk:filters-change', onFiltersChange);
    },
  },
});
