# Data Model

> Source of truth for the `event` custom post type — fields, taxonomies, REST API, and block editor implementation.

---

## Custom Post Type: `event`

Registered in `includes/event/class-event.php`.

- **Slug:** `event` (code always in English — URL slugs translated via Polylang, see `docs/i18n.md`)
- **Public URL slug:** `begivenhed` (Danish default), `event` (English)
- **supports:** `title`, `editor`, `excerpt`, `thumbnail`, `author`, `custom-fields`
- **has_archive:** `true` — enables `/begivenheder/` archive
- **show_in_rest:** `true` — required for block editor and REST API

---

## Core WordPress Fields

Native WP fields — no custom registration needed.

| Field | Type | Notes |
|---|---|---|
| `post_title` | string | Event name. Used to auto-generate the slug |
| `post_content` | blocks | Full event description. Rich text, images, headings |
| `post_excerpt` | string | Short summary for event cards |
| `post_author` | int | WP user ID of the creator |
| `post_name` | string | URL slug. Auto-generated from `post_title`. æ→ae, ø→oe, å→aa |

---

## Event Details — Post Meta

### `event_date`

| Field | Type | Filter? | Notes |
|---|---|---|---|
| `event_date` | string | ✅ Yes | Date the event takes place. Format: `YYYY-MM-DD`. Use `type: DATE` in `meta_query` for range filtering |

### `event_routes`

Array of route options for the event. Stored as JSON in post meta. Registered as `type: array`.

Each route object:

| Key | Type | Notes |
|---|---|---|
| `distance_km` | float | Distance in kilometres e.g. `30.0` |
| `start_time` | string | Departure time. Format: `HH:MM` |
| `cutoff_time` | int | Maximum allowed completion time in hours e.g. `8` |
| `price` | float | Price in DKK. `0` = free |

Example:

```json
[
  { "distance_km": 30.0, "start_time": "08:00", "cutoff_time": 8, "price": 0 },
  { "distance_km": 50.0, "start_time": "06:00", "cutoff_time": 12, "price": 250 }
]
```

Route display name is derived at render time — never stored: `{post_title} {distance_km} km` e.g. "Mammutmarch 50 km".

### `event_is_free`

| Field | Type | Derived | Filter? | Notes |
|---|---|---|---|---|
| `event_is_free` | boolean | ✅ Yes — on save | ✅ Yes | `false` if any route in `event_routes` has `price > 0`, otherwise `true`. Used as free/paid toggle filter on the frontend |

---

## Event Location — Post Meta

Address input uses **DAWA** (Danmarks Adressers Web API — `api.dataforsyningen.dk`) for autocomplete and geocoding. Free, official Danish government address register. Returns full address, municipality, and coordinates in one call.

| Field | Type | Required | Filter? | Notes |
|---|---|---|---|---|
| `event_place_name` | string | — | — | Optional human-readable name e.g. `Dyrehaven` or `Silkeborg Sti ved parkeringen`. Shown on event cards. Falls back to `event_municipality` if not set |
| `event_address` | string | ✅ | — | Full validated address string e.g. `I G Smiths Alle 12, 2650 Hvidovre`. Set via DAWA autocomplete |
| `event_lat` | float | ✅ | ✅ (map) | Latitude. Derived from DAWA on save. Used for map pins in the v1 map view. Proximity search filter deferred to v2 |
| `event_lng` | float | ✅ | ✅ (map) | Longitude. Derived from DAWA on save. Used for map pins in the v1 map view. Proximity search filter deferred to v2 |
| `event_municipality` | string | ✅ | — (v2) | Municipality name e.g. `Hvidovre`. Derived from DAWA on save. Used internally to assign `event_region` taxonomy term. Fallback display value on cards if `event_place_name` is not set. Municipality-level filtering deferred to v2 |

### Proximity Search (v2)

When a user searches "Valby", DAWA geocodes it to coordinates, then a custom SQL haversine query finds events within a given radius using `event_lat` / `event_lng`. Standard `meta_query` cannot do this. Deferred to v2.

---

## Media

| Field | Type | Required | Notes |
|---|---|---|---|
| Featured image | WP native | — | Standard WP featured image (`post_thumbnail`). Shown on event page and event cards. If not set, a placeholder image is shown — handled in the theme template |
| Additional images | WP native | — | Editors add extra images via native WP Gallery block inside `post_content`. No separate meta field — keeps display consistent across all events |

---

## Organiser — Taxonomy & Post Meta

### `organizer` Taxonomy

Custom taxonomy representing organizations (DVL, Mammutmarch, etc.). See `docs/authentication.md` for the full organization model.

Each organizer term carries:

| Meta Key | Type | Notes |
|---|---|---|
| `email_domain` | string | Organization's email domain (e.g. `dvl.dk`). Used for automatic user attachment and claim flow validation |

**Archive pages:** taxonomy term archive pages are served as `/arrangor/[slug]` public profile pages (native WordPress).

### Post Meta

| Field | Type | Required | Notes |
|---|---|---|---|
| `event_organiser_name` | string | — | Organiser display name. Retained for fallback/display when the organizer taxonomy is not set. For manually created events defaults to the WP author's `display_name`. For scraped events set by the scraper from the source data |
| `event_organiser_url` | string (url) | — | Organiser's website or Facebook page |
| `event_organiser_email` | string (email) | — | Contact email. Admin only — never displayed publicly |
| `post_as` | string (enum) | — | Organization membership posting context. Values: `organization` (default—event attached to the user's organizer term) or `private` (no organizer term attached, event visible only to author). Author-only control — only the post author can change this field |

---

## Scraping — Post Meta

All scraping fields are admin only — never visible to event creators or the public.

| Field | Type | Required | Notes |
|---|---|---|---|
| `event_source` | string (enum) | — | `manual` or `scraped`. Indicates how the event was created. Useful for filtering in WP admin |
| `event_source_url` | string (url) | ✅ (scraped) | Original URL scraped from. Used for deduplication — scraper skips if URL already exists |
| `event_source_name` | string | — | Human-readable source label e.g. `DVL.dk` or `Mammutmarch`. Shown in WP admin instead of raw URL |
| `event_scraped_at` | string (datetime) | — | Timestamp of last successful scrape. Useful for identifying stale events |
| `event_claimed` | boolean | — | Whether the organiser has claimed this event. Default: `false` |
| `event_claimed_by` | int | — | WP user ID of the organiser who claimed the event |
| `event_claimed_at` | string (datetime) | — | Timestamp when the event was claimed |
| `event_claim_token` | string | — | Single-use random token emailed to the organiser during the claim flow. Cleared after successful claim |
| `event_claim_token_expires` | string (datetime) | — | Expiry timestamp for the claim token. Token is invalid after 48 hours |

### Claim Flow

1. Organiser clicks "Claim this event" on the event page and enters their email
2. System checks that the email domain matches the domain in `event_source_url` — e.g. only a `@mammut-march.dk` email can claim an event scraped from `mammut-march.dk`. Request is rejected if domains don't match
3. A unique token is generated, stored in `event_claim_token` with a 48-hour expiry in `event_claim_token_expires`, and emailed to the organiser
4. Organiser clicks the link in the email — system validates the token is correct and not expired
5. On success: `event_claimed = true`, `event_claimed_by` and `event_claimed_at` set, token fields cleared
6. From this point the scraper will not overwrite the event — the base class skips claimed events

---

## Taxonomies

### `organizer`

Custom taxonomy representing organizations (e.g. DVL, Mammutmarch, individual organizers). Defines shared event ownership and team membership. See `docs/authentication.md` for the full organization and user role model.

- Each event is attached to exactly one organizer term (or none, if posted privately via the `post_as` field)
- Term meta `email_domain` enables automatic user attachment and claim flow validation
- Archive pages at `/arrangor/[slug]` are public organizer profile pages

### `event_region`

5 Danish regions. Auto-assigned on save by mapping the municipality returned by DAWA to its region. The full municipality → region mapping is hardcoded in the plugin.

| Term | Slug |
|---|---|
| Hovedstaden | `hovedstaden` |
| Sjælland | `sjaelland` |
| Syddanmark | `syddanmark` |
| Midtjylland | `midtjylland` |
| Nordjylland | `nordjylland` |

Used as the primary location filter on the frontend (v1). Municipality-level filtering deferred to v2.

### `event_length`

Auto-assigned on save from all `distance_km` values in `event_routes`. An event with routes of 8 km and 30 km gets both `short` and `medium` terms. Kept as a taxonomy so distance filtering is a fast SQL query rather than PHP-level JSON iteration.

| Term | Slug | Range |
|---|---|---|
| Short | `short` | 0–10 km |
| Medium | `medium` | 10–25 km |
| Long | `long` | 25+ km |

### Deferred to v2

- `event_category` — activity type (Walking, Running, Cycling etc.). Not needed in v1 since all events are walking
- `event_tags` — labels e.g. `dog-friendly`, `family-friendly`. Deferred until real user feedback indicates the need

---

## Filter Bar Summary

All filters for the frontend event listing. Each filter maps to either a meta field or a taxonomy term query.

| Field | Filter type | Notes |
|---|---|---|
| `event_date` | Date range picker | Presets: This weekend, This month, Next 3 months. Uses `meta_query` with `type: DATE` |
| `event_length` | Pills / checkboxes | Short / Medium / Long. Multiple selectable. Taxonomy query |
| `event_region` | Dropdown | 5 Danish regions. Taxonomy query |
| `event_is_free` | Toggle | Show free only / show all. Meta query |

### Deferred to v2

| Field | Filter type | Notes |
|---|---|---|
| `event_lat` / `event_lng` | Proximity search | Find events within X km of a searched location. Requires haversine SQL query |
| `event_municipality` | Dropdown | All Danish municipalities. More granular than region filter |
| `event_tags` | Multi-select | e.g. dog-friendly, family-friendly |

---

## REST API

Base namespace: `vandrekalender/v1`

### `GET /events`

Returns a filtered list of events. Used by all three frontend views (calendar, cards, map).

**Filter params:**

| Param | Type | Maps to |
|---|---|---|
| `date_from` | string (YYYY-MM-DD) | `event_date` meta, `>=` |
| `date_to` | string (YYYY-MM-DD) | `event_date` meta, `<=` |
| `length` | string | `event_length` taxonomy slug e.g. `short` |
| `region` | string | `event_region` taxonomy slug e.g. `midtjylland` |
| `is_free` | boolean | `event_is_free` meta |
| `lang` | string | `da` or `en`. Passed to Polylang via `WP_Query` `lang` arg |

**Response shape per event:**

```json
{
  "id": 42,
  "title": "Mammutmarch",
  "permalink": "https://vandrekalender.dk/begivenhed/mammutmarch",
  "date": "2026-06-20",
  "excerpt": "Årlig marchtur i Gribskov...",
  "featured_image": "https://vandrekalender.dk/wp-content/uploads/mammutmarch.jpg",
  "place_name": "Gribskov Naturpark",
  "lat": 56.051,
  "lng": 12.301,
  "is_free": false,
  "length": ["medium", "long"],
  "region": "hovedstaden"
}
```

---

### `GET /events/{id}`

Returns full detail for a single event. Used by the event detail page.

**Response shape:**

```json
{
  "id": 42,
  "title": "Mammutmarch",
  "permalink": "https://vandrekalender.dk/begivenhed/mammutmarch",
  "date": "2026-06-20",
  "content": "<p>Full description...</p>",
  "excerpt": "Årlig marchtur i Gribskov...",
  "featured_image": "https://vandrekalender.dk/wp-content/uploads/mammutmarch.jpg",
  "place_name": "Gribskov Naturpark",
  "address": "Esrum Møllegård, Esrum Klostervej 14, 3230 Græsted",
  "lat": 56.051,
  "lng": 12.301,
  "is_free": false,
  "length": ["medium", "long"],
  "region": "hovedstaden",
  "routes": [
    { "distance_km": 30.0, "start_time": "08:00", "cutoff_time": 8, "price": 0 },
    { "distance_km": 50.0, "start_time": "06:00", "cutoff_time": 12, "price": 250 }
  ],
  "organiser_name": "Mammutmarch",
  "organiser_url": "https://mammut-march.dk",
  "source_url": "https://mammut-march.dk/events/2026"
}
```

---

## Block Editor Implementation

All custom fields are added as panels in the block editor sidebar via `PluginDocumentSettingPanel`. The post content area is left free for the event description and images.

### Event Details panel

File: `resources/event-meta-fields/index.js`

| Field | UI component | Notes |
|---|---|---|
| `event_date` | `DatePicker` | Stores as `YYYY-MM-DD` |
| `event_routes` | Custom add/edit/remove list | Each route has `distance_km`, `start_time`, `cutoff_time`, `price`. Display name derived at render time as `{post_title} {distance_km} km` |
| `event_is_free` | Read-only derived display | Auto-derived from routes on save — not editable directly |

### Location panel

To be built.

| Field | UI component | Notes |
|---|---|---|
| `event_place_name` | `TextControl` | Optional, free text |
| `event_address` | `TextControl` with DAWA autocomplete | Triggers geocoding on selection. Populates `event_lat`, `event_lng`, `event_municipality` automatically |
| `event_lat`, `event_lng`, `event_municipality` | Hidden | Derived from DAWA — not shown to editor |

### Organiser panel

To be built.

| Field | UI component | Notes |
|---|---|---|
| `event_organiser_name` | `TextControl` | Defaults to WP author display name |
| `event_organiser_url` | `TextControl` | URL input |
| `event_organiser_email` | `TextControl` | Admin only — not shown on frontend |

### Taxonomy panels

`event_region` and `event_length` are auto-assigned on save and should not be manually editable by editors. Their default WP taxonomy panels in the sidebar should be hidden.
