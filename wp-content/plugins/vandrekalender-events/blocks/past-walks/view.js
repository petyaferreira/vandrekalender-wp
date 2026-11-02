/**
 * Past Walks — frontend view module (Interactivity API).
 *
 * The list is server-rendered (see render.php); this module only re-renders
 * it through `@wordpress/interactivity-router`, as Event Cards does. "Vis
 * flere ture" is a link to ?side=N+1: with JavaScript it navigates in place
 * instead of loading a new page. A `vk:filters-change` event from the Event
 * Filters block re-navigates to the URL the filters just wrote (which has no
 * `side`, so the list starts again at the first PER_PAGE walks).
 *
 * The filters listener is attached in `callbacks.init`, because the directive
 * parser rejects the colon in the event name.
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

const { state, actions } = store('vandrekalender/past-walks', {
  state: {
    isNavigating: false,
  },
  actions: {
    *loadMore(event) {
      event.preventDefault();
      const url = new URL(getElement().ref.href);
      yield* navigate(`${url.pathname}${url.search}`);
    },
    *refresh() {
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
