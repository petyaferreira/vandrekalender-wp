# Scrapers

> Source of truth for the scraping pipeline — sources, field mapping, and how to add a new scraper.
>
> Reconciled with `data-model.md`, the current plugin code, and a live URL check on 2026-06-24. Anything marked **Planned** or **to build** is design intent, not running code. Everything else reflects code in `wp-content/plugins/vandrekalender-events/`.

---

## Current status

The scraping pipeline is **built and running**. What exists today:

- An abstract base class (`Vandrekalender_Scraper_Base`) with `fetch()`, `parse()`, `run()`, and `upsert_event()`.
- A WP-Cron scheduler (`Vandrekalender_Scraper_Scheduler`) that runs **all** scrapers once daily at 02:12 (site timezone), gated to production by the `VK_ENABLE_SCRAPING` constant. See [Running & scheduling](#running--scheduling).
- Four implemented scrapers: `mammutmarch.dk` (`Vandrekalender_Scraper_Mammut`), `sportstiming.dk` (`Vandrekalender_Scraper_Sportstiming`), `dvl.dk` (`Vandrekalender_Scraper_DVL` — all regional chapters via the national maps feed), and `opdagverden.dk` (`Vandrekalender_Scraper_Opdagverden` — day walks).

Coordinates come from the server-side Adressevælger helper (see [Geocoding](#geocoding-server-side-adressevælger-helper--built)); DVL brings its own coordinates and only reverse-geocodes the municipality. Opdag Verden has no street addresses; its meeting point comes from the trip description and its pin from a Google Maps link, the route page or the region (see the Opdag Verden row below).

---

## Part 1 — Event Sources

The list below is grouped by type. **Built** in the notes marks a source that has a scraper. Priority reflects rough value (**High**, **Medium**); low-value sources have been dropped.

### Walking organisations

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Opdag Verden | opdagverden.dk | Page data (JSON) | High | **Built** as `Vandrekalender_Scraper_Opdagverden` (2026-08-19, rewritten 2026-10-13 after the site moved). Membership outdoor community. The site is a Laravel/Inertia app: each page carries its data as JSON in the `data-page` attribute. Day walks are listed at `/faellesture/dagsvandringer` (paginated, `trips`), each trip has a detail page `/faellesture/<slug>` (`trip`: title, description, `location`, categories, `upcoming_dates` with a price each). A trip walked on several dates becomes **one event per date**, keyed `/faellesture/<slug>#<date>`. Distance comes from the title, start time from the description ("Start: Kl.10 ved …"), price from the date (non-member price). The description is public now and is scraped. The **meeting point** is the description's start line ("Start: Kl.10 ved Rungsted station"), shown as the place name with no street address; the trip's `location.name` ("Blixen ruten (folehave skov) rundsted") is only a fallback. The **pin**, once per trip: (1) the Google Maps short link many start lines carry (`maps.app.goo.gl`, whose redirect holds the exact coordinates; cached for a month), exact; else (2) the point of the trip's linked route page (`/ruter/<hike_route.slug>`, `hikeRoute.latitude/longitude`), which is somewhere on the route, not the meeting point, and is only used when the reverse lookup puts it in the trip's region; else (3) the centre of the region (`Vandrekalender_Municipalities::REGION_CENTRES`). (2) and (3) set `event_location_approximate`, so the map shows the walk but directions use the place name and the JSON-LD has no `geo`. The region comes from the pin's municipality, or from the trip's **region category** (`region_from_categories()`, via `tax_terms`) when there is none. The route's GPX file is members only (403). On 14 Oct 2026: 9 of 17 trips had an exact pin, 6 the route point, 2 the region centre. Until October 2026 the site was a Joomla "Events Booking" site with one page per date at `/ture/dagsvandring/<region>/<slug>-<id>`; see [Source URL changes](#source-url-changes) |
| Dansk Vandrelaug (DVL) | dvl.dk | JSON feed + HTML | High | **Built** as `Vandrekalender_Scraper_DVL` (2026-07-03). The tour listing is client-rendered, but the public feed at `/wp-json/dvl/v1/maps/data` lists every upcoming tour (~490) with title, exact meeting-point coordinates, and tour URL. Each server-rendered tour page (`/vandreture/<slug>/`) is then fetched for date/time (from the add-to-calendar link), distance, organising chapter (`Arrangør` → `DVL <chapter>` + chapter page URL), meeting point, description, and image. Coordinates come from the feed; only the municipality is reverse-geocoded (`Geocoder::municipality_from_coords()`), because meeting points are often landmark names an address search cannot geocode forward. The municipality is the nearest address's (Datafordeler, see [Geocoding](#geocoding-server-side-adressevælger-helper--built)). DVL events scraped between 1 October 2026 and this lookup going live got no municipality or region; `wp vandrekalender regeocode` backfilled them on production on 9 Oct 2026. Day walks are marked free (price 0) only when the page carries the "Turen er gratis for medlemmer" note; paid vandreferier get no price. One scraper covers **all regional chapters** — the feed is national |

### Event timing & registration platforms

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Sportstiming.dk | sportstiming.dk/events | HTML / API check | High | Confirmed live 2026-06-24. Has a dedicated **Walk** category. Events at `/events`. Check for a public API |
| Eventyrsport.dk | eventyrsport.dk/events | HTML scrape | Medium | Adventure sports events. Loaded fine on recheck 2026-06-24 (the earlier 403 did not recur) |

### March & long-distance walk events

| Source | URL | Type | Priority | Notes |
|---|---|---|---|---|
| Mammut March | mammutmarch.dk | JSON API | High | **Built** as `Vandrekalender_Scraper_Mammut`. 24-hour march ("100 km til fods"). Since autumn 2026 a Next.js site backed by a public JSON API (`https://mm-backend-production-f4af.up.railway.app/api`, the one its pages call; every request needs the header `x-locale: dk`). `/events?country=dk` lists the events (name, date, address, city, distances, image; the list also has Berlin, Stockholm and Göteborg, skipped as not Danish). `/events/{id}/variants` gives the variants per distance (participation only, or with an event shirt); the participation price is `basePrice` in øre. There is no public single-event endpoint, so the description is read from the event page `/events/<slug>` (the first `.tiptap-content` element). Start times are only published shortly before the march ("i startgrupper; tidspunkterne offentliggøres senere"); the variants' `startTime` is used once set. Until autumn 2026 the event pages were at `/<n>-km-marsch/<slug>/`; see [Source URL changes](#source-url-changes) |
| Riddermarchen | riddermarchen.dk | HTML scrape | High | Well-known Danish military-style march. Domain resolves but is client-rendered, so content is not visible to a plain fetch. Checked 2026-06-24 |
| AMA Vandringen | Facebook only | FB importer | Medium | Exists only on Facebook. 21/42/55 km options. Imported via **Events → Add from Facebook**. No Facebook API, see [Facebook scraping](#facebook-scraping) |

### Facebook

**No API: the paste-a-link importer.** The **Add from Facebook** admin screen (**Events → Add from Facebook**, `Vandrekalender_Facebook_Importer`) lets an admin paste public `facebook.com/events/…` URLs — one per line, up to 30 per batch. For each **new** URL the importer normalises it, deduplicates against `event_source_url`, fetches the page server-side, and prefills a **draft** event from its Open Graph tags — title, cover image, and (parsed out of Facebook's generated og:description sentence) the **event date**, **organiser name**, and **place name** where present — plus **one seeded empty route** (so the event surfaces in calendar views; the admin fills distance/time/price) — with `event_source = facebook` / `event_source_name = Facebook`. **Group-hosted events** (created inside a group rather than shared into it) are login-walled to *all* anonymous agents including Facebook's own crawler UA (verified 2026-07-03) — those import as empty drafts with only the source URL, to be filled in manually from the browser. The page fetch uses the plain `Vandrekalender/1.0` user-agent (Facebook rejects spoofed browser UAs with HTTP 400 but serves link-preview data to simple ones); the cover image lives on Facebook's lookaside crawler-media endpoint, which only serves image bytes to link-preview crawler UAs, so that one request identifies as `facebookexternalhit`. The admin verifies the date and completes routes and address (Adressevælger address search in the editor) and publishes — the usual save hooks derive region/length/is-free. If Facebook serves a login wall anyway, the importer still creates the draft with only the source URL set. Already-imported URLs are **skipped untouched** (create-or-skip, never update), so re-pasting a whole list is safe. No Meta Developer App, no App Review. See [Facebook scraping](#facebook-scraping) for why the API route is not worth it yet.

**Groups (Vandreture i Danmark, DVL group, …).** A group's event *listing* is only rendered for logged-in browsers (verified 2026-07-03), and Meta shut down the Groups API entirely in April 2024, so listings cannot be fetched automatically by anyone. The importer page therefore ships a **link-collector bookmarklet**: the admin opens the group's events page in their own logged-in browser, clicks the bookmarklet, and every `facebook.com/events/{id}` link on the page is copied to the clipboard for pasting into the batch field. Weekly routine: open each group's events tab → scroll → bookmarklet → paste → complete the new drafts (verify date, add routes and address) → publish. The routine is easy to forget; how to make it reliable or automate it: [#164](https://github.com/petyaferreira/vandrekalender-wp/issues/164).

### Sources still to research

- Confirm whether Sportstiming.dk has a public API.

---

## Part 2 — Field Mapping Strategy

Scraped data rarely maps cleanly to the event schema. Each source structures its events differently — one has a clear distance field, another buries it in a description paragraph, a third does not mention it at all. A three-layer approach handles this consistently across sources.

> **Reconciled with the real schema.** Distance, start time, cut-off time, and price live **inside `event_routes`** (an array of route objects), not as flat fields. `is_free` is **computed at read time** from route prices (never stored as meta), and `event_length` (Short/Medium/Long taxonomy) is **auto-assigned on save** from route distances. Geocoding uses **Adressevælger** (DAWA shut down on 1 October 2026). Field names use British spelling (`event_organiser_name`). Difficulty is **out of scope** (no difficulty field in the schema). The tables below use the real schema keys.

### Layer 1 — Direct field mapping

The simplest case: some fields map cleanly from a specific HTML element to a schema field. These are hardcoded per scraper class as a selector map. Every source has at least a title, a date, and a URL that map directly.

| Schema field | Maps from |
|---|---|
| `post_title` | Main heading element (e.g. `<h1 class="event-title">`) |
| `event_date` | A structured date element or `<time>` tag (`YYYY-MM-DD`) |
| `event_source_url` | The current page URL — always available |
| `event_place_name` | Meeting-point name or venue, if present |
| `event_address` | Address / meeting-point text, passed to the geocoder in Layer 2 |
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
| `event_address` → `event_lat` / `event_lng` / `event_municipality` | **Adressevælger** lookup | The geocoder searches the extracted address string, looks the chosen house number up, and returns coordinates **and** municipality. Same provider as manual event entry in the editor |

The free/paid state (`is_free`) and the `event_length` taxonomy are **not scraped** — `is_free` is computed at read time from `event_routes` prices and `event_length` is auto-assigned on save from route distances, the same as for manually created events. The scraper only needs to populate `event_routes` correctly; the save hook handles `event_length` and nothing needs to store `is_free`.

Pattern extraction runs after direct mapping. If a pattern matches, the field is populated; if not, the field is left null and passed to Layer 3.

### Layer 3 — Leave empty and flag

Some fields cannot be extracted from some sources. Rather than guessing or silently publishing incomplete data, missing fields are handled explicitly by severity:

| Missing field | Behaviour |
|---|---|
| Optional field (e.g. a route's `start_time`) | Field left null. Event still published. Card shows no badge for that field |
| `event_routes` distance | Route left without distance, event published. Card shows no distance badge ([no enrichment queue](decisions/0014-no-enrichment-queue.md)) |
| `event_lat` / `event_lng` (geocoding failed) | Event held as **draft** — coordinates required for the map view. Admin must resolve |
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
| `event_address` | 2 | HTML / regex | Passed to the geocoder |
| `event_lat` / `event_lng` | 2 | Adressevælger | Geocoded from address |
| `event_municipality` | 2 | Adressevælger | Code returned alongside coordinates, name from `data/municipalities.json` |
| `is_free` | — | Computed at read time | From `event_routes` prices — not stored, not scraped |
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

- `fetch(): string` — retrieves raw HTML. The base class provides `remote_get( $url, $headers = [] )`, a `wp_remote_get` wrapper with a 15-second timeout, a `Vandrekalender/1.0` user-agent, optional extra headers, and empty-string return on any non-200 / error.
- `parse( string $html ): array` — applies Layer 1 selectors and Layer 2 extraction, returning an array of event arrays. **This is where per-source work happens.** For a JSON source, `fetch()` can return the JSON and `parse()` decode it (Mammutmarch).

The base class then provides:

- `run(): int` — calls `fetch()`, then `parse()`, then `upsert_event()` for each result; returns the count created/updated. Afterwards it drafts stale events — see Removal below. If `fetch()` returned nothing, or no events were found, it records why as a warning and in `source_problem()` — see [Alerts](#alerts-when-a-source-breaks) below.
- `upsert_event( array $event ): bool` — see Deduplication below.
- `sideload_gpx( int $post_id, string $url ): int` — downloads a GPX file into the media library, attached to the event. See [GPX files](#gpx-files-route-maps) below.
- `record_warning( string $message ): void` — records a problem that does not stop the run; shown under the scraper's row in the Scraper Log (read by the scheduler with `take_warnings()`).
- `mark_source_url_seen( string $url ): void` — scrapers call this for every URL found in the source's listing, *before* fetching the detail page, so a temporarily unreachable detail page is not mistaken for a removed event. `run()` also marks the source URL of every parsed event automatically.

> Each scraper parses its fields (the Layer 2 regex) in its own `parse()` or small private helpers. The patterns follow each site's HTML, so there is no shared field-mapping code.

### Geocoding (server-side Adressevælger helper) — built

`Vandrekalender_Geocoder` (`includes/class-geocoder.php`) turns a free-text Danish address into `event_lat` / `event_lng` / `event_municipality` with Adressevælger, the same service the block editor's address search uses (`resources/event-meta-fields/index.js`, see `docs/data-model.md` → Event Location). It reads the token from `VANDREKALENDER_ADRESSEVAELGER_TOKEN` (see `docs/deployment.md` → Address search token).

Steps: search `/husnumre/soeg`, choose a house number hit, look it up with `/husnumre/{id}`, convert its EPSG:25832 point with `Vandrekalender_Utm_Converter` (`includes/class-utm-converter.php`, a line-for-line port of the editor's helper, so both give identical coordinates), and map the municipality code to a name with `Vandrekalender_Municipalities` (`data/municipalities.json`).

**Choosing the hit.** The search ignores a town written without a postcode: "Skovvejen 26, Brædstrup" ranks Skovvejen 26 in Slagelse first. So the hit is chosen by the input's locality:
- postcode in the input → the first hit with that postcode;
- only a town → the first hit whose locality contains the town (with up to 200 results, since the right town can rank far down: "Skolevej 5, Fanø" is hit 56);
- neither (a landmark or bare town name like "Æbelø" or "Kolding") → the first hit, but only when all hits share one postcode. The point is then approximate (somewhere in the right area, so the region is right), otherwise the input is ambiguous and there is no match.

A hit in the wrong town is rejected rather than pinned in the wrong part of the country, and foreign addresses ("Ostseebad Binz") find no match. A house number that does not exist ("Marselisborg Havnevej 1", the street has only even numbers) falls back to the nearest house on the same street and postcode. Checked against the coordinates DAWA had stored for every scraped address: all 21 matches within 3 km of DAWA's point (18 of 20 within 100 m), 20 of 21 with the same municipality (the other one DAWA had not found).

**Street not in the register (postcode fallback).** When neither search finds the street ("Kræmmerstien 1, 2740 Skovlunde", the street is not registered) but the address has a postcode, the pin goes to the centre of that postcode area: its outline comes from Datafordeler's DAGI register (GraphQL `DAGI/v2`, `DAGI_Postnummerinddeling`), the centre is the centroid of its largest polygon (`Vandrekalender_Wkt::area_centre()`), and the municipality is the nearest address's (the reverse lookup below). The result is marked approximate, so Mammut and Sportstiming set `event_location_approximate` (exact results set it to `false`), and the Scraper Log says "Street not found for …". The address must name the postcode's town after a comma (DAGI's `navn`; "København" matches "København V"): four digits can also be a Norwegian or Swiss postcode ("0154 Oslo") or a house number ("Industrivej 1050, Roskilde", "Industrivej 1050"), and those get no position. An address without a postcode still gets no position, since a guess could be anywhere. Checked on 21 October 2026: "Kræmmerstien 1, 2740 Skovlunde" → 55.7149, 12.3977 in Skovlunde, Ballerup.

**Caching.** Hits are cached for a month and genuine misses for an hour, as transients. Failed requests (network error, non-200, bad JSON, missing token) are **never** cached, so an outage does not hide addresses until a cache expires.

**Reverse lookup (coordinates → nearest address and municipality).** Adressevælger has no reverse geocoding, so `Geocoder::reverse()` uses Datafordeler's DAR register (GraphQL `DAR/v3`, key in `VANDREKALENDER_DATAFORDELER_API_KEY`, see `docs/deployment.md`):
1. Convert the point to EPSG:25832 (`Vandrekalender_Utm_Converter::from_lat_lng()`).
2. Ask for current address points (`DAR_Adressepunkt`, status 8) inside a square around it: 60 m, then 250 m, then 1 km, then 3 km half-side, until one has a usable hit. A page holds 200 points and comes back unordered, so when a page is full (the nearest point might be missing) the square is halved until the page is complete.
3. Look up the current house numbers (`DAR_Husnummer`, status 3) whose access point is among the nearest 100 (DAR's `in` filter limit), and take the nearest point that has one. Many points are road points or belong to retired house numbers.
4. Look that house number up in Adressevælger (`/husnumre/{id}`) for the address text and municipality code.

Results are cached for a month per ~10 m. `Geocoder::municipality_from_coords()` (DVL) is the municipality part of it, and the editor uses it through `GET /vandrekalender/v1/geocode/reverse?lat=&lng=`. That endpoint needs `edit_events` (administrators and event organisers; organisers have no `edit_posts`) and is limited to 60 lookups per user per 10 minutes (HTTP 429 after that), since anyone can sign up as an organiser and each call spends our Datafordeler quota. The key never reaches a browser. Each service retries once on a dropped connection, so during a real outage a lookup can take up to twice the timeout (Datafordeler 15 s → up to 30 s) before it gives up. Checked on 60 random DVL meeting points against the municipality DAWA had stored: 60 of 60 found, 59 the same. The one difference was Rødovre Station, whose nearest address officially lies in Hvidovre (same region). The nearest address was a median 27 m away, 948 m at most. Points with no Danish address within 3 km (abroad, at sea) get nothing.

**Place names: not built.** There is no lookup for landmark names ("Stevns Klint"); Opdag Verden no longer needs one (see its row above). See `docs/history/dawa-migration-plan.md` → Place names.

**Scraper Log.** Problems do not stop a scraper but are collected per run (`Geocoder::take_issues()`, plus the scraper's own `take_warnings()`, e.g. a failed GPX download) and shown under the run's row in **Events → Scraper Log**, and as warnings in `./scrape.sh`: a missing token, failed requests (with the HTTP status or error), unknown municipality codes, and the two lookups above.

**Repairing events from the outage.** `wp vandrekalender regeocode [--since=2026-10-01] [--dry-run]` repairs scraped events created since the given date: an address but no coordinates gets geocoded, and coordinates but no municipality (DVL) gets the reverse lookup. Locally it fixed 164 of 165 DVL events; the miss was a trip in Germany. It ran on production on 9 Oct 2026 (25 events fixed, 1 without a match). The command is kept on purpose: it only touches events missing coordinates or a municipality, so it is safe to run again after any future geocoder outage.

### Alerts when a source breaks

When a source's site moves or changes, its scraper usually does not fail: it just finds no events. Mammutmarch and Opdag Verden did this for a month in autumn 2026 and only showed "0 events" in the Scraper Log. So:

- `run()` sets `source_problem()` when `fetch()` returned an empty string ("Could not load the source…") or no source URL was seen ("The source loaded, but no events were found…"). Every source has always listed upcoming events, so either case almost always means the scraper needs fixing. The message is also a warning on the scraper's row in the Scraper Log and in `./scrape.sh`.
- `Scheduler::execute()` stores it as `problem` in the scraper's log entry. A scraper that throws gets its error message as `problem`.
- After a **cron** run (production only), `Vandrekalender_Scraper_Alerts::notify()` compares the sources with a `problem` against the ones stored in the `vandrekalender_scraper_alerts` option. It emails `kontakt@allevandreture.dk` (filter `vandrekalender_scraper_alert_email`) when a source newly breaks or works again, and sends nothing while a source stays broken, so a broken source gives one email, not one every night. If `wp_mail()` fails, the option is not updated and the next run tries again. Manual runs never email.
- Emails go through `wp_mail()` with the admin email as From. On production FluentSMTP sends them, as kontakt@allevandreture.dk (see `docs/deployment.md` → Email).

If a source ever legitimately lists no events for a while (between seasons), expect one "stopped working" email and one "works again" email when it lists events again.

### Adding a new scraper

1. Create `includes/scrapers/class-scraper-{source}.php`.
2. Extend `Vandrekalender_Scraper_Base`.
3. Implement `fetch()` — for an HTML listing usually `return $this->remote_get( self::SOURCE_URL );`; a JSON source can return the JSON for `parse()` to decode (Mammutmarch).
4. Implement `parse( $html )` — return an array of event arrays. Each must include at least `post_title`, `event_date`, and `event_source_url` (the dedup key). Call `$this->mark_source_url_seen( $url )` for each URL as it is discovered in the listing, so events removed at the source get drafted (see Removal below).
5. If the source links GPX files, set `gpx_source_url` on the matching route in `event_routes`. Never set `gpx_id` or `gpx_name`; the base class does (see [GPX files](#gpx-files-route-maps)).
6. If the source is one organisation (not a platform listing many), add its `SOURCE_NAME` to `Event::ORGANISER_SOURCES`, so its events get that `event_organiser` term.
7. Register the scraper in the `$scrapers` list in `Vandrekalender_Scraper_Scheduler::execute()` (`class-scraper-scheduler.php`).
8. Add a test on saved copies of the source, like `tests/Scrapers/DvlScraperTest.php` ([tests.md](contributing/tests.md)). DVL, Mammut, Opdag Verden and Sportstiming have tests so far.

### Deduplication

`upsert_event()` deduplicates on the **source URL**:

- If no `event_source_url` is present on the event array, the event is skipped.
- It queries for an existing event with the same `event_source_url`. If found and `event_claimed` is truthy, it is **skipped entirely** — a claimed event is owned by its organiser, who is the source of truth.
- Otherwise it updates the existing post, or inserts a new one published immediately.
- Reserved keys (`post_title`, `post_content`, `post_status`, `post_type`, `ID`) become post fields; every other key is written as post meta.
- After writing the scraper's fields, `upsert_event()` sets the source-tracking meta itself: `event_source = 'scraped'`, `event_scraped_at` = current time, and (on insert only) `event_claimed = false`. It also sets the `event_organiser` term from `event_source_name` ([data-model.md](data-model.md#event_organiser)).

### GPX files (route maps)

A route can carry a GPX track, shown on the event page by the Event Route Map block (see `docs/history/route-gpx-plan.md` and `docs/data-model.md` → `event_routes`). Scrapers only set a route's `gpx_source_url`, the URL of the `.gpx` file on the source site. After writing the meta, `upsert_event()` (`attach_route_gpx_files()`) fills in `gpx_id` and `gpx_name`:

- **Unchanged URL.** Stored routes are matched by route `id`. If the stored route has a file and the same `gpx_source_url`, its file is kept, so a source is downloaded once, not on every run. This also keeps a file someone chose with Replace in the editor (Replace leaves `gpx_source_url` alone).
- **New or changed URL.** If another route of the event already has a file for that URL, it is reused, so one GPX linked from several routes is downloaded once. Otherwise `sideload_gpx()` downloads it with `download_url()` and adds it with `media_handle_sideload()`, attached to the event. The GPX filters in `Vandrekalender_Gpx_Uploads` apply to sideloads too: the file must be a GPX track (`gpx` root and at least one point) under 5 MB. A URL that does not end in `.gpx` gets the extension added to the filename, since the type check goes by extension.
- **Download fails.** The route keeps its previous file and previous `gpx_source_url`, so the next run tries again. The failure appears as a warning in the Scraper Log.
- **No `gpx_source_url`.** A file attached by hand in the editor (stored without a source URL) is kept. A previously scraped file is dropped.
- **Cleanup.** Files attached to this event that no route uses any more (replaced, dropped by the source, or the route was removed) are deleted, so the media library does not fill up.

Like every other scraped field, this only happens on unclaimed events; claimed events are skipped entirely.

**Which sources link GPX files** (audit 13 October 2026, upcoming events at each source):

| Source | Pages checked | GPX links | Notes |
|---|---|---|---|
| Dansk Vandrelaug | 87 of 421 | None | No GPX, Komoot or Plotaroute links in the walk descriptions |
| Sportstiming | 14 of 14 | None | One event links an AllTrails map page (not downloadable without an account) |
| Opdag Verden | 7 of 7 | Members only | 3 events link a "hike route" (`/ruter/{slug}`) with `has_gpx: true`, but `/ruter/{slug}/gpx` answers 403 and `gpx_url` is `null` for visitors. Not scrapable |
| Mammutmarch | 3 of 3 (all Danish events) | None | The new site's admin has GPX and Komoot fields per distance, but no event fills them on the public pages |

So no scraper sets `gpx_source_url` today. The base support is ready for when a source starts linking GPX files: collect `<a>` elements whose `href` ends in `.gpx` (case insensitive, ignoring the query string), make them absolute with `WP_Http::make_absolute_url()`, and with several routes match a link to a route by the distance in its text or filename ("30 km", "30km", "_30_"). Komoot and Plotaroute links need an account to download, so skip them.

### Source URL changes

When a source moves its event pages, every upcoming event would get a new source URL. Since `upsert_event()` deduplicates by source URL, the next run would create a duplicate of each one and draft the original (its URL "disappeared"), losing the original's permalink. `adopt_legacy_source_url( $new_url, $date, $legacy_pattern )` prevents that: a scraper calls it for each event before returning it from `parse()`. If no post has the new URL yet, a post of the same source with the same date whose URL matches the old format gets the new URL, and `upsert_event()` updates it in place. Past events keep their old URL (no event at the source has their date) and stay as past events; the series key still links them to the next occurrence. Once every upcoming post has moved, the call finds nothing.

Used for both URL changes so far (October 2026): Mammutmarch `/<n>-km-marsch/<slug>/` → `/events/<slug>`, and Opdag Verden `/ture/dagsvandring/<region>/<slug>-<id>` → `/faellesture/<slug>#<date>`. Both calls can be removed once the scrapers have run on production and no upcoming post has an old-format URL.

### Recurring events (duplicate titles)

Some sources (DVL) publish a **separate page per occurrence** of a recurring walk — same title, same description, different date and URL. Each occurrence legitimately becomes its own event post (the calendar and filters need one post per date), but identical titles produce near-identical public pages that Google flags as duplicate content.

`upsert_event()` therefore disambiguates titles (`disambiguate_title()`): if another event post already uses the exact same title, the new occurrence's title gets the localised event date appended — `Motionstur fra Hareskov St. – 23. juli 2026` — which also makes the slug WordPress derives from it unique. The **first** occurrence keeps the clean title and URL; only subsequent twins are suffixed. The check runs on every upsert (excluding the post itself), so re-scraping an occurrence keeps its dated title stable.

### Removal (events cancelled at the source)

Every scraper's listing covers **all upcoming events** at its source, so after each run the base class (`unpublish_stale_events()`) moves to **draft** any published event of that source whose URL was not seen during the run — it was cancelled or removed at the source. Guard rails:

- **Past events are never touched by this sweep** — they drop out of "upcoming" listings naturally. Only events with `event_date` >= today are candidates. (Past events stay published — see Past-events cleanup below.)
- **Claimed events are never touched** — the organiser is the source of truth.
- **A failed or empty fetch drafts nothing** — if the run saw no URLs at all, the source is assumed unreachable rather than empty.
- **Draft, not delete**: the source-URL dedup matches drafts, so if the source re-lists the event (or the feed had a one-day glitch), the next run republishes the same post instead of creating a duplicate.

All meta keys use the **canonical, no-underscore schema keys** registered in `class-event.php` (`Vandrekalender\Event::META_*`) — the same keys the REST API and frontend read. A scraped event surfaces through exactly the same path as a manually created one. Writing `event_routes` and `event_municipality` also triggers the derive-on-save hooks, so `event_length` and `event_region` taxonomies are assigned automatically.

### Past-events cleanup

**Past events stay published.** Nothing drafts an event just because its date passed — `Vandrekalender_Scraper_Scheduler` no longer runs a cleanup sweep. That sweep (`cleanup_past_events()`, removed 2026-09-17) used to move every published, scraped, unclaimed event to **draft** once its `event_date` was more than 7 days past. It solved the duplicate-content problem recurring sources create (a fresh page per occurrence), but at the cost of turning every already-indexed URL into a 404 — Search Console showed hundreds of `Not found (404)` rows for it. See `docs/history/past-events-brief.md` for the full incident writeup.

Recurring occurrences are deduplicated differently instead, without touching publish status:

- **Series key.** `upsert_event()` sets `event_series_key` (`\Vandrekalender\Event::META_SERIES_KEY`) on every scraped event: `sanitize_title( event_source_name . ' ' . base_title )`, where `base_title` is the scraped title before `disambiguate_title()`'s date suffix. Recurring occurrences of the same walk share the same key. Manually created and claimed events never get one — they are never redirected. `wp vandrekalender backfill-series-key [--dry-run]` backfills posts scraped before this existed.
- **Redirect to the next occurrence.** On `template_redirect`, `Vandrekalender_Event_Past_Events` checks a singular past event for a series key; if a published sibling with the same key has `event_date >= today`, it 301-redirects there (same Polylang language only). This is what keeps recurring series from accumulating near-identical public pages — the old ones funnel forward instead of piling up.
- **One-off past events, and recurring ones with no next occurrence yet**, render normally: `Vandrekalender_Event_Past_Events` appends a "this walk has taken place" notice plus 3–5 upcoming walks nearby (same region, falling back to nationwide) via the `the_content` filter, so it's server-rendered and crawlable. The "Jeg kommer" join button is hidden on any past event via the existing `vandrekalender_event_is_joinable` filter.
- **Sitemap.** `Vandrekalender_Event_Sitemap` excludes redirecting past events (has a series key and a later published occurrence) from Rank Math's XML sitemap via `rank_math/sitemap/entry`, so crawlers aren't sent into a redirect. The ID set is computed with one query and cached, not derived per entry. Past one-off events stay in the sitemap.
- **Old `/event/` base.** `Vandrekalender_Legacy_Event_Urls` 301-redirects the pre-Polylang `/event/{slug}/` URLs (still indexed, still hit) to the current `/begivenhed/{slug}/` permalink; a second hop into the redirect above is expected and fine.
- **Restoring what the old sweep already drafted.** `wp vandrekalender restore-past-drafts [--dry-run]` republishes scraped, unclaimed drafts the old cleanup created — distinguished from a draft caused by the removal sweep above (`event_date` on or after `post_modified` means cancelled at the source, left alone) by `event_date` being at least 6 days before `post_modified` (the cleanup ran 7+ days after the event). It publishes with `wp_update_post` and verifies `post_name` didn't change.

**Known limitation: an admin can't remove a scraped event for good** while the source still lists it. `upsert_event()` finds events by source URL with `post_status => 'any'`, which leaves out trashed posts, and it always writes `post_status => 'publish'`. So on the next scrape a Draft event is published again, a permanently deleted one is created again, and a trashed one comes back as a new duplicate (the trashed copy stays in Trash). Only claimed events are left alone. No event has needed removing so far, so tombstones (a list of dismissed source URLs) are not built. If this becomes a problem, reopen [#36](https://github.com/petyaferreira/vandrekalender-wp/issues/36), which has the analysis and the open question about admin drafts versus drafts from the removal sweep.

Deleting events older than ~12 months and answering 410 Gone is not built either ([#37](https://github.com/petyaferreira/vandrekalender-wp/issues/37)).

> Resolved 2026-06-24: the base class previously used underscore-prefixed meta (`_event_source_url`, `_event_claim_status`) that the rest of the app never read. It now uses the registered `event_source_url` / `event_claimed` keys, and `event_source`, `event_source_url`, `event_source_name`, `event_scraped_at`, `event_claimed` are registered as REST-visible meta. The remaining claim-flow fields (`event_claimed_by`, `event_claimed_at`, claim tokens) are documented in `data-model.md` and will be registered with the claim flow ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)).

### Scheduling

`Vandrekalender_Scraper_Scheduler` (`includes/class-scraper-scheduler.php`):

- Registers a single cron hook, `vandrekalender_run_scrapers`, on WP-Cron's built-in **daily** schedule, at 02:12 site time, only where `VK_ENABLE_SCRAPING` is set (see [Running & scheduling](#running--scheduling)).
- `execute()` instantiates every scraper and calls `run()` on each, then records the run in the Scraper Log and, for cron runs, sends the alert email ([Alerts](#alerts-when-a-source-breaks)). All sources run on the same daily cadence.
- `reconcile_schedule()` on `init` schedules or clears the job to match the flag; deactivation clears it.

Trigger manually:

```bash
./wp.sh cron event run vandrekalender_run_scrapers
```

> **Not built:** per-source schedules instead of one shared daily run ([#39](https://github.com/petyaferreira/vandrekalender-wp/issues/39)).

---

## Not Built Yet

None of the following exists in code yet. Open work is tracked as GitHub issues; this section keeps the design notes and decisions.

### Admin scraping dashboard

**Built.** A **Scraper Log** screen exists under **Events → Scraper Log**
(`Vandrekalender_Scraper_Admin`), listing recent runs with their trigger
(cron/manual), duration, total events updated, and per-scraper counts or errors,
plus the current schedule status. Runs are stored in the `vandrekalender_scraper_runs`
option (capped, most recent first) by `Vandrekalender_Scraper_Log`.

A queue of events missing data, to fix by hand, will not be built
([decision 0014](decisions/0014-no-enrichment-queue.md)).

### Facebook scraping

**Decision (2026-06-24): no Graph API.** Not worth the cost. **Update (2026-07-03):** the site ships the paste-a-link importer instead (**Events → Add from Facebook**, see [Facebook](#facebook) above) — no Meta app, best-effort Open Graph prefill into a draft.

Reading event data through the Graph API needs a Meta Developer App plus App Review (weeks), and usually business verification and a privacy policy. The bigger problem is access: Meta has locked down public content, so reading events from Pages or groups you do **not** own is largely unavailable to third-party apps. The only reliable route is reading events for a Page that you or the organiser admin, using that Page's access token. Private groups (e.g. Vandring Danmark) and small local walking groups are effectively closed to outside apps, so their events come in through the bookmarklet routine above, manual entry, or the organiser.

Facebook-only events (e.g. AMA Vandringen) are handled by manual entry or by the organiser submitting/claiming the event. Revisit the API only if an organiser partnership makes a Page-token approach worthwhile. If pursued later: available fields are name, description, start_time, end_time, place (name + location), cover photo; distance is almost never structured, so Layer 2 regex on the description usually falls through to Layer 3.

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
**Events → Scraper Log**. Only cron runs send the alert email when a source
breaks — see [Alerts when a source breaks](#alerts-when-a-source-breaks).

## Key files

```
wp-content/plugins/vandrekalender-events/includes/
├── class-scraper-base.php          ← abstract base: fetch/parse/run/upsert_event/remote_get
├── class-scraper-scheduler.php     ← schedule (daily 02:12, prod-gated) + execute() run/log
├── class-scraper-log.php           ← rolling run history (vandrekalender_scraper_runs option)
├── class-scraper-alerts.php        ← emails kontakt@ when a source breaks or works again
├── class-scraper-admin.php         ← Events → Scraper Log admin screen
├── class-geocoder.php              ← server-side Adressevælger geocoding (address → lat/lng/municipality)
├── class-utm-converter.php         ← EPSG:25832 → lat/lng, same formula as the editor
├── class-municipalities.php        ← reads data/municipalities.json (code → name → region)
├── class-facebook-importer.php     ← Events → Add from Facebook paste-a-link importer
└── scrapers/
    ├── class-scraper-mammut.php        ← mammutmarch.dk
    ├── class-scraper-sportstiming.php  ← sportstiming.dk
    ├── class-scraper-dvl.php           ← dvl.dk (Dansk Vandrelaug)
    └── class-scraper-opdagverden.php   ← opdagverden.dk (Opdag Verden day walks)
```

Manual-run wrapper: `scrape.sh` (repo root). Production flag: `VK_ENABLE_SCRAPING`
in `wp-config.php`.

Related: `docs/data-model.md` (canonical schema — meta keys, taxonomies, `event_routes` shape, address search), `docs/authentication.md` (claim flow and organiser ownership).
