/**
 * Event Info Card — the "Jeg kommer" CTA (Interactivity API).
 *
 * Only the sign-up CTA is interactive; the route tabs stay in the plain
 * `viewScript` next door (view.js), because they mutate values the runtime
 * never rendered.
 *
 * The button is server-rendered with its final label and the form posts to
 * admin-post.php, so both states work without JavaScript. This module upgrades
 * the logged-in case: joining becomes a REST call, and cancelling opens a
 * confirmation dialog first instead of going straight through.
 *
 * The logged-out submit is deliberately left alone — it has to reach the server
 * so the pending-join cookie is set before the login redirect. render.php omits
 * the submit directive entirely when nobody is logged in.
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const { state } = store('vandrekalender/event-info-card', {
  state: {
    get ctaLabel() {
      return getContext().attending ? state.attendingLabel : state.joinLabel;
    },
  },
  actions: {
    /**
     * Single submit handler for both directions: sign up, or ask before
     * cancelling. Which one depends on whether the visitor is already on the
     * list, exactly as the no-JS server handler decides it.
     */
    *submit(event) {
      event.preventDefault();

      const context = getContext();

      if (context.busy) {
        return;
      }

      if (context.attending) {
        context.confirming = true;
        return;
      }

      yield* request(context, 'POST', {
        attending: true,
        note: state.joinedNote,
      });
    },
    *confirmCancel() {
      const context = getContext();

      if (context.busy) {
        return;
      }

      yield* request(context, 'DELETE', { attending: false, note: '' });

      context.confirming = false;
    },
    dismissCancel() {
      getContext().confirming = false;
    },
  },
  callbacks: {
    /**
     * Drive the native <dialog> from context. showModal() is the only way to
     * get the modal backdrop and focus trap, and it cannot be expressed as an
     * attribute binding — the `open` attribute alone renders a non-modal box.
     */
    toggleDialog() {
      const { ref } = getElement();
      const { confirming } = getContext();

      if (confirming && !ref.open) {
        ref.showModal();
      } else if (!confirming && ref.open) {
        ref.close();
      }
    },
  },
});

/**
 * Call the join endpoint and apply the resulting state.
 *
 * @param {Object} context Block context to mutate.
 * @param {string} method  'POST' to join, 'DELETE' to cancel.
 * @param {Object} result  Context values to apply on success.
 */
function* request(context, method, result) {
  context.busy = true;
  context.error = '';

  try {
    const response = yield fetch(`${state.restUrl}${context.eventId}/join`, {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': state.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`Join request failed with status ${response.status}`);
    }

    yield response.json();

    Object.assign(context, result);
  } catch (error) {
    context.error = method === 'DELETE' ? state.cancelError : state.joinError;
  } finally {
    context.busy = false;
  }
}
