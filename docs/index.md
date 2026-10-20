# How Vandrekalender works

These pages describe the product as it is now (source of truth). Open the one the task needs.

| Topic | Page |
|---|---|
| Data model — CPT, meta fields, taxonomies, REST API | [data-model.md](data-model.md) |
| Authentication — user roles, login methods, organizer taxonomy, team membership | [authentication.md](authentication.md) |
| Scraping pipeline — sources, patterns, how to add a scraper | [scrapers.md](scrapers.md) |
| Frontend — blocks, templates, filter bar, event cards | [frontend.md](frontend.md) |
| Sitemap and URL structure | [sitemap.md](sitemap.md) |
| Deployment, CI/CD, environments, installing plugins | [deployment.md](deployment.md) |
| Internationalisation — Polylang, translation functions, language matching | [i18n.md](i18n.md) |

## Other folders

| Folder | What is in it | Kept true? |
|---|---|---|
| [contributing/](contributing/index.md) | How we work: issues, PRs, code standards, local dev | Yes |
| [principles/](principles/index.md) | Rules for how we build, each with an example from this repo | Yes |
| [decisions/](decisions/index.md) | Decisions already taken, with the reason. Check before proposing a big change | Yes |
| `plans/` | Plans for features that need several PRs, while they are being built: [organisers](plans/organisers-plan.md) | Yes, until done |
| [design/](design/index.md) | Current design references | Yes |
| [business/](business/index.md) | Not about code: GDPR, naming, monetisation, pitch | Owned by Petya |
| [history/](history/index.md) | Frozen: finished plans and briefs, original specs, old mockups. Never a description of the code now | No |

## Code map

```
wp-content/plugins/vandrekalender-events/
├── vandrekalender-events.php   ← plugin bootstrap
├── includes/
│   ├── event/class-event.php   ← CPT, taxonomies, meta registration
│   ├── class-event-*.php       ← REST API, join flow, attendees, schema, sitemap, past events
│   ├── class-scraper-*.php     ← scraper base (upsert), scheduler, log, alerts, admin
│   ├── scrapers/               ← one class per source
│   └── …                       ← organiser fields, geocoder, municipalities, roles, GPX uploads, Facebook importer
├── blocks/                     ← Gutenberg blocks → build/blocks
├── resources/                  ← editor scripts (event meta fields) → build/resources
└── data/                       ← static data (municipalities.json)

wp-content/themes/vandrekalender-theme/
├── theme.json                  ← design tokens
├── templates/, parts/          ← FSE page templates, header + footer
└── resources/                  ← theme JS/SCSS → public/

tests/                          ← PHPUnit without WordPress (docs/contributing/tests.md)
├── wp-stubs.php                ← stand-ins for the WordPress functions the tests reach
├── fakes/                      ← fake geocoder, scrapers that read fixtures
├── fixtures/                   ← saved copies of source sites
├── Scrapers/                   ← one test per scraper (DVL so far)
└── Event/, Rest/               ← organiser terms and fields, REST query args

.github/workflows/
├── ci.yml                      ← Validate on every push/PR; staging deploy on main after Validate
└── deploy-to-nordicway.yml     ← reusable deploy workflow
```
