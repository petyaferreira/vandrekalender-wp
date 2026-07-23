# Frontend

> Source of truth for blocks, templates, filters, and the calendar UI.

**Status: stub — to be filled in when reviewing Vandrekalender_HomepageStructure_v2.docx and Vandrekalender_Sitemap_v4.docx**

---

## Where frontend code lives (plugin)

One rule: **code that needs compiling goes through the build; static files ship as-is.**

| Folder | What | Build? |
|---|---|---|
| `blocks/` | Gutenberg block sources (block.json, edit.js, view.js, style.scss) | Yes → `build/blocks/`, registered from there |
| `resources/` | Non-block editor scripts that import `@wordpress/*` packages (e.g. event-meta-fields sidebar) | Yes → `build/resources/` |
| `assets/` | Plain dependency-free JS/CSS served directly (e.g. `assets/js/filtered-count-view.js`) | No — enqueued as-is, committed to git |
| `build/` | Compiled output. Gitignored — CI builds it on deploy. Never edit by hand | — |

If a file in `assets/` ever starts importing `@wordpress/*` packages or needs JSX, move it into `resources/` and wire it into `build:resources`.

---

## Accessibility conventions

**Tap targets: every standalone interactive control is at least 44px tall** (nav links, buttons, tabs, icon buttons, form inputs, standalone links). WCAG 2.2 AA only requires 24×24, but 44px is the house standard. Use `min-height: 44px` on the clickable element itself (with `display: inline-flex; align-items: center` for text links) rather than em-based padding — min-height holds at any font size. Links inside running text (paragraphs) are exempt per WCAG and must NOT get a min-height. Existing examples: `.header-login-button a` and the navigation rules in the theme's `screen.scss`, `__navigation-item` in the tabs block, `__nav` in the event-calendar block.

---

## Blocks

<!-- List of Gutenberg blocks, what each does, source location go here -->

**Event Cards** (`blocks/event-cards/`) — the filterable card grid. Fully server-rendered for SEO using the Interactivity API: `render.php` queries and renders all cards (cumulative pagination via the `side` URL param, 50 per page, reusing `Vandrekalender_Event_Rest_Api::build_query_args()`), and `view.js` is a `viewScriptModule` store that re-renders by navigating with `@wordpress/interactivity-router`, which swaps the block's `data-wp-router-region`. Reacts to `vk:filters-change` from the Event Filters block (listener attached in `callbacks.init` — the directive parser rejects the colon in the event name, so `data-wp-on-document--` can't be used). Requires the `--experimental-modules` flag on `wp-scripts build`/`start`, already set in `package.json`. New interactive blocks should follow this pattern (see paychex-wp for more examples).

**Event Calendar** (`blocks/event-calendar/`) — the month grid, same fully server-rendered pattern as Event Cards. `render.php` renders the grid (per-day counts via `Vandrekalender_Event_Rest_Api::count_events_by_day()`) plus the selected day's events; view state lives in the URL — `maaned` (YYYY-MM) picks the month, `dag` (YYYY-MM-DD) selects a day — and `view.js` only navigates through the router. Month/day buttons carry their target in plain `data-month`/`data-day` attributes, **not** `data-wp-context`: the router morphs reused elements in place and their context keeps its initial value, so a context payload goes stale after the first navigation. A filter change resets to the current month on purpose, so the concurrent Cards/Calendar router navigations hit the identical URL. The filter bar's date range is ignored — the calendar has its own month navigation.

**Event Map** (`blocks/event-map/`) — Leaflet needs the client, so this one only partially converts: a small controls-strip island whose `data-wp-context` carries the map config **and** the initial pin payload (`Vandrekalender_Event_Rest_Api::locations_payload()`), so first paint needs no REST call. `view.js` lazy-loads the Leaflet **scripts** from a CDN in `callbacks.init` and only refetches `GET /vandrekalender/v1/events/locations` on `vk:filters-change`. The camera never follows the pins — fixed Denmark view; filter changes only swap markers. Hard-won rules for coexisting with the router, all of which broke the map before:

- **Leaflet CSS is enqueued server-side in `render.php`, never injected from JS.** The router manages the `<head>` across client-side navigations, and JS-injected `<link>` tags come out of that morphing present in the DOM but no longer applied — tiles and controls silently lose all layout.
- **The map canvas lives outside the island** (plain sibling div) and the context is read-only: island re-renders would wipe or reset DOM the runtime didn't render. Status/reset visibility is mutated directly on the elements.
- **The map is never created against a hidden (zero-size) container** — cluster maths and tile layout inherit the broken geometry and nothing repairs it. `view.js` waits for the canvas to have real size (the Tabs block dispatches a bubbling `vk:tab-shown` on the activated panel; a slow poll is the fallback — ResizeObserver alone proved unreliable for the display-none→block flip).

**Event Info Card** (`blocks/event-info-card/`) — the sticky summary in the single event sidebar. Two scripts on purpose: `view.js` is a plain `viewScript` for the route tabs (they swap values the runtime never rendered), and `join.js` is the `viewScriptModule` holding the "Jeg kommer" CTA (Interactivity API).

The bottom CTA slot holds exactly one of two things, decided server-side:

- `Vandrekalender_Event_Attendees::is_joinable()` → the sign-up button. Server-rendered with its final label, so first paint is right: "Jeg kommer" in forest, or "Deltager" in meadow (the lighter green) when the visitor is already signed up.
- Otherwise, the original "Book din plads" link out to `event_source_url`/`event_organiser_url`.

The card normally returns early when the event has no routes; a joinable event skips that early return, since the card is now the only place the button can live. The price and start/cutoff rows are guarded individually instead.

**How the button behaves.** It is a real `<form>` posting to `admin-post.php`, and the server handler is a **toggle** — it joins when you are not attending and cancels when you are — so both directions work with JavaScript off. `data-wp-on--submit` is attached **only when a user is logged in**: a logged-out submit has to reach the server so the pending-join cookie is set before the login redirect (see `docs/authentication.md`). With JavaScript, joining is a `POST` to `/vandrekalender/v1/events/{id}/join`, and cancelling first opens a confirmation before the `DELETE` — so the destructive direction is never one stray click. Per-block values (event ID, attending, busy, confirming, note) live in `data-wp-context`; translated labels and the REST URL/nonce are global `wp_interactivity_state()`.

The confirmation is a native `<dialog>` driven from `callbacks.toggleDialog`, which calls `showModal()`/`close()` in a `data-wp-watch` on `context.confirming`. `showModal()` is the only way to get the backdrop, focus trap and Escape handling, and it cannot be expressed as an attribute binding — the bare `open` attribute renders a non-modal box. The top layer also means the card's `position: sticky` cannot clip it.

**Event count shortcodes** (registered in `vandrekalender-events.php`, for use inside paragraph text). Both render an inline `<span>` that inherits the surrounding text colour and size:

- `[vk_upcoming_count]` — number of upcoming events (event date ≥ today). Static — never reacts to the filter bar. Used on the cover hero.
- `[vk_filtered_count]` — number of events matching the active filters. Server-renders the initial count from the URL params, then updates live via `GET /vandrekalender/v1/events/count` whenever the Event Filters block broadcasts `vk:filters-change` (enqueues `assets/js/filtered-count-view.js`).

---

## Page Templates

All page templates share one skeleton — header part → `<main>` → footer part — and are **top-flush**: `<main>` sets `margin-top:0`, so the first content block sits directly under the header. Gaps between sections come from the root `blockGap` (`medium`) in theme.json; templates only ever *remove* structural spacing, never add it (the one exception: the with-title template adds a `medium` top padding above the title).

| Template | Use case |
|---|---|
| `page.html` (default) | Pages without a rendered title. Also the hero template: make the first content block a full-width Cover and it sits flush against the header. |
| `page-with-title.html` | Standard content pages (contact, about, …). Renders the post title with a deliberate `medium` space above. |
| `page-without-gaps.html` | Full-bleed pages: zero `blockGap` between content blocks and a flush footer. |
| `single-event.html` | Single event view. |
| `index.html`, `taxonomy.html` | Archive fallbacks. |

There is deliberately no `front-page.html` — the front page uses `page.html` like any other page.

---

## Filter Bar

<!-- Filter fields, how they map to REST API params, URL state go here -->

---

## Event Card

<!-- What's shown on the card: title, date, distance, difficulty badge, location go here -->

---

## Single Event Page

<!-- Layout, map, routes tab switcher go here -->

**Sign-up CTA.** One button in one place — the bottom of the Event Info Card. An event created on vandrekalender.dk gets "Jeg kommer" there; a scraped event gets "Book din plads" in the identical slot. See the Event Info Card block above.

> `single-event.html` also exists as a **user customisation in the database** (`wp_template` post `single-event`). Editing the theme file alone changes nothing on a site that has one — the DB copy wins. Add new blocks in the Site Editor, or update both.
