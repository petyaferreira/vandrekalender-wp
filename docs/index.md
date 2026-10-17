# How Vandrekalender works

These pages describe the product as it is now (source of truth). Open the one the task needs.

| Topic | Page |
|---|---|
| Data model — CPT, meta fields, taxonomies, REST API | [data-model.md](data-model.md) |
| Authentication — user roles, login methods, organizer taxonomy, team membership | [authentication.md](authentication.md) |
| Scraping pipeline — sources, patterns, how to add a scraper | [scrapers.md](scrapers.md) |
| Frontend — blocks, templates, filter bar, event cards | [frontend.md](frontend.md) |
| Sitemap and URL structure | [sitemap.md](sitemap.md) |
| Monetisation and business model | [monetisation.md](monetisation.md) |
| Deployment, CI/CD, environments, installing plugins | [deployment.md](deployment.md) |
| Internationalisation — Polylang, translation functions, language matching | [i18n.md](i18n.md) |
| Route GPX feature — plan, PR by PR (done) | [route-gpx-plan.md](route-gpx-plan.md) |
| DAWA → Adressevælger migration (address search) — plan, PR by PR (done) | [dawa-migration-plan.md](dawa-migration-plan.md) |

How we work (issues, PRs, local dev): [contributing/](contributing/index.md). Rules for how we build: [principles/](principles/index.md).

Background, not rules: briefs for finished work ([past-events-brief.md](past-events-brief.md), [migration-to-allevandreture.md](migration-to-allevandreture.md)), [AlleVandreture_GDPR_Plan.md](AlleVandreture_GDPR_Plan.md), naming research, HTML mockups, `shared/` (original .docx specs) and `history/` (the archived first build plan).

## Code map

```
wp-content/plugins/vandrekalender-events/
├── vandrekalender-events.php   ← plugin bootstrap
├── includes/
│   ├── event/class-event.php   ← CPT, taxonomies, meta registration
│   ├── class-event-*.php       ← REST API, join flow, attendees, schema, sitemap, past events
│   ├── class-scraper-*.php     ← scraper base (upsert), scheduler, log, alerts, admin
│   ├── scrapers/               ← one class per source
│   └── …                       ← geocoder, municipalities, roles, GPX uploads, Facebook importer
├── blocks/                     ← Gutenberg blocks → build/blocks
├── resources/                  ← editor scripts (event meta fields) → build/resources
└── data/                       ← static data (municipalities.json)

wp-content/themes/vandrekalender-theme/
├── theme.json                  ← design tokens
├── templates/, parts/          ← FSE page templates, header + footer
└── resources/                  ← theme JS/SCSS → public/

.github/workflows/
├── ci.yml                      ← Validate on every push/PR; staging deploy on main after Validate
└── deploy-to-nordicway.yml     ← reusable deploy workflow
```
