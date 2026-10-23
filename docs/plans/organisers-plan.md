# Organisers: taxonomy, organiser pages and filters

Status: in progress (steps 1–4 done; step 5 replanned on 21 October 2026, in review). Issue: [#32](https://github.com/petyaferreira/vandrekalender-wp/issues/32). Owner: Petya. One PR per step, in order, as in [pull-requests.md → Planned features](../contributing/pull-requests.md#planned-features) (branches `feature/organisers-<n>-<short-name>`).

## Goal

An organiser becomes something events share, not only a text field. Visitors see who runs a walk, can filter the calendar by organiser and open an organiser's page with its walks and its own texts (`/arrangoerer/<slug>/`), or the overview of all organisers (`/arrangoerer/`). Petya can filter the admin Events list by organiser.

Out of scope: members of an organisation, shared editing, "Post as" and claiming scraped events ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)); the real organiser of Sportstiming events ([#88](https://github.com/petyaferreira/vandrekalender-wp/issues/88)).

## Background

Today the organiser is the post meta `event_organiser_name` (plus `event_organiser_url`), a free string. Scrapers set it: DVL to `DVL <chapter>` (e.g. "DVL Herning Ikast"), Mammut, Opdag Verden and Sportstiming to their `SOURCE_NAME`. `docs/authentication.md` designs an `organizer` taxonomy for shared ownership; this plan builds only the taxonomy and what visitors see.

Region and length are the model to copy: taxonomies registered in `includes/event/class-event.php`, used by the REST filters (`build_query_args()` in `includes/class-event-rest-api.php`), the filter bar (`blocks/event-filters/`), and the cards, calendar and map blocks.

## Decisions

- **Taxonomy `event_organiser`**, like `event_region` and `event_length` (and the `event_organiser_*` meta). Public, `show_in_rest`, `show_admin_column`, not hierarchical. Its rewrite slug was `arrangoerer` (Danish "arrangører" in ASCII; `arrangor` for its first day, with no redirect: hardly visited) until step 5a ([#122](https://github.com/petyaferreira/vandrekalender-wp/pull/122)) gave that URL to the organiser post type; since then it has no archive and is not publicly queryable. Only admins assign terms (`assign_terms` → `manage_categories`); event organiser users cannot until #86.
- **One term per organisation.** All DVL events get the term **Dansk Vandrelaug**, whatever the chapter. Mammutmarch and Opdag Verden get their own term. Sportstiming events get no term until #88.
- **Visitors see the organisation:** the info card and event cards show the term name ("Dansk Vandrelaug") with a round badge, linked to its page, as in the design. The badge shows the organiser's initials until there is a logo. `event_organiser_name` ("DVL Herning Ikast") stays for events without a term, and in the admin.
- **Manual and Facebook events** get a term only when Petya sets one in the editor. Without a term, the name shows as plain text, no link.
- **Scraper runs own the term** of scraped, unclaimed events, like their other fields.
- **Design:** `docs/design/organiser-page/` (Claude Design, 19 October 2026: mobile, desktop, cards, and a build spec). Where the spec differs from this plan, this plan wins: events use a taxonomy, and the page is a post linked to it, edited in the block editor instead of the spec's admin fields ([decision 0008](../decisions/0008-organiser-pages-as-post-type.md)); our own JSON-LD ([decision 0004](../decisions/0004-own-event-json-ld.md)), not Rank Math's; `/arrangoerer/` as in the spec.
- **No hard-coded numbers:** every count on the organiser pages (upcoming walks, regions, km range, free walks, next walk) is computed from the events at render time, in blocks.

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

In `blocks/event-info-card/render.php`, an "Arrangør" row after Place: with a term, its badge and name linked to the organiser page ("Dansk Vandrelaug ›"); without one, `event_organiser_name` as plain text; neither, no row.

## Step 4: front-page filter

1. REST: an `organiser` parameter (term slugs, comma separated) in `filters_from_query()`, `extract_filters()`, `get_collection_params()` and a `tax_query` in `build_query_args()`, like `region`.
2. `blocks/event-filters/`: an "Arrangør" select like the region one, terms with at least one upcoming event, sorted by name.
3. Cards, calendar and map pass the filter on like `region` (they share `filters_from_query()`).
4. `frontend.md`: the filter bar section.

## Step 5: organiser pages

An organiser's page is a post of the post type `organiser`, linked to its `event_organiser` term and edited in the block editor ([decision 0008](../decisions/0008-organiser-pages-as-post-type.md)). The page, top to bottom:

1. **Hero:** name (post title), logo (featured image; initials badge without one), "Officiel arrangør" badge (only when flagged), short description, "Besøg hjemmeside" button.
2. **Arrangøren i tal:** upcoming walks, regions, km range. Computed.
3. **Kommende ture:** heading with the number of walks, the filter bar, list, map and calendar with "Vis flere": the front page's blocks, limited to the organiser. Computed. Anchor `#kommende-ture`.
4. **About**, two columns: heading, text and image | "Ture i hele landet", the regions with upcoming walks and their counts, each linking to this page with that region filtered (`?region=<slug>#kommende-ture`). Computed right column.
5. **FAQ:** heading and core Accordion block.
6. **Andre arrangører:** the other organisers with a page. Computed, in the template, not in the post.

Dropped from the design: "Tidligere ture", the free walks count, the "Næste tur" link, the facts box (Afdelinger, Kontakt …), the source line and the generated summary sentence.

One PR each:

- **5a. Post type and link.** Post type `organiser` ("Arrangør"/"Arrangører"), rewrite `arrangoerer` with archive `/arrangoerer/`, in REST, supports title, editor, thumbnail, excerpt and revisions; only admins create and edit (#86 opens it to organisations). Post meta: the linked term (a select in the editor sidebar; one post per term) and the official flag (admins only). The taxonomy loses its rewrite: `term_link` returns the organiser's post when it is published, else the calendar filtered by organiser (`/?organiser=<slug>`), and an old `/arrangoerer/<slug>/` without a post redirects there. The info card's link (step 3) follows automatically. Unit tests for the pure parts; docs: `data-model.md`, `sitemap.md` (organiser posts instead of term archives), `authentication.md` (the post is what #86 lets organisations edit).
- **5b. Template and section patterns.** `single-organiser.html` (header, breadcrumb, post content, footer); theme patterns `organiser-hero`, `organiser-stats`, `organiser-walks`, `organiser-about` and `organiser-faq` as above, with placeholders where a 5c block goes. A new organiser post starts from them, locked to content only (texts, images, links, FAQ). The cards, calendar, map and filter blocks preset the organiser from the post's term, as they do on a term archive today. Zero state when there are no upcoming walks.
- **5c. Computed blocks.** Stats (upcoming walks, regions, km range), the regions table with filtered links, and "Andre arrangører". All from the linked term, live.
- **5d. Overview `/arrangoerer/`.** The post type archive (`archive-organiser.html`): every organiser with a page, with logo or initials, upcoming walks and regions. The breadcrumb and "Se alle arrangører" link there.
- **5e. SEO.** Title and meta description from the spec (section 5) through Rank Math's filters, the excerpt as the description fallback; Organization and ItemList JSON-LD in our own schema class; noindex for a page with no text and no walks. Together with the event-page templates in [#97](https://github.com/petyaferreira/vandrekalender-wp/issues/97).

Content: Petya writes each page on production, from the organisers' own websites; the spec's drafts have `[FYLD UD]` gaps, none of which may go live.

## Step 6: organiser on the event cards

Variant B of the design: under the title, a line with the badge and the organiser name linking to its page. The whole card stays a link to the walk (stretched link), with the organiser link above it, so there is no link inside a link. Touch target at least 32 px. The front page's "Officielle arrangører" strip links to the organiser pages.

## Status

- Step 1: merged in [#92](https://github.com/petyaferreira/vandrekalender-wp/pull/92) (19 October 2026), with the editor field in [#93](https://github.com/petyaferreira/vandrekalender-wp/pull/93). Before it, the user role was renamed to `event_author` ([#90](https://github.com/petyaferreira/vandrekalender-wp/issues/90)), so it is not confused with `event_organiser`.
- Step 2: merged in [#94](https://github.com/petyaferreira/vandrekalender-wp/pull/94) (19 October 2026), closing #33.
- Step 3: merged in [#95](https://github.com/petyaferreira/vandrekalender-wp/pull/95) (20 October 2026). The rewrite slug moved from `arrangor` to `arrangoerer` in [#98](https://github.com/petyaferreira/vandrekalender-wp/pull/98) (20 October 2026). Backfill run on staging and production.
- Step 4: merged in [#108](https://github.com/petyaferreira/vandrekalender-wp/pull/108) (20 October 2026).
- Step 5: replanned in [#113](https://github.com/petyaferreira/vandrekalender-wp/pull/113) (21 October 2026, docs only): the page became a post type after trying term meta with a synced pattern and theme pattern files (see decision 0008).
  - 5a: post type, term link and redirect in [#122](https://github.com/petyaferreira/vandrekalender-wp/pull/122).
  - 5b: template, section patterns, Organiser Logo and Official Organiser Badge blocks, and the walk blocks pinned to the organiser, in [#130](https://github.com/petyaferreira/vandrekalender-wp/pull/130). Stats and Regions are registered as empty blocks so pages written now already have them; 5c fills them in. Publishing a page now needs its organiser. Admin labels are "Arrangørsider"/"Arrangørside", so the menu is not confused with the taxonomy's "Arrangører".
  - 5c: Organiser Stats (upcoming walks, regions, km range), Organiser Regions and Other Organisers ("Andre arrangører", in the template), computed per request in `Organiser_Walks`, in [#162](https://github.com/petyaferreira/vandrekalender-wp/pull/162). "Se alle arrangører" comes with the overview in 5d.
  - 5d: overview `archive-organiser.html`; Other Organisers became Organiser List with `show` "others" or "all", so both lists share one card; "Se alle arrangører" under "Andre arrangører"; the overview's title is "Arrangører", in PR #TBD.
