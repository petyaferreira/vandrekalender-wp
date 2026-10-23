# Frontend

> Source of truth for blocks, templates, filters, and the calendar UI.

Design references: [design/](design/index.md).

---

## Where frontend code lives (plugin)

One rule: **our own JS and CSS goes through the build.** Its version is then the build's content hash (`index.asset.php`), so a changed file gets a new URL after a deploy and browsers don't keep a cached old copy.

| Folder | What | Build? |
|---|---|---|
| `blocks/` | Gutenberg block sources (block.json, edit.js, view.js, style.scss) | Yes → `build/blocks/`, registered from there |
| `resources/` | Non-block scripts: the editor sidebars (event-meta-fields, organiser-fields) and the `[vk_filtered_count]` frontend script (filtered-count). Each one is an entry in `build:resources` and `start:resources` in `package.json` | Yes → `build/resources/`, registered from there with the version in its `index.asset.php` |
| `assets/` | Third-party libraries (`assets/vendor/swiper/`) and media (`assets/video/`), served as-is | No. A library is registered with its own version number (Swiper `'12.0.3'`); change it when the library is upgraded |
| `build/` | Compiled output. Gitignored — CI builds it on deploy. Never edit by hand | — |

A new script of our own goes in `blocks/` or `resources/`, never in `assets/` ([decision 0009](decisions/0009-assets-versioned-by-build.md)).

---

## Accessibility conventions

**Tap targets: every standalone interactive control is at least 44px tall** (nav links, buttons, tabs, icon buttons, form inputs, standalone links). WCAG 2.2 AA only requires 24×24, but 44px is the house standard. Use `min-height: 44px` on the clickable element itself (with `display: inline-flex; align-items: center` for text links) rather than em-based padding — min-height holds at any font size. Links inside running text (paragraphs) are exempt per WCAG and must NOT get a min-height. Existing examples: `.header-login-button a` and the navigation rules in the theme's `screen.scss`, `__navigation-item` in the tabs block, `__nav` in the event-calendar block.

---

## Blocks

Block sources live in `wp-content/plugins/vandrekalender-events/blocks/`. Besides the blocks below there are generic layout blocks (`link-box`, `slider`, `slider-slide`, `tabs`, `tab`) and **Event Filters**, described under [Filter Bar](#filter-bar).

**Event Cards** (`blocks/event-cards/`) — the filterable card grid. Fully server-rendered for SEO using the Interactivity API: `render.php` queries and renders all cards (cumulative pagination via the `side` URL param, 50 per page, reusing `Vandrekalender_Event_Rest_Api::build_query_args()`), and `view.js` is a `viewScriptModule` store that re-renders by navigating with `@wordpress/interactivity-router`, which swaps the block's `data-wp-router-region`. Reacts to `vk:filters-change` from the Event Filters block (listener attached in `callbacks.init` — the directive parser rejects the colon in the event name, so `data-wp-on-document--` can't be used). Requires the `--experimental-modules` flag on `wp-scripts build`/`start`, already set in `package.json`. New interactive blocks should follow this pattern (see paychex-wp for more examples). A card's place text is `event_place_name, event_municipality`, or the `Event::coordinates_label()` GPS text when both are empty.

**Event Calendar** (`blocks/event-calendar/`) — the month grid, same fully server-rendered pattern as Event Cards. `render.php` renders the grid (per-day counts via `Vandrekalender_Event_Rest_Api::count_events_by_day()`) plus the selected day's events; view state lives in the URL — `maaned` (YYYY-MM) picks the month, `dag` (YYYY-MM-DD) selects a day — and `view.js` only navigates through the router. Month/day buttons carry their target in plain `data-month`/`data-day` attributes, **not** `data-wp-context`: the router morphs reused elements in place and their context keeps its initial value, so a context payload goes stale after the first navigation. A filter change resets to the current month on purpose, so the concurrent Cards/Calendar router navigations hit the identical URL. The filter bar's date range is ignored — the calendar has its own month navigation.

**Event Map** (`blocks/event-map/`) — Leaflet needs the client, so this one only partially converts: a small controls-strip island whose `data-wp-context` carries the map config **and** the initial pin payload (`Vandrekalender_Event_Rest_Api::locations_payload()`), so first paint needs no REST call. `view.js` lazy-loads Leaflet and the marker-cluster plugin in `callbacks.init` with a dynamic `import()` of the npm packages (exact versions in the plugin's `package.json`; webpack splits them into their own chunks in `build/`, so pages without a map do not load them; the plugins read the global `L`, so `view.js` sets `window.L` before importing them) and only refetches `GET /vandrekalender/v1/events/locations` on `vk:filters-change`. The camera never follows the pins — fixed Denmark view; filter changes only swap markers. Hard-won rules for coexisting with the router, all of which broke the map before:

- **Leaflet CSS is part of the block stylesheet, never injected from JS.** `index.js` imports `leaflet/style.css` (a file with that name so wp-scripts puts it in `style-index.css`, which WordPress enqueues server-side; `webpack.config.js` lets the build find Leaflet's images). The router manages the `<head>` across client-side navigations, and JS-injected `<link>` tags come out of that morphing present in the DOM but no longer applied — tiles and controls silently lose all layout.
- **The map canvas lives outside the island** (plain sibling div) and the context is read-only: island re-renders would wipe or reset DOM the runtime didn't render. Status/reset visibility is mutated directly on the elements.
- **The map is never created against a hidden (zero-size) container** — cluster maths and tile layout inherit the broken geometry and nothing repairs it. `view.js` waits for the canvas to have real size (the Tabs block dispatches a bubbling `vk:tab-shown` on the activated panel; a slow poll is the fallback — ResizeObserver alone proved unreliable for the display-none→block flip).

**Event Info Card** (`blocks/event-info-card/`) — the sticky summary in the single event sidebar. Routes render as tabs in `Event::sort_routes_by_distance()` order (ascending, routes with no distance last), not `event_routes`' stored (creation) order — Event Route Map sorts the same way, so both blocks always agree. The "Place" row shows `event_place_name`, else `event_municipality`, else `Event::coordinates_label()` ("GPS 55.67286° N, 12.56103° Ø") for events placed only by pasted coordinates. The "Organiser" row shows, for an event with an `event_organiser` term, the organisation's name ("Dansk Vandrelaug", not the DVL chapter) with an initials badge (`Event::organiser_initials()`, until organisers have logos), linked to its page; otherwise `event_organiser_name` as plain text. No term and no name, no row. "Show directions" comes from `Event::directions_url()`: Google Maps to the address, or to the coordinates when there is no address, or, when the pin is only approximate (`event_location_approximate`), to the place name ("Rungsted station, Danmark"). Two scripts on purpose: `view.js` is a plain `viewScript` for the route tabs (they swap values the runtime never rendered, and also swap the "Download GPX" link and dispatch `vk:route-change` — see Event Route Map below), and `join.js` is the `viewScriptModule` holding the "Jeg kommer" CTA (Interactivity API).

The bottom CTA slot holds exactly one of four things, decided server-side:

- `Vandrekalender_Event_Attendees::is_joinable()` **and the viewer is the event's organiser** (`Vandrekalender_Event_Join::is_event_organiser()`, i.e. the `post_author`) → a non-interactive line, "Du er arrangør af denne tur". The organiser runs the walk, so they are not one of its attendees; none of the interactive join state is set up for them (no `data-wp-interactive`, no dialog). The self-join is also refused in the backend (see below), so this is a UI mirror of a real rule, not just a hidden button.
- The event date is before today in the site timezone (`Event::is_past_date()`) → only the note "Denne tur har fundet sted." No booking link, no sign-up button. The join itself is refused in the backend too: `Vandrekalender_Event_Past_Events::disable_join_for_past_events()` turns `is_joinable()` off for past events, using the same `is_past_date()`.
- `is_joinable()` for anyone else → the sign-up button. Server-rendered with its final label, so first paint is right: "Jeg kommer" in forest, or "Deltager" in meadow (the lighter green) when the visitor is already signed up.
- Otherwise (today or a future date), the original "Book din plads" link out to `event_source_url`/`event_organiser_url`.

> "Organiser" here is the individual `post_author`. The shared-ownership `organizer` taxonomy — where a teammate who did not create the event could also count as its organiser — is a deliberately separate, later question (`docs/authentication.md`).

The card normally returns early when the event has no routes; a joinable event skips that early return, since the card is now the only place the button can live. The price and start/cutoff rows are guarded individually instead.

**How the button behaves.** It is a real `<form>` posting to `admin-post.php`, and the server handler is a **toggle** — it joins when you are not attending and cancels when you are — so both directions work with JavaScript off. `data-wp-on--submit` is attached **only when a user is logged in**: a logged-out submit has to reach the server so the pending-join cookie is set before the login redirect (see `docs/authentication.md`). With JavaScript, joining is a `POST` to `/vandrekalender/v1/events/{id}/join`, and cancelling first opens a confirmation before the `DELETE` — so the destructive direction is never one stray click. Per-block values (event ID, attending, busy, confirming, note) live in `data-wp-context`; translated labels and the REST URL/nonce are global `wp_interactivity_state()`.

The confirmation is a native `<dialog>` driven from `callbacks.toggleDialog`, which calls `showModal()`/`close()` in a `data-wp-watch` on `context.confirming`. `showModal()` is the only way to get the backdrop, focus trap and Escape handling, and it cannot be expressed as an attribute binding — the bare `open` attribute renders a non-modal box. The top layer also means the card's `position: sticky` cannot clip it.

**Event Route Map** (`blocks/event-route-map/`) — draws the GPX track(s) attached to a single event's routes (a different block from Event Map, which is the Denmark-wide event calendar). `render.php` returns nothing when no route has a `gpx_id`, so it never leaves an empty gap; otherwise it embeds each GPX-bearing route (`id`, `distance_km`, `gpx_url`, sorted like Event Info Card's tabs) as JSON in a `data-vk-routes` attribute, plus the info card's default-active route's id in `data-vk-initial-route-id` (the first route in that same sorted order, whether or not it has a file — see `$vk_first_route` in Event Info Card's `render.php`), and gets Leaflet's CSS through its block stylesheet (same "never inject from JS" rule as Event Map, followed here for consistency even though this block does not use the router). `view.js` is a **plain `viewScript`**, not the Interactivity API — the single event page is not a router region, so there is no `<head>`-morphing hazard or shared state to coordinate.

It lazy-loads Leaflet and the `leaflet-gpx` plugin with a dynamic `import()` of the npm packages, draws one `L.GPX` layer per route with a distinct colour and plain dot start/end markers (leaflet-gpx's own defaults point at GitHub-hosted images). Only the *selected* route's track is ever shown on the map — not dimmed alongside the rest — matching how every other tab-driven value in the info card (price, start/cutoff time) fully replaces on switch rather than leaving the previous one lingering:

- First paint shows only `data-vk-initial-route-id`'s track (matching the info card's default-active tab), not every track — unless that route has no file, in which case it falls back to fitting all tracks (nothing has set the map's view yet, so an empty unset canvas would be worse).
- `vk:route-change` (dispatched by Event Info Card's tab switcher, `detail.id` matching a route's `id`) switches to that route's track, or hides every track if the selected route has none — this is a real click, so unlike the initial-load fallback above, the view is left as is rather than snapping to fit-all.
- A "Show all routes" toggle button (rendered only when 2+ routes have a file) shows every track together and fits bounds to all of them, overriding the current selection until a route is clicked again.

A download link per route sits below the map.

**Organiser page blocks** (`blocks/organiser-*`, used only in the organiser section patterns, hidden from the inserter). **Organiser Logo** renders the organiser page's featured image in a circle, or `Event::organiser_initials()` of the page title until there is one. **Official Organiser Badge** renders "Officiel arrangør" only when `organiser_official` is set; in the editor it shows faded otherwise. **Organiser Stats** renders tiles for the organiser's upcoming walks, the regions they are in and the shortest to longest route (left out when no route has a distance). **Organiser Regions** lists those regions with their number of walks, most first, each linking to the same page filtered by the region at its walks (`?region=<slug>#kommende-ture`), or "Der er ingen kommende ture lige nu." without walks. **Organiser List** shows organisers with a published page as cards (logo or initials, name, upcoming walks). With `show: "others"` ("Andre arrangører", in `single-organiser.html`, not in the post) it shows up to six organisers other than the page shown, those sharing the most regions first, then by number of walks, and a "Se alle arrangører" link; nothing when there is no other page. With `show: "all"` (the overview, `archive-organiser.html`) it shows every organiser with a page, by name, with its regions. All three compute from the linked term's upcoming walks on every render, never stored, through `Organiser_Walks` (one query for every organiser's upcoming walks per request). In the editor, Stats and Regions show a placeholder text.

**Event count shortcodes** (registered in `vandrekalender-events.php`, for use inside paragraph text). Both render an inline `<span>` that inherits the surrounding text colour and size:

- `[vk_upcoming_count]` — number of upcoming events (event date ≥ today). Static — never reacts to the filter bar. Used on the cover hero.
- `[vk_filtered_count]` — number of events matching the active filters, plus what the page pins (see [Filter Bar](#filter-bar)). Server-renders the initial count from the URL params, then updates live via `GET /vandrekalender/v1/events/count` whenever the Event Filters block broadcasts `vk:filters-change` (enqueues `build/resources/filtered-count/index.js`, built from `resources/filtered-count/index.js`). The pinned filters reach the script in `data-presets`.

---

## Page Templates

All page templates share one skeleton — header part → `<main>` → footer part — and are **top-flush**: `<main>` sets `margin-top:0`, so the first content block sits directly under the header. Gaps between sections come from the root `blockGap` (`medium`) in theme.json; templates only ever *remove* structural spacing, never add it (the one exception: the with-title template adds a `medium` top padding above the title).

| Template | Use case |
|---|---|
| `page.html` (default) | Pages without a rendered title. Also the hero template: make the first content block a full-width Cover and it sits flush against the header. |
| `page-with-title.html` | Standard content pages (contact, about, …). Renders the post title with a deliberate `medium` space above. |
| `page-without-gaps.html` | Full-bleed pages: zero `blockGap` between content blocks and a flush footer. |
| `single-event.html` | Single event view. |
| `single-organiser.html` | An organiser's page: breadcrumbs ("Kalender / Arrangører / Name", see `Organiser::filter_breadcrumbs()`), the post content and Organiser List ("Andre arrangører"). The page itself is built from the `organiser-*` section patterns, see [Organiser Page](#organiser-page). |
| `archive-organiser.html` | The organiser overview `/arrangoerer/`: breadcrumbs ("Kalender / Arrangører"), heading and intro, and Organiser List with every organiser. The browser title is the post type's archives label "Arrangører" (`Organiser::filter_archive_title()`), not the admin's "Arrangørsider". |
| `index.html`, `taxonomy.html` | Archive fallbacks. |

There is deliberately no `front-page.html` — the front page uses `page.html` like any other page.

---

## Filter Bar

The **Event Filters** block (`blocks/event-filters/`) renders a `<form class="vk-filters">`. `render.php` builds the region options from the `event_region` taxonomy (only regions that have events) and the organiser options from the `event_organiser` terms that have at least one upcoming event, sorted by name (the select is left out when there are none); the length options are fixed.

| Control | URL / REST param | Values |
|---|---|---|
| Region (select) | `region` | region term slug |
| Organiser (select) | `organiser` | organiser term slug (the REST API also takes several, comma-separated) |
| Length (toggle pills, several can be on) | `length` | `kort` (0–10 km), `mellem` (10–25 km), `lang` (25+ km), comma-separated |
| From date / To date | `date_from`, `date_to` | `YYYY-MM-DD` |
| Free only (checkbox) | `is_free` | `true` |

The URL is the single source of truth. `view.js` (a plain `viewScript`) reads the controls on every change, writes them to the query string with `history.replaceState`, and dispatches a bubbling `vk:filters-change` event on `document`. On load it fills the controls from the URL, so reloads and shared links keep their state. "Reset" clears everything. Event Cards, Event Calendar, Event Map and the `[vk_filtered_count]` shortcode listen for `vk:filters-change`; the server side reads the same params with `Vandrekalender_Event_Rest_Api::filters_from_query()`.

With no date params the query is floored at today (upcoming events only); an explicit past range still works. Some pages pin a filter regardless of the URL, through `Vandrekalender_Event_Rest_Api::page_presets()`: a region or length archive its term, an organiser page its organiser. Event Cards, Event Calendar, Event Map and `[vk_filtered_count]` merge those presets over the URL's filters. On an organiser page the organiser select is left out. An organiser page without an organiser (a draft) pins `NO_ORGANISER`, which matches no walk, so it never shows every walk as its own.

With no filters from the visitor and nothing upcoming, Event Cards says "Der er ingen kommende ture lige nu." instead of "no walks match your filters".

---

## Organiser Page

`/arrangoerer/<slug>/`, a post of the `organiser` post type ([data-model.md](data-model.md#custom-post-type-organiser), [decision 0008](decisions/0008-organiser-pages-as-post-type.md)), design in `docs/design/organiser-page/`. A new page starts from five theme patterns (`Organiser::PATTERNS`, the post type's `template`), top to bottom:

| Pattern | Content |
|---|---|
| `organiser-hero` | Organiser Logo, Official Organiser Badge, the post title as H1, the excerpt as the short description (write one: without it WordPress cuts one from the page's text), and a "Besøg hjemmeside" button whose link the editor sets |
| `organiser-stats` | "Arrangøren i tal" and Organiser Stats |
| `organiser-walks` | "Kommende ture" with `[vk_filtered_count]`, the filter bar, and the list, calendar and map tabs (list first). Anchor `#kommende-ture` |
| `organiser-about` | Two columns: "Om arrangøren" heading (renamed per organiser), text and an image, then "Ture i hele landet" with Organiser Regions |
| `organiser-faq` | "Ofte stillede spørgsmål" and a core Accordion, starting with two questions that fit every organiser |

Each pattern's outer group has `templateLock: "contentOnly"`: texts, images, links and FAQ questions can be changed and added, the sections and their order cannot. The patterns are in the theme, the post type's `template` names them from the plugin; it only applies to a new page, so changing a pattern does not change pages already written. Styles: `.vk-organiser-page` and `.vk-organiser-hero` in the theme's `screen.scss`, the logo, badge, stats, regions and organiser list in their blocks.

---

## Event Card

One `<li class="vk-card">` per event, rendered by the Event Cards block, the whole card being a link to the event. It shows, top to bottom: the featured image (large size, as a background), the date (site date format), the title, the place (`event_place_name, event_municipality`, or the GPS text when both are empty), and a meta row with the distances of all routes (sorted, e.g. "10, 21 km"), the price and the region. The price reads "Free" when no route has a price or the cheapest is 0, otherwise "from N kr" with the cheapest route's price. There is no difficulty badge. Cards are sorted by event date ascending, 50 per page, with a "Show more walks" button that loads the next 50 (see Event Cards above).

---

## Single Event Page

`single-event.html` is the header part, then `<main>` with the title (wide), and two columns: a 66% column with the excerpt, featured image, post content and the **Event Route Map**; a 34% column with the **Event Info Card**. The info card's route tabs switch the price and times and tell the route map which track to show (`vk:route-change`); both are described under Blocks above.

**Sign-up CTA.** One slot in one place — the bottom of the Event Info Card. An event created on allevandreture.dk gets "Jeg kommer" there (or "Du er arrangør af denne tur" if you created it); a scraped event gets "Book din plads" in the identical slot. See the Event Info Card block above.

> `single-event.html` also exists as a **user customisation in the database** (`wp_template` post `single-event`). Editing the theme file alone changes nothing on a site that has one — the DB copy wins. Add new blocks in the Site Editor, or update both.
