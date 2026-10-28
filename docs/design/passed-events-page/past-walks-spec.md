# Past walks page for allevandreture.dk: build spec

Split out of the [organiser page spec](../organiser-page/organiser-page-spec.md) (28 October 2026). Design files in this folder: `Tidligere.dc.html` (mobile) and `TidligereDesktop.dc.html` (desktop). Build issue: [#176](https://github.com/petyaferreira/vandrekalender-wp/issues/176); retention: [#37](https://github.com/petyaferreira/vandrekalender-wp/issues/37).

## 1. Past walks page (Tidligere ture)

Design files: `Tidligere.dc.html` (mobile) and `TidligereDesktop.dc.html` (desktop). Same theme.json tokens as [the organiser page spec, section 1b](../organiser-page/organiser-page-spec.md#1b-design-tokens-use-the-existing-themejson-only), nothing new. The month headings on mobile use the theme size extra-large (24px) and the walk title uses the theme size medium (18px).

Purpose: a plain archive for people who want to find a walk that already happened. It is a list, not a second calendar. Do not build a calendar view or a map view for this page.

* **URLs:** `/tidligere-ture/` is the archive. Month pages are `/tidligere-ture/2026/10/`. Further pages of one month or of the whole list are `/tidligere-ture/side/2/`. All of these are real URLs with plain links between them.
* **Tabs:** "Kommende ture" and "Tidligere ture" at the top. The current tab uses aria-current="page". Tabs are links, not JavaScript.
* **List:** grouped by month, newest first, with the month name and number of walks in each group header. Each row shows date, walk name, place, region, distance and organiser name (links to the organiser page). No images, no price chips, no "Gratis" label.
* **Desktop row:** grid with columns date, walk and organiser, place, distance, region. **Mobile row:** stacked, date on top, then name, then place and region, then a distance chip and the organiser link.
* **Row link:** the walk name links to the walk page when the walk page still exists. Otherwise it is plain text.
* **Filters:** search in name and place, region, organiser, and a reset link. Filters change the list without changing the canonical URL.
* **"Spring til måned":** links to the month pages inside the retention window, as plain links in the HTML. This also gives Google a clear path to every month.
* **Empty months are never shown.** A month only appears as a group in the list and as a chip in "Spring til måned" if at least one walk matches the current filters. Example: when the list is filtered to Mammutmarch, which only has walks in some months, only those months show up, and the chips are recalculated for that filter. The design files show this with gaps in the chip row (Okt, Sep, Jun, Maj, Mar, Nov).
* **Month pages with no walks do not exist.** Do not create or link an empty month page. A request for one returns 404 (410 if the month once had walks), and it is not in the sitemap. Chips on a filtered list keep the filter in the link (for example `?organiser=mammutmarch`), and the page canonicalises to the clean month URL.
* **"Nyere" and "Ældre" skip empty months.** They go to the next page or month that has walks. The "Side 1 af N" count is calculated from the filtered list only.
* **Pagination:** "Nyere" and "Ældre" links and "Side 1 af 9". 30 walks per page. They must work without JavaScript. The disabled link is not a link.
* **Count line:** "212 afholdte ture, nyeste først", derived from the data.
* **Call to action at the bottom:** "Lyst til at gå selv?" with a button to the upcoming walks.
* **Organiser page link:** the "Se flere tidligere ture" link on an organiser page goes to `/tidligere-ture/?organiser={slug}` (decided 28 October 2026: the same `organiser` parameter as the calendar filters).

Meta and headings:

* Archive title: `Tidligere vandreture i Danmark | Alle Vandreture`
* Archive meta: `Se vandreture, der allerede er afholdt de seneste 12 måneder: dato, sted, længde og arrangør.`
* Month page title: `Vandreture i {måned} {år}: {N} afholdte ture | Alle Vandreture`
* Month page meta: `Se de {N} vandreture, der blev afholdt i {måned} {år}. Find dato, sted, længde og arrangør.`
* One H1 ("Tidligere vandreture", or "Vandreture i {måned} {år}" on month pages). Month group headings are H2.
* Breadcrumb: Kalender, Tidligere ture (and the month on month pages), with BreadcrumbList markup.

Indexing:

* The archive, the month pages and numbered pages are indexable and in the sitemap.
* Filtered and search URLs (`?q=`, `?region=`, `?organiser=`) are noindex, follow, and canonical to the clean URL.
* Do not add Event markup for the archive list. Event markup belongs on the walk pages.

## 2. Retention: walks older than 12 months

The site removes walks 12 months after they happen. Treat 12 as a single setting (RETENTION_MONTHS), because the intro text on the archive page ("de seneste 12 måneder") must read from the same value.

What deletion does to SEO: deleted pages drop out of Google and lose their search traffic and links, but it does not lower the ranking of the rest of the site. The risks are lost traffic from walks that still rank, and organiser pages that become thin. So do the removal carefully:

* **Weekly job** finds walks older than RETENTION_MONTHS and lists them as candidates.
* **Keep flag:** before removal, check Google Search Console (clicks in the last 12 months) and backlinks. A walk with traffic or links gets a "behold" flag and is skipped. This check can be manual at first, but the flag must exist in the data model.
* **Removal:** set the walk status to archived. The walk URL returns 410 Gone (404 is acceptable if 410 is hard to do). Do not redirect old walks to the front page, because Google treats that as a soft 404.
* **Redirect only with a clear match:** a 301 is allowed when there is a real equivalent, for example a recurring walk with a newer page, or the organiser page for a one time walk with an organiser. Decide per case, never in bulk.
* **Cleanup:** remove archived walks from the XML sitemap, the archive lists, month pages, region pages, the organiser pages' "Tidligere ture", and from the derived counts. Fix internal links to the removed URL.
* **Month pages** older than the retention window return 410 as well, and the "Spring til måned" chips only show months inside the window.
* **Organiser pages:** after removals, an organiser may have no upcoming and no past walks. Follow [the organiser page spec, section 6](../organiser-page/organiser-page-spec.md#6-indexing-rules): keep the page indexable when it has about text, show the zero state, and set noindex, follow when it has neither text nor walks.
* **Early removal:** private guides can ask for their walks to be removed earlier. Support a manual "fjern nu" action in the admin.
* **Default decision:** no text only archive rows beyond 12 months. Add that later only if Search Console shows the old pages still matter.

Open question: do you want an extra "behold" list in the admin, or only the flag on each walk?

## 3. Definition of done

* The Tidligere ture archive and its month pages render from the design files, work without JavaScript, and have the meta, canonical and noindex rules from section 1.
* The 12 month removal job runs, respects the "behold" flag, returns 410 for removed walks, and cleans sitemap, lists and counts.
