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

## Blocks

<!-- List of Gutenberg blocks, what each does, source location go here -->

**Event Cards** (`blocks/event-cards/`) — the filterable card grid. Fully server-rendered for SEO using the Interactivity API: `render.php` queries and renders all cards (cumulative pagination via the `side` URL param, 50 per page, reusing `Vandrekalender_Event_Rest_Api::build_query_args()`), and `view.js` is a `viewScriptModule` store that re-renders by navigating with `@wordpress/interactivity-router`, which swaps the block's `data-wp-router-region`. Reacts to `vk:filters-change` from the Event Filters block (listener attached in `callbacks.init` — the directive parser rejects the colon in the event name, so `data-wp-on-document--` can't be used). Requires the `--experimental-modules` flag on `wp-scripts build`/`start`, already set in `package.json`. New interactive blocks should follow this pattern (see paychex-wp for more examples).

**Event Calendar** (`blocks/event-calendar/`) — the month grid, same fully server-rendered pattern as Event Cards. `render.php` renders the grid (per-day counts via `Vandrekalender_Event_Rest_Api::count_events_by_day()`) plus the selected day's events; view state lives in the URL — `maaned` (YYYY-MM) picks the month, `dag` (YYYY-MM-DD) selects a day — and `view.js` only navigates through the router. Month/day buttons carry their target in plain `data-month`/`data-day` attributes, **not** `data-wp-context`: the router morphs reused elements in place and their context keeps its initial value, so a context payload goes stale after the first navigation. A filter change resets to the current month on purpose, so the concurrent Cards/Calendar router navigations hit the identical URL. The filter bar's date range is ignored — the calendar has its own month navigation.

**Event Map** (`blocks/event-map/`) — Leaflet needs the client, so this one only partially converts: a small controls-strip island whose `data-wp-context` carries the map config **and** the initial pin payload (`Vandrekalender_Event_Rest_Api::locations_payload()`), so first paint needs no REST call. `view.js` lazy-loads the Leaflet **scripts** from a CDN in `callbacks.init` and only refetches `GET /vandrekalender/v1/events/locations` on `vk:filters-change`. The camera never follows the pins — fixed Denmark view; filter changes only swap markers. Hard-won rules for coexisting with the router, all of which broke the map before:

- **Leaflet CSS is enqueued server-side in `render.php`, never injected from JS.** The router manages the `<head>` across client-side navigations, and JS-injected `<link>` tags come out of that morphing present in the DOM but no longer applied — tiles and controls silently lose all layout.
- **The map canvas lives outside the island** (plain sibling div) and the context is read-only: island re-renders would wipe or reset DOM the runtime didn't render. Status/reset visibility is mutated directly on the elements.
- **The map is never created against a hidden (zero-size) container** — cluster maths and tile layout inherit the broken geometry and nothing repairs it. `view.js` waits for the canvas to have real size (the Tabs block dispatches a bubbling `vk:tab-shown` on the activated panel; a slow poll is the fallback — ResizeObserver alone proved unreliable for the display-none→block flip).

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

<!-- Layout, map, routes tab switcher, sign-up CTA go here -->
