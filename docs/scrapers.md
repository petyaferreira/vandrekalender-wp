# Scrapers

> Source of truth for the scraping pipeline — sources, field mapping, and how to add a new scraper.
>
> Reconciled with `data-model.md`, the current plugin code, and a live URL check on 2026-06-24. Anything marked **Planned** or **to build** is design intent, not running code. Everything else reflects code in `wp-content/plugins/vandrekalender-events/`.

---

## Current status

The scraping pipeline is **built and running**. What exists today:

- An abstract base class (`Vandrekalender_Scraper_Base`) with `fetch()`, `parse()`, `run()`, and `upsert_event()`.
- A WP-Cron scheduler (`Vandrekalender_Scraper_Scheduler`) that runs **all** scrapers once daily at 02:12 (site timezone), gated to production by the `VK_ENABLE_SCRAPING` constant. See [Running & scheduling](#running--scheduling).
- Three implemented scrapers: `mammutmarch.dk` (`Vandrekalender_Scraper_Mammut`), `sportstiming.dk` (`Vandrekalender_Scraper_Sportstiming`), and `dvl.dk` (`Vandrekalender_Scraper_DVL` — all regional chapters via the national maps feed).

Coordinates come from the server-side DAWA helper (see [Geocoding](#geocoding-server-side-dawa-helper--built)); DVL brings its own coordinates and only reverse-geocodes the municipality.

---

## Part 1 — Event Sources

**V1 implementation order.** The full list below is grouped by type. For v1 we build scrapers in this order:

| # | Source | Why |
|---|---|---|
| 1 | Mammut March | First target, already scaffolded. Known organiser, easy to eyeball the result |
| 2 | Sportstiming.dk | Walking events only |
| 3 | Dansk Vandrelaug (DVL) | Richest, most representative source (free guided walks) |

Priority below reflects rough value: **High** = v1 target, **Medium** = v1 if feasible. Low-value sources have been dropped.

### Walking organisations

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Dansk Vandrelaug (DVL) | dvl.dk | JSON feed + HTML | High | **Built** as `Vandrekalender_Scraper_DVL` (2026-07-03). The tour listing is client-rendered, but the public feed at `/wp-json/dvl/v1/maps/data` lists every upcoming tour (~490) with title, exact meeting-point coordinates, and tour URL. Each server-rendered tour page (`/vandreture/<slug>/`) is then fetched for date/time (from the add-to-calendar link), distance, organising chapter (`Arrangør` → `DVL <chapter>` + chapter page URL), meeting point, description, and image. Coordinates come from the feed; only the municipality is reverse-geocoded via DAWA (`Geocoder::municipality_from_coords()`), because meeting points are often landmark names DAWA cannot geocode forward. Day walks are marked free (price 0) only when the page carries the "Turen er gratis for medlemmer" note; paid vandreferier get no price. One scraper covers **all regional chapters** — the feed is national |

### Event timing & registration platforms

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Sportstiming.dk | sportstiming.dk/events | HTML / API check | High | Confirmed live 2026-06-24. Has a dedicated **Walk** category. Events at `/events`. Check for a public API |
| Eventyrsport.dk | eventyrsport.dk/events | HTML scrape | Medium | Adventure sports events. Loaded fine on recheck 2026-06-24 (the earlier 403 did not recur) |

### March & long-distance walk events

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Mammut March | mammutmarch.dk | HTML scrape | High | Confirmed live 2026-06-24. 24-hour march ("100 km til fods"). WooCommerce site — events listed as products at `/shop/` (e.g. København 75/100 km, Aarhus 30/50 km, plus 30/42/55 km variants). Distances vary per event. **Scaffolded** as `Vandrekalender_Scraper_Mammut` |
| Riddermarchen | riddermarchen.dk | HTML scrape | High | Well-known Danish military-style march. Domain resolves but is client-rendered, so content is not visible to a plain fetch. Checked 2026-06-24 |
| AMA Vandringen | Facebook only | FB importer (v1) | Medium | Exists only on Facebook. 21/42/55 km options. v1: import via **Events → Add from Facebook**. Facebook API deferred to v2 |

### Facebook

**API deferred to v2 — v1 uses the paste-a-link importer.** The **Add from Facebook** admin screen (**Events → Add from Facebook**, `Vandrekalender_Facebook_Importer`) lets an admin paste public `facebook.com/events/…` URLs — one per line, up to 30 per batch. For each **new** URL the importer normalises it, deduplicates against `event_source_url`, fetches the page server-side, and prefills a **draft** event from its Open Graph tags — title, cover image, and (parsed out of Facebook's generated og:description sentence) the **event date**, **organiser name**, and **place name** where present — plus **one seeded empty route** (so the event surfaces in calendar views; the admin fills distance/time/price) — with `event_source = facebook` / `event_source_name = Facebook`. **Group-hosted events** (created inside a group rather than shared into it) are login-walled to *all* anonymous agents including Facebook's own crawler UA (verified 2026-07-03) — those import as empty drafts with only the source URL, to be filled in manually from the browser. The page fetch uses the plain `Vandrekalender/1.0` user-agent (Facebook rejects spoofed browser UAs with HTTP 400 but serves link-preview data to simple ones); the cover image lives on Facebook's lookaside crawler-media endpoint, which only serves image bytes to link-preview crawler UAs, so that one request identifies as `facebookexternalhit`. The admin verifies the date and completes routes and address (DAWA autocomplete in the editor) and publishes — the usual save hooks derive region/length/is-free. If Facebook serves a login wall anyway, the importer still creates the draft with only the source URL set. Already-imported URLs are **skipped untouched** (create-or-skip, never update), so re-pasting a whole list is safe. No Meta Developer App, no App Review. See [Facebook scraping](#facebook-scraping) for why the API route is not worth it yet.

**Groups (Vandreture i Danmark, DVL group, …).** A group's event *listing* is only rendered for logged-in browsers (verified 2026-07-03), and Meta shut down the Groups API entirely in April 2024, so listings cannot be fetched automatically by anyone. The importer page therefore ships a **link-collector bookmarklet**: the admin opens the group's events page in their own logged-in browser, clicks the bookmarklet, and every `facebook.com/events/{id}` link on the page is copied to the clipboard for pasting into the batch field. Weekly routine: open each group's events tab → scroll → bookmarklet → paste → complete the new drafts (verify date, add routes and address) → publish.

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Facebook Events API | developers.facebook.com | Graph API | v2 | Reading public events from Pages/groups you don't own is largely unavailable to third-party apps. Reliable access only for a Page you/the organiser admin, via a Page token. Needs a Developer App, App Review (weeks), and business verification |
| Vandring Danmark (FB group) | facebook.com/groups/* | manual | v2 (backlog) | Private group — API access effectively gone for outside apps. Manual or group-admin partnership only |
| Local walking FB groups | Various | manual | v2 (backlog) | Many small local groups. Manual entry or organiser submission |

### Sources still to research

- Confirm whether Sportstiming.dk has a public API.

---

## Part 2 — Field Mapping Strategy

Scraped data rarely maps cleanly to the event schema. Each source structures its events differently — one has a clear distance field, another buries it in a description paragraph, a third does not mention it at all. A three-layer approach handles this consistently across sources.

> **Reconciled with the real schema.** Distance, start time, cut-off time, and price live **inside `event_routes`** (an array of route objects), not as flat fields. `event_is_free` is **derived on save** from route prices, and `event_length` (Short/Medium/Long taxonomy) is **auto-assigned on save** from route distances. Geocoding uses **DAWA**. Field names use British spelling (`event_organiser_name`). Difficulty is **out of scope for v1** (no difficulty field in the schema). The tables below use the real schema keys.

### Layer 1 — Direct field mapping

The simplest case: some fields map cleanly from a specific HTML element to a schema field. These are hardcoded per scraper class as a selector map. Every source has at least a title, a date, and a URL that map directly.

| Schema field | Maps from |
|---|---|
| `post_title` | Main heading element (e.g. `<h1 class="event-title">`) |
| `event_date` | A structured date element or `<time>` tag (`YYYY-MM-DD`) |
| `event_source_url` | The current page URL — always available |
| `event_place_name` | Meeting-point name or venue, if present |
| `event_address` | Address / meeting-point text, passed to DAWA in Layer 2 |
| featured image (native WP) | Main event image `src`, sideloaded as the post's featured image |
| `event_organiser_name` | Organiser or club name field |

Each scraper keeps its own hardcoded selector map specific to that source's markup. When a source changes its design, only that scraper's selector map needs updating.

### Layer 2 — Pattern extraction & geocoding

Some fields are present but embedded in free text rather than structured elements. They follow predictable patterns extractable with regex or keyword matching. **Route-level fields (distance, start time, price) are assembled into `event_routes` entries** rather than written as flat meta.

| Target | Method | Pattern / source |
|---|---|---|
| `event_routes[].distance_km` | Regex on title/description | `"15 km"` / `"15km"` / `"15 kilometer"` → `15.0` |
| `event_routes[].start_time` | Regex | `"kl. 09:00"` / `"09.00"` / `"kl 9"` |
| `event_routes[].cutoff_time` | Regex | Stated cut-off / max duration where present |
| `event_routes[].price` | Keyword / amount | `"gratis"` / `"free"` → `0`; a DKK amount → that value |
| `event_address` → `event_lat` / `event_lng` / `event_municipality` | **DAWA** lookup | DAWA geocodes the extracted address string and returns coordinates **and** municipality in one call (`api.dataforsyningen.dk`). Same provider as manual event entry |

`event_is_free` and the `event_length` taxonomy are **not scraped** — they are derived on save from `event_routes`, the same as for manually created events. Let the existing save hooks compute them; the scraper only needs to populate `event_routes` correctly.

Pattern extraction runs after direct mapping. If a pattern matches, the field is populated; if not, the field is left null and passed to Layer 3.

### Layer 3 — Leave empty and flag

Some fields cannot be extracted from some sources. Rather than guessing or silently publishing incomplete data, missing fields are handled explicitly by severity:

| Missing field | Behaviour |
|---|---|
| Optional field (e.g. a route's `start_time`) | Field left null. Event still published. Card shows no badge for that field |
| `event_routes` distance | Route left without distance, event published, **flagged** for manual enrichment *(flagging UI is Planned)* |
| `event_lat` / `event_lng` (DAWA failed) | Event held as **draft** — coordinates required for the map view. Admin must resolve |
| `event_date` | Event held as **draft** — date is a required field |
| `post_title` | Event **rejected** entirely — not created |

This replaces the confidence-scoring system from the original plan. These Layer 3 rules alone decide publish vs draft vs reject.

### Field mapping reference

| Schema field | Layer | Method | Notes |
|---|---|---|---|
| `post_title` | 1 | HTML selector | Main heading per source |
| `event_date` | 1 | HTML selector | `<time>` tag or structured date element |
| `event_source_url` | 1 | Auto | Current page URL, always available; dedup key |
| `event_place_name` | 1 | HTML selector | Meeting-point / venue name |
| `event_organiser_name` | 1 | HTML selector | Club or organiser name |
| `event_routes[].distance_km` | 2 | Regex | `"15 km"` / `"15km"` / `"15 kilometer"` |
| `event_routes[].start_time` | 2 | Regex | `"kl. 09:00"` / `"09.00"` |
| `event_routes[].price` | 2 | Keyword / amount | `"gratis"` → `0`, DKK amount → value |
| `event_address` | 2 | HTML / regex | Passed to DAWA |
| `event_lat` / `event_lng` | 2 | DAWA | Geocoded from address |
| `event_municipality` | 2 | DAWA | Returned alongside coordinates |
| `event_is_free` | — | Derived on save | From `event_routes` prices — not scraped |
| `event_length` | — | Derived on save | Taxonomy auto-assigned from route distances — not scraped |
| `event_region` | — | Derived on save | Taxonomy assigned from `event_municipality` — not scraped |
| `event_lat` / `event_lng` | 3 | Draft if missing | Required for map view |
| `event_date` | 3 | Draft if missing | Required field |
| `post_title` | 3 | Reject if missing | Event not created |

---

## Part 3 — Scraper Architecture (current)

This section describes the code that exists today.

### Base class

`Vandrekalender_Scraper_Base` (`includes/class-scraper-base.php`) is abstract. Each source subclasses it and implements two methods:

- `fetch(): string` — retrieves raw HTML. The base class provides `remote_get( $url )`, a `wp_remote_get` wrapper with a 15-second timeout, a `Vandrekalender/1.0` user-agent, and empty-string return on any non-200 / error.
- `parse( string $html ): array` — applies Layer 1 selectors and Layer 2 extraction, returning an array of event arrays. **This is where per-source work happens.** The current scraper (`mammutmarch.dk`) returns `[]` (stub).

The base class then provides:

- `run(): int` — calls `fetch()`, then `parse()`, then `upsert_event()` for each result; returns the count created/updated. Afterwards it drafts stale events — see Removal below.
- `upsert_event( array $event ): bool` — see Deduplication below.
- `mark_source_url_seen( string $url ): void` — scrapers call this for every URL found in the source's listing, *before* fetching the detail page, so a temporarily unreachable detail page is not mistaken for a removed event. `run()` also marks the source URL of every parsed event automatically.

> **Kept simple for v1.** The original plan proposed a third `normalise()` method plus a shared `FieldMapper` utility. These do not exist and are deferred: for v1 the Layer 2 regex lives inside each scraper's `parse()` or small private helpers. Extracting a shared `FieldMapper` is the right move once a second scraper needs the same patterns.

### Geocoding (server-side DAWA helper) — built

`Vandrekalender_Geocoder` (`includes/class-geocoder.php`) turns a free-text Danish address into `event_lat` / `event_lng` / `event_municipality` via the DAWA autocomplete endpoint, with transient caching (a month for hits, an hour of negative caching for misses). Scrapers call it server-side; the block editor keeps its own client-side DAWA integration (`resources/event-meta-fields/index.js`).

### Adding a new scraper

1. Create `includes/scrapers/class-scraper-{source}.php`.
2. Extend `Vandrekalender_Scraper_Base`.
3. Implement `fetch()` — usually `return $this->remote_get( self::SOURCE_URL );`.
4. Implement `parse( $html )` — return an array of event arrays. Each must include at least `post_title`, `event_date`, and `event_source_url` (the dedup key). Call `$this->mark_source_url_seen( $url )` for each URL as it is discovered in the listing, so events removed at the source get drafted (see Removal below).
5. Register the scraper in `class-scraper-scheduler.php` → `run_all_scrapers()`.

### Deduplication

`upsert_event()` deduplicates on the **source URL**:

- If no `event_source_url` is present on the event array, the event is skipped.
- It queries for an existing event with the same `event_source_url`. If found and `event_claimed` is truthy, it is **skipped entirely** — a claimed event is owned by its organiser, who is the source of truth.
- Otherwise it updates the existing post, or inserts a new one published immediately.
- Reserved keys (`post_title`, `post_content`, `post_status`, `post_type`, `ID`) become post fields; every other key is written as post meta.
- After writing the scraper's fields, `upsert_event()` sets the source-tracking meta itself: `event_source = 'scraped'`, `event_scraped_at` = current time, and (on insert only) `event_claimed = false`.

### Recurring events (duplicate titles)

Some sources (DVL) publish a **separate page per occurrence** of a recurring walk — same title, same description, different date and URL. Each occurrence legitimately becomes its own event post (the calendar and filters need one post per date), but identical titles produce near-identical public pages that Google flags as duplicate content.

`upsert_event()` therefore disambiguates titles (`disambiguate_title()`): if another event post already uses the exact same title, the new occurrence's title gets the localised event date appended — `Motionstur fra Hareskov St. – 23. juli 2026` — which also makes the slug WordPress derives from it unique. The **first** occurrence keeps the clean title and URL; only subsequent twins are suffixed. The check runs on every upsert (excluding the post itself), so re-scraping an occurrence keeps its dated title stable.

### Removal (events cancelled at the source)

Every scraper's listing covers **all upcoming events** at its source, so after each run the base class (`unpublish_stale_events()`) moves to **draft** any published event of that source whose URL was not seen during the run — it was cancelled or removed at the source. Guard rails:

- **Past events are never touched by this sweep** — they drop out of "upcoming" listings naturally. Only events with `event_date` >= today are candidates. (Past scraped events are drafted separately, a week after their date — see Past-events cleanup below.)
- **Claimed events are never touched** — the organiser is the source of truth.
- **A failed or empty fetch drafts nothing** — if the run saw no URLs at all, the source is assumed unreachable rather than empty.
- **Draft, not delete**: the source-URL dedup matches drafts, so if the source re-lists the event (or the feed had a one-day glitch), the next run republishes the same post instead of creating a duplicate.

All meta keys use the **canonical, no-underscore schema keys** registered in `class-event.php` (`Vandrekalender\Event::META_*`) — the same keys the REST API and frontend read. A scraped event surfaces through exactly the same path as a manually created one. Writing `event_routes` and `event_municipality` also triggers the derive-on-save hooks, so `event_length` and `event_region` taxonomies are assigned automatically.

### Past-events cleanup

After the scrapers run, `Vandrekalender_Scraper_Scheduler::cleanup_past_events()` moves to **draft** every published **scraped, unclaimed** event whose `event_date` is more than **7 days** past. Recurring sources publish a fresh page per occurrence, so without this sweep past occurrences accumulate forever as near-identical public pages (reported by Google Search Console as "Duplicate, Google chose different canonical than user"). Guard rails:

- **Manually created and Facebook-imported events are never touched** — only `event_source = 'scraped'`.
- **Claimed events are never touched** — the organiser is the source of truth.
- The week's grace keeps just-finished events visible while still relevant.
- The sweep is logged as its own row (`Cleanup (past events)`) in the Scraper Log.

Tombstones (not re-creating admin-deleted events) are deferred to v2.

> Resolved 2026-06-24: the base class previously used underscore-prefixed meta (`_event_source_url`, `_event_claim_status`) that the rest of the app never read. It now uses the registered `event_source_url` / `event_claimed` keys, and `event_source`, `event_source_url`, `event_source_name`, `event_scraped_at`, `event_claimed` are registered as REST-visible meta. The remaining claim-flow fields (`event_claimed_by`, `event_claimed_at`, claim tokens) are documented in `data-model.md` and will be registered with the claim-flow milestone.

### Scheduling

`Vandrekalender_Scraper_Scheduler` (`includes/class-scraper-scheduler.php`):

- Registers a single cron hook, `vandrekalender_run_scrapers`, on a **weekly** schedule (a custom `weekly` interval it adds to WP-Cron).
- `run_all_scrapers()` instantiates every scraper and calls `run()` on each. All sources run on the same weekly cadence.
- Scheduled on plugin activation, cleared on deactivation.

Trigger manually:

```bash
./wp.sh cron event run vandrekalender_run_scrapers
```

> **Planned (v2):** per-source schedules (high-volume sources more often, others weekly) instead of one shared weekly run.

---

## Planned (v2 and later)

None of the following exists in code yet. Kept here as design intent so the roadmap isn't lost.

### Tombstones

Events deleted by an admin should not be re-created on the next scrape. Maintain a tombstone list of dismissed source URLs that `upsert_event()` checks before inserting.

### Admin scraping dashboard

**Partly built.** A **Scraper Log** screen now exists under **Events → Scraper Log**
(`Vandrekalender_Scraper_Admin`), listing recent runs with their trigger
(cron/manual), duration, total events updated, and per-scraper counts or errors,
plus the current schedule status. Runs are stored in the `vandrekalender_scraper_runs`
option (capped, most recent first) by `Vandrekalender_Scraper_Log`.

Still planned: a queue of flagged events needing manual enrichment, and a
per-scraper "Run now" button in the UI.

### Facebook scraping

**Decision (2026-06-24): API deferred to v2.** Not worth the cost for v1. **Update (2026-07-03):** v1 instead ships the paste-a-link importer (**Events → Add from Facebook**, see [Facebook](#facebook) above) — no Meta app, best-effort Open Graph prefill into a draft.

Reading event data through the Graph API needs a Meta Developer App plus App Review (weeks), and usually business verification and a privacy policy. The bigger problem is access: Meta has locked down public content, so reading events from Pages or groups you do **not** own is largely unavailable to third-party apps. The only reliable route is reading events for a Page that you or the organiser admin, using that Page's access token. Private groups (e.g. Vandring Danmark) are effectively closed to outside apps.

For v1, Facebook-only events (e.g. AMA Vandringen) are handled by manual entry or by the organiser submitting/claiming the event. Revisit the API only if an organiser partnership makes a Page-token approach worthwhile. If pursued later: available fields are name, description, start_time, end_time, place (name + location), cover photo; distance is almost never structured, so Layer 2 regex on the description usually falls through to Layer 3.

### LLM extraction (V3)

Once the source list grows beyond ~10 sources, hand-maintaining Layer 1 selector maps and Layer 2 regex per source becomes impractical. The V3 approach: feed raw event HTML to an LLM with a prompt to return structured JSON matching the schema. The LLM replaces Layers 1 and 2 — it reads free-form content and returns typed fields. Layer 3 (required-field validation and flagging) still applies on top. Adding a source then becomes trivial: just a URL, no custom scraper class.

---

## Running & scheduling

**Automatic (production only).** `Vandrekalender_Scraper_Scheduler` schedules a
daily WP-Cron job at **02:12 site time** (Europe/Copenhagen), but only where
`VK_ENABLE_SCRAPING` is defined and truthy. Add to production's `wp-config.php`:

```php
define( 'VK_ENABLE_SCRAPING', true );
```

The schedule self-reconciles on `init`: defining the flag schedules the job (no
reactivation needed); removing it clears the job. Local and staging omit the
flag, so they never auto-run.

Because WP-Cron only fires on site traffic, production should drive it from a
real system cron with `DISABLE_WP_CRON` set — e.g. a crontab entry running
`wp cron event run --due-now` frequently. Note the timezone: the job becomes due
at 02:12 Copenhagen (= 00:12 UTC), so a UTC crontab should target 00:12 (or run
every few minutes and let `--due-now` catch it).

**Manual (local / anywhere).** Run the whole pipeline once and record it to the
Scraper Log:

```bash
./scrape.sh                          # wrapper around the WP-CLI command
./wp.sh vandrekalender scrape        # equivalent
```

Both cron and manual runs go through `Scheduler::execute()`, so both appear in
**Events → Scraper Log**.

## Key files

```
wp-content/plugins/vandrekalender-events/includes/
├── class-scraper-base.php          ← abstract base: fetch/parse/run/upsert_event/remote_get
├── class-scraper-scheduler.php     ← schedule (daily 02:12, prod-gated) + execute() run/log
├── class-scraper-log.php           ← rolling run history (vandrekalender_scraper_runs option)
├── class-scraper-admin.php         ← Events → Scraper Log admin screen
├── class-geocoder.php              ← server-side DAWA geocoding (address → lat/lng/municipality, coords → municipality)
├── class-facebook-importer.php     ← Events → Add from Facebook paste-a-link importer
└── scrapers/
    ├── class-scraper-mammut.php        ← mammutmarch.dk
    ├── class-scraper-sportstiming.php  ← sportstiming.dk
    └── class-scraper-dvl.php           ← dvl.dk (Dansk Vandrelaug)
```

Manual-run wrapper: `scrape.sh` (repo root). Production flag: `VK_ENABLE_SCRAPING`
in `wp-config.php`.

Related: `docs/data-model.md` (canonical schema — meta keys, taxonomies, `event_routes` shape, DAWA), `docs/authentication.md` (claim flow and organiser ownership).
