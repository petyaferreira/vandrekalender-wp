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

These are native WP fields, no custom registration needed.

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

| Field | Type | Derived | Notes |
|---|---|---|---|
| `event_is_free` | boolean | ✅ Yes — on save | `false` if any route in `event_routes` has `price > 0`, otherwise `true` |

Used as a filter on the frontend (free events toggle).

---

## Taxonomies

### `event_length`

Auto-assigned on save from all `distance_km` values in `event_routes`. An event with routes of 8 km and 30 km gets both `short` and `medium` terms.

| Term | Slug | Range |
|---|---|---|
| Short | `short` | 0–10 km |
| Medium | `medium` | 10–25 km |
| Long | `long` | 25+ km |

Used for distance range filtering. Kept as a taxonomy (not a meta field) so filtering is a fast SQL query rather than PHP-level JSON iteration.

### `event_category`

<!-- To be filled in — review Vandrekalender_DataModel_v2.docx -->

### `event_tags`

<!-- To be filled in — review Vandrekalender_DataModel_v2.docx -->

---

## Event Location — Post Meta

<!-- To be filled in next session — coordinates, municipality, region -->

---

## Organiser — Post Meta

<!-- To be filled in when reviewing Vandrekalender_DataModel_v2.docx -->

---

## Scraping — Post Meta

<!-- To be filled in when reviewing Vandrekalender_ScrapingSources_v2.docx -->

---

## REST API

<!-- Endpoint reference, filter params, response shape — to be filled in -->

---

## Block Editor Implementation

<!-- Which blocks cover which fields, custom blocks list — to be filled in -->
