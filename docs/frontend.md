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

**Event Cards** (`blocks/event-cards/`) — the filterable card grid. Fully server-rendered for SEO using the Interactivity API: `render.php` queries and renders all cards (cumulative pagination via the `side` URL param, 50 per page, reusing `Vandrekalender_Event_Rest_Api::build_query_args()`), and `view.js` is a `viewScriptModule` store that re-renders by navigating with `@wordpress/interactivity-router`, which swaps the block's `data-wp-router-region`. Reacts to `vk:filters-change` from the Event Filters block (listener attached in `callbacks.init` — the directive parser rejects the colon in the event name, so `data-wp-on-document--` can't be used). Requires the `--experimental-modules` flag on `wp-scripts build`/`start`, already set in `package.json`. New interactive blocks should follow this pattern (see paychex-wp for more examples); the map and calendar blocks are older vanilla-JS fetch-based views.

**Event count shortcodes** (registered in `vandrekalender-events.php`, for use inside paragraph text). Both render an inline `<span>` that inherits the surrounding text colour and size:

- `[vk_upcoming_count]` — number of upcoming events (event date ≥ today). Static — never reacts to the filter bar. Used on the cover hero.
- `[vk_filtered_count]` — number of events matching the active filters. Server-renders the initial count from the URL params, then updates live via `GET /vandrekalender/v1/events/count` whenever the Event Filters block broadcasts `vk:filters-change` (enqueues `assets/js/filtered-count-view.js`).

---

## Page Templates

<!-- FSE templates: front-page, calendar page, single event, etc. go here -->

---

## Filter Bar

<!-- Filter fields, how they map to REST API params, URL state go here -->

---

## Event Card

<!-- What's shown on the card: title, date, distance, difficulty badge, location go here -->

---

## Single Event Page

<!-- Layout, map, routes tab switcher, sign-up CTA go here -->
