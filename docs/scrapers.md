# Scrapers

> Source of truth for the scraping pipeline — sources, field mapping, and how to add a new scraper.
>
> Reconciled with `data-model.md` and the current plugin code on 2026-06-24. Where this doc describes something **not yet built**, it is marked **Planned**. Everything not marked Planned reflects code that exists in `wp-content/plugins/vandrekalender-events/`.

---

## Current status

The scraping pipeline is **scaffolded but not yet live**. What exists today:

- An abstract base class (`Vandrekalender_Scraper_Base`) with `fetch()`, `parse()`, `run()`, and `upsert_event()`.
- A WP-Cron scheduler (`Vandrekalender_Scraper_Scheduler`) that runs **all** scrapers once weekly.
- One scraper class — `mammutmarch.dk` — with an **empty `parse()` stub** that returns no events yet.

The next milestone is to implement `parse()` for this source and confirm an event reaches the database. Everything in [Field Mapping Strategy](#field-mapping-strategy) below the Layer 1 basics, plus everything in [Planned](#planned--not-yet-built), is design intent, not running code.

---

## Part 1 — Event Sources

All known sources for walking events in Denmark, grouped by type. Priority reflects V1 implementation order: **High** = V1 target, **Medium** = V1 if feasible, **Low** = V2 or later.

> Note: the only source scaffolded in code so far is `mammutmarch.dk`. The DVL and Sportstiming sources below are the highest-value targets but have no scraper class yet.

### Walking organisations

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Dansk Vandrelaug (DVL) | dvl.dk | HTML scrape | High | National walking association. Multiple regional chapters, each with own event pages. Rich data: date, distance, difficulty, meeting point |
| DVL København | dvl.dk/kobenhavn | HTML scrape | High | Copenhagen chapter. High volume of events |
| DVL Horsens | dvl.dk/horsens | HTML scrape | High | Already reviewed — structured event listings |
| DVL other regions | dvl.dk/* | HTML scrape | Medium | Aarhus, Odense, Aalborg and other chapters to be mapped |

### Event timing & registration platforms

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Sportstiming.dk | sportstiming.dk/events | HTML / API check | High | Covers running, walking, cycling, OCR, triathlon. Well-structured listings. Check for public API |
| Eventyrsport.dk | eventyrsport.dk/events | HTML scrape | Medium | Adventure sports events. Returned 403 on first check — may need headers/session handling |
| Finishers.com | finishers.com | HTML / API check | Medium | Global race calendar. Has Danish events but limited walking focus. Check for API |

### March & long-distance walk events

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Mammut March | mammutmarch.dk | HTML scrape | High | Major Danish march. WooCommerce site — events listed as products at `/shop/`. Distances vary per event (e.g. København 75/100 km, Aarhus 30/50 km, 30/42/55 km). **Scaffolded** as `Vandrekalender_Scraper_Mammut` |
| Riddermarchen | riddermarchen.dk | HTML scrape | High | Well-known Danish military-style march. Structured website |
| AMA Vandringen | Facebook only | Facebook API | Medium | Exists only on Facebook. 21/42/55 km options. Needs Facebook Graph API or manual entry |
| Copenhagen Walking Festival | To be found | HTML scrape | Medium | Needs research to find official site |

### Running & multi-sport (walking categories)

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Motionsplan.dk | motionsplan.dk | HTML scrape | Low | Training-plans site — may list events |
| RunnersDK | To be confirmed | HTML scrape | Low | To be confirmed |

### Facebook

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Facebook Events API | developers.facebook.com | Graph API | Medium | Public events via Graph API. Requires Facebook App approval (several weeks). Limited fields available |
| Vandring Danmark (FB group) | facebook.com/groups/* | FB API / manual | Low | Private group — API access limited. May need group-admin partnership |
| Local walking FB groups | Various | FB API / manual | Low | Many small local groups. Assess after Facebook App approval |

### Municipality & nature agencies

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Naturstyrelsen | naturstyrelsen.dk | HTML scrape | Low | Danish Nature Agency. Occasionally lists guided walks and nature events |
| VisitDenmark | visitdenmark.dk | HTML scrape | Low | Tourism board. Lists some walking tours for tourists |
| Municipality sites | Various .dk | HTML scrape | Low | Individual municipalities sometimes list local events. Low data consistency |

### Sources still to research

- Confirm whether Finishers.com and Sportstiming.dk have public APIs.
- Find the official website for Copenhagen Walking Festival.
- Map all DVL regional chapter URLs.
- Research whether Danish orienteering federation (DOF) events include walking categories.
- Check Motionsplan.dk and RunnersDK for relevance.

---

## Part 2 — Field Mapping Strategy

Scraped data rarely maps cleanly to the event schema. Each source structures its events differently — one has a clear distance field, another buries it in a description paragraph, a third does not mention it at all. A three-layer approach handles this consistently across sources.

> **Reconciled with the real schema.** The Word doc this section is based on used flat fields like `event_distance_km`, `event_start_time`, and `event_fee`. Those do **not** exist in the current schema. Per `data-model.md`, distance, start time, cut-off time, and price live **inside `event_routes`** (an array of route objects), `event_is_free` is **derived on save** from those route prices, and `event_length` (Short/Medium/Long taxonomy) is **auto-assigned on save** from the route distances. Geocoding uses **DAWA**, not Nominatim. Field names use British spelling (`event_organiser_name`). The tables below use the real schema keys.

### Layer 1 — Direct field mapping

The simplest case: some fields map cleanly from a specific HTML element to a schema field. These are hardcoded per scraper class as a selector map. Every source has at least a title, a date, and a URL that map directly.

| Schema field | Maps from |
|---|---|
| `post_title` | Main heading element (e.g. `<h1 class="event-title">`) |
| `event_date` | A structured date element or `<time>` tag (`YYYY-MM-DD`) |
| `event_source_url` | The current page URL — always available |
| `event_place_name` | Meeting-point name or venue, if present |
| `event_address` | Address / meeting-point text, passed to DAWA in Layer 2 |
| `event_featured_image` *(if added to schema)* | Main event image `src` |
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
| `event_difficulty` *(if used)* | Keyword match | `"let"` / `"nem"` → Easy · `"moderat"` → Medium · `"svær"` / `"krævende"` → Challenging |
| `event_address` → `event_lat` / `event_lng` / `event_municipality` | **DAWA** lookup | DAWA geocodes the extracted address string and returns coordinates **and** municipality in one call (`api.dataforsyningen.dk`). Same provider as manual event entry |

`event_is_free` and the `event_length` taxonomy are **not scraped** — they are derived on save from `event_routes`, the same as for manually created events. Let the existing save hooks compute them; the scraper only needs to populate `event_routes` correctly.

Pattern extraction runs after direct mapping. If a pattern matches, the field is populated; if not, the field is left null and passed to Layer 3.

### Layer 3 — Leave empty and flag

Some fields cannot be extracted from some sources. Rather than guessing or silently publishing incomplete data, missing fields are handled explicitly by severity:

| Missing field | Behaviour |
|---|---|
| Non-required optional (e.g. `event_difficulty`) | Field left null. Event still published. Card shows no badge for that field |
| `event_routes` distance | Route left without distance, event published, **flagged** for manual enrichment *(flagging UI is Planned)* |
| `event_lat` / `event_lng` (DAWA failed) | Event held as **draft** — coordinates required for the map view. Admin must resolve |
| `event_date` | Event held as **draft** — date is a required field |
| `post_title` | Event **rejected** entirely — not created |

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
- `parse( string $html ): array` — applies Layer 1 selectors and returns an array of event arrays. **This is where per-source work happens.** Both current scrapers return `[]` (stub).

The base class then provides:

- `run(): int` — calls `fetch()`, then `parse()`, then `upsert_event()` for each result; returns the count created/updated.
- `upsert_event( array $event ): bool` — see Deduplication below.

> **Planned:** the Word doc proposed a third `normalise()` method plus shared `FieldMapper` and `GeocodingService` utilities to hold Layer 2/3 logic. These do not exist yet. Until they do, Layer 2 extraction and DAWA calls live inside each scraper's `parse()`. Extracting them into shared utilities is the right move once a second scraper needs the same regexes.

### Adding a new scraper

1. Create `includes/scrapers/class-scraper-{source}.php`.
2. Extend `Vandrekalender_Scraper_Base`.
3. Implement `fetch()` — usually `return $this->remote_get( self::SOURCE_URL );`.
4. Implement `parse( $html )` — return an array of event arrays. Each must include at least `post_title`, the event date, and the source URL (the dedup key).
5. Register the scraper in `class-scraper-scheduler.php` → `run_all_scrapers()`.

### Deduplication

`upsert_event()` deduplicates on the **source URL**:

- If no `event_source_url` is present on the event array, the event is skipped.
- It queries for an existing event with the same `event_source_url`. If found and `event_claimed` is truthy, it is **skipped entirely** — a claimed event is owned by its organiser, who is the source of truth.
- Otherwise it updates the existing post, or inserts a new one published immediately.
- Reserved keys (`post_title`, `post_content`, `post_status`, `post_type`, `ID`) become post fields; every other key is written as post meta.
- After writing the scraper's fields, `upsert_event()` sets the source-tracking meta itself: `event_source = 'scraped'`, `event_scraped_at` = current time, and (on insert only) `event_claimed = false`.

All meta keys use the **canonical, no-underscore schema keys** registered in `class-event.php` (`Vandrekalender\Event::META_*`) — the same keys the REST API and frontend read. This means a scraped event surfaces through exactly the same path as a manually created one. Writing `event_routes` and `event_municipality` also triggers the derive-on-save hooks, so `event_length` and `event_region` taxonomies are assigned automatically.

> Resolved 2026-06-24: the base class previously used underscore-prefixed meta (`_event_source_url`, `_event_claim_status`) that the rest of the app never read. It now uses the registered `event_source_url` / `event_claimed` keys, and `event_source`, `event_source_url`, `event_source_name`, `event_scraped_at`, `event_claimed` are registered as REST-visible meta. The remaining claim-flow fields (`event_claimed_by`, `event_claimed_at`, claim tokens) are documented in `data-model.md` and will be registered as part of the claim-flow milestone.

### Scheduling

`Vandrekalender_Scraper_Scheduler` (`includes/class-scraper-scheduler.php`):

- Registers a single cron hook, `vandrekalender_run_scrapers`, on a **weekly** schedule (a custom `weekly` interval it adds to WP-Cron).
- `run_all_scrapers()` instantiates every scraper and calls `run()` on each. All sources run on the same weekly cadence.
- Scheduled on plugin activation, cleared on deactivation.

Trigger manually:

```bash
./wp.sh cron event run vandrekalender_run_scrapers
```

> **Planned:** per-source schedules (High-priority sources such as DVL/Sportstiming daily, Medium every 3 days, Low weekly) instead of one shared weekly run. Also planned: skip re-scraping claimed events at the scheduler level (currently the skip happens inside `upsert_event`), and set past events (`event_date` < today) to an archived status on each run.

---

## Planned — not yet built

None of the following exists in code yet. Kept here as design intent so the roadmap isn't lost.

### Confidence scoring

Score each scraped event by how many fields were extracted, and route it accordingly:

- **High** (all required + 3 or more optional fields) → auto-publish immediately.
- **Medium** (all required, few/no optional) → publish with gaps, flag for enrichment.
- **Low** (one or more required fields missing) → save as draft, admin reviews before publishing.

### Tombstones

Events deleted by an admin should not be re-created on the next scrape. Maintain a tombstone list of dismissed source URLs that `upsert_event()` checks before inserting.

### Admin scraping dashboard

A WP-admin screen showing each registered scraper with last-run time, events found/added/updated, and errors; a queue of flagged events needing manual enrichment with the specific missing field highlighted; a per-scraper "Run now" button; and a log viewer of raw scraper output for debugging.

### Facebook scraping

Facebook event scraping needs a Facebook Developer App with `pages_read_engagement` or `public_content_access`. App Review takes several weeks — **apply early**. Public events from public pages can be fetched without user auth. Private-group events need group-admin cooperation or are out of scope. Available fields: name, description, start_time, end_time, place (name + location), cover photo. Distance and difficulty are almost never structured on Facebook, so Layer 2 regex on the description will usually fall through to Layer 3.

### LLM extraction (V3)

Once the source list grows beyond ~10 sources, hand-maintaining Layer 1 selector maps and Layer 2 regex per source becomes impractical. The V3 approach: feed raw event HTML to an LLM with a prompt to return structured JSON matching the schema. The LLM replaces Layers 1 and 2 — it reads free-form content and returns typed fields. Layer 3 (required-field validation and flagging) still applies on top. Adding a source then becomes trivial: just a URL, no custom scraper class.

---

## Key files

```
wp-content/plugins/vandrekalender-events/includes/
├── class-scraper-base.php          ← abstract base: fetch/parse/run/upsert_event/remote_get
├── class-scraper-scheduler.php     ← WP-Cron: weekly hook, runs all scrapers
└── scrapers/
    └── class-scraper-mammut.php    ← mammutmarch.dk (parse() stub)
```

Related: `docs/data-model.md` (canonical schema — meta keys, taxonomies, `event_routes` shape, DAWA), `docs/authentication.md` (claim flow and organiser ownership).
