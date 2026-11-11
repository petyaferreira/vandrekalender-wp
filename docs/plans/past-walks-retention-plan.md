# Past walks retention: implementation plan

Issue: [#37](https://github.com/petyaferreira/vandrekalender-wp/issues/37). Spec: [past-walks-spec.md, section 2](../design/passed-events-page/past-walks-spec.md#2-retention-walks-older-than-12-months). Status: not started. Owner: Petya.

Five steps, one PR each, in order, as [stacked PRs](../contributing/pull-requests.md#planned-features). Each step's issue says what to change and how to check it.

## Goal

A walk's life: **published** → **archived** after `RETENTION_MONTHS` (12) months, unless it has "Behold" → **deleted** 3 years after it was archived. An archived walk answers 410 Gone and is gone from every list, but its row and files stay, so it can be restored. Nobody has to act for this to happen.

## Decisions (on #37, recorded in [decision 0018](../decisions/0018-past-walks-archived-then-purged.md))

- 28 October 2026: a "Behold" flag on each walk, off by default; no separate admin screen, only a column and a filter in the Events list. Search Console checks are optional.
- 10 November 2026: archive first, delete 3 years later. Images and GPX files are deleted only with the walk, and only when nothing else uses them (editors can reuse one Media Library image on several walks).
- 10 November 2026: no new 301s. An archived recurring occurrence keeps the existing redirect to the next upcoming occurrence; every other archived walk answers 410.
- Month pages are not built yet, so the spec's "month pages return 410" needs no step here; it applies when they are built. The "Spring til måned" chips ([#198](https://github.com/petyaferreira/vandrekalender-wp/issues/198)) only show months inside the window.

## Steps

| Step | Issue | Branch |
|---|---|---|
| 1. "Behold" flag, "Due for removal" filter, one cutoff helper | [#265](https://github.com/petyaferreira/vandrekalender-wp/issues/265) | `feature/retention-1-keep-flag` |
| 2. `archived` status, 410 (or the series 301), out of every list, "Gendan" | [#266](https://github.com/petyaferreira/vandrekalender-wp/issues/266) | `feature/retention-2-archived-status` |
| 3. Weekly job archives old walks, `wp vandrekalender archive-old` | [#267](https://github.com/petyaferreira/vandrekalender-wp/issues/267) | `feature/retention-3-weekly-job` |
| 4. "Fjern nu" archives one walk right away | [#268](https://github.com/petyaferreira/vandrekalender-wp/issues/268) | `feature/retention-4-remove-now` |
| 5. Purge 3 years after archiving, files only when unused, `wp vandrekalender purge-archived` | [#269](https://github.com/petyaferreira/vandrekalender-wp/issues/269) | `feature/retention-5-purge` |

## Not in this plan

- **Privacy text.** The draft in `docs/business/gdpr-plan.md` now says walks are archived after 12 months and deleted 3 years later. The live page `/privatlivspolitik/` is edited in WordPress, not in the repo: Petya adds that paragraph once step 3 ([#267](https://github.com/petyaferreira/vandrekalender-wp/issues/267)) runs on production.
- The past walks page intro and its Rank Math description say "12 måneder" as plain text in the page content, not read from `RETENTION_MONTHS`. Edit them by hand if the setting changes.
- Keeping walks with Search Console traffic automatically (Search Console API). A separate issue if wanted.

When step 5 is merged: `docs/data-model.md` describes the lifecycle, and this plan moves to `docs/history/`.
