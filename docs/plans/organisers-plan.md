# Organisers: taxonomy, organiser pages and filters

Status: in progress (steps 1–2 done, step 3 in review). Issue: [#32](https://github.com/petyaferreira/vandrekalender-wp/issues/32). Owner: Petya. One PR per step, in order, as in [pull-requests.md → Planned features](../contributing/pull-requests.md#planned-features) (branches `feature/organisers-<n>-<short-name>`).

## Goal

An organiser becomes something events share, not only a text field. Visitors see who runs a walk, can filter the calendar by organiser and open an organiser page with all its walks (`/arrangor/<slug>`). Petya can filter the admin Events list by organiser.

Out of scope: members of an organisation, shared editing, "Post as" and claiming scraped events ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)); the real organiser of Sportstiming events ([#88](https://github.com/petyaferreira/vandrekalender-wp/issues/88)).

## Background

Today the organiser is the post meta `event_organiser_name` (plus `event_organiser_url`), a free string. Scrapers set it: DVL to `DVL <chapter>` (e.g. "DVL Herning Ikast"), Mammut, Opdag Verden and Sportstiming to their `SOURCE_NAME`. `docs/authentication.md` designs an `organizer` taxonomy for shared ownership; this plan builds only the taxonomy and what visitors see.

Region and length are the model to copy: taxonomies registered in `includes/event/class-event.php`, used by the REST filters (`build_query_args()` in `includes/class-event-rest-api.php`), the filter bar (`blocks/event-filters/`), and the cards, calendar and map blocks.

## Decisions

- **Taxonomy `event_organiser`**, like `event_region` and `event_length` (and the `event_organiser_*` meta). Public, `show_in_rest`, `show_admin_column`, rewrite slug `arrangor`, not hierarchical. Only admins assign terms (`assign_terms` → `manage_categories`); event organiser users cannot until #86.
- **One term per organisation.** All DVL events get the term **Dansk Vandrelaug**, whatever the chapter. Mammutmarch and Opdag Verden get their own term. Sportstiming events get no term until #88.
- **`event_organiser_name` stays** as the display name. On the event page a DVL walk still says "DVL Herning Ikast"; the link goes to the Dansk Vandrelaug page.
- **Manual and Facebook events** get a term only when Petya sets one in the editor. Without a term, the name shows as plain text, no link.
- **Scraper runs own the term** of scraped, unclaimed events, like their other fields.

## Step 1: taxonomy, scrapers and backfill

Nothing visible to visitors yet.

1. Register `event_organiser` in `class-event.php` (constant `TAX_ORGANISER`), as above. Admins pick the term in an **Organisation** field in the editor's existing Organiser panel; the default taxonomy panel is hidden (follow-up PR to step 1).
2. `upsert_event()` sets the term from `event_source_name` for the sources listed in `Event::ORGANISER_SOURCES` (DVL, Mammutmarch, Opdag Verden; the term has the source's name). Sportstiming is not listed. One list, used by the scrapers and the backfill.
3. `wp vandrekalender backfill-organiser [--dry-run]`, like `backfill-series-key`: for scraped events, set the term from `event_source_name` (skip Sportstiming); list manual and Facebook events without a term, so Petya can set them by hand.
4. Unit tests for the mapping (source name → term or none).
5. Docs: `data-model.md` (its `organizer` taxonomy sections become `event_organiser`; `email_domain` and `post_as` stay only in the `authentication.md` design, for #86), `authentication.md` (the design note says the taxonomy is built as `event_organiser` and links #86), `scrapers.md` (which sources get a term).

After merge: Petya runs the backfill on staging, then production (`--dry-run` first).

## Step 2: admin Events list

`show_admin_column` already adds the column. Add an organiser dropdown to the Events list filters (`restrict_manage_posts`); no term counts, since they count published events only and the list shows drafts too. Closes [#33](https://github.com/petyaferreira/vandrekalender-wp/issues/33).

## Step 3: event page info card

In `blocks/event-info-card/render.php`, an "Arrangør" row next to date and place: `event_organiser_name`, linked to the term page when the event has a term, plain text otherwise. Falls back to the term name if the meta is empty.

## Step 4: front-page filter

1. REST: an `organiser` parameter (term slugs, comma separated) in `filters_from_query()`, `extract_filters()`, `get_collection_params()` and a `tax_query` in `build_query_args()`, like `region`.
2. `blocks/event-filters/`: an "Arrangør" select like the region one, terms with at least one upcoming event, sorted by name.
3. Cards, calendar and map pass the filter on like `region` (they share `filters_from_query()`).
4. `frontend.md`: the filter bar section.

## Step 5: organiser page

A `taxonomy-event_organiser.html` template in the theme, from Petya's Claude Design mock-up: name, description (term description), website link (new term meta `organiser_url`, editable on the term screen), upcoming walks as cards. Check the term pages are in the sitemap and have sensible `<title>` and meta description. `sitemap.md`: `/arrangor/<slug>` built, plus the `/arrangor` archive if the mock-up has one.

## Step 6: organiser on the event cards

The organiser name on each card in `blocks/event-cards/render.php`, where the mock-up puts it. Decided with the mock-up; until then this step is open.

## Status

- Step 1: merged in [#92](https://github.com/petyaferreira/vandrekalender-wp/pull/92) (19 October 2026), with the editor field in [#93](https://github.com/petyaferreira/vandrekalender-wp/pull/93). Before it, the user role was renamed to `event_author` ([#90](https://github.com/petyaferreira/vandrekalender-wp/issues/90)), so it is not confused with `event_organiser`.
- Step 2: merged in [#94](https://github.com/petyaferreira/vandrekalender-wp/pull/94) (19 October 2026), closing #33.
