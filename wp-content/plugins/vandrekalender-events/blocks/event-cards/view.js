/**
 * Event Cards — frontend view module (Interactivity API).
 *
 * The cards are fully server-rendered (see render.php). This module only
 * triggers re-renders through `@wordpress/interactivity-router`: "Vis flere"
 * bumps the `side` URL param, and a `vk:filters-change` event from the Event
 * Filters block re-navigates to the URL the filters just wrote. The router
 * fetches the page and swaps this block's router region, so the card markup
 * lives in one place — render.php.
 *
 * The filters listener is attached in `callbacks.init` rather than a
 * `data-wp-on-document--` directive because the directive parser rejects the
 * colon in the event name.
 */

import { store } from '@wordpress/interactivity';

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

const { state, actions } = store('vandrekalender/event-cards', {
  state: {
    isNavigating: false,
  },
  actions: {
    *loadMore() {
      const url = new URL(window.location);
      const current = parseInt(url.searchParams.get('side'), 10) || 1;
      url.searchParams.set('side', current + 1);
      yield* navigate(`${url.pathname}${url.search}`);
    },
    *refresh() {
      // The filters block writes the new filter state into the URL (without
      // a `side` param) before dispatching, so the current URL is the target.
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
