# Vandrekalender

Walking events calendar for Denmark. Aggregates events from scrapers, Facebook, and user submissions.

**v1 scope:** Walking tours in Denmark only.

---

## Docs (source of truth)

| Topic | File |
|---|---|
| Data model — CPT, meta fields, taxonomies, REST API | `docs/data-model.md` |
| Authentication — user roles, login methods, organizer taxonomy, team membership | `docs/authentication.md` |
| Scraping pipeline — sources, patterns, how to add a scraper | `docs/scrapers.md` |
| Frontend — blocks, templates, filter bar, event cards | `docs/frontend.md` |
| Sitemap and URL structure | `docs/sitemap.md` |
| Monetisation and business model | `docs/monetisation.md` |
| Deployment, CI/CD, environments | `docs/deployment.md` |
| Internationalisation — Polylang, translation functions, REST API lang filtering | `docs/i18n.md` |

---

## Local Dev

```bash
cp .env.example .env          # first time only
composer install              # installs PHPCS + git hooks
./start.sh                    # starts Docker stack
# WordPress:  http://localhost:${WORDPRESS_PORT}
# phpMyAdmin: http://localhost:${PHPMYADMIN_PORT}

cd wp-content/themes/vandrekalender-theme && npm install && npm run build
cd wp-content/plugins/vandrekalender-events && npm install && npm run build
```

**Build assets** (both use `@wordpress/scripts`):
```bash
# Theme: resources/ → public/
cd wp-content/themes/vandrekalender-theme
npm run start   # dev watch
npm run build   # production

# Plugin blocks: blocks/ → build/
cd wp-content/plugins/vandrekalender-events
npm run start   # dev watch
npm run build   # production
```

**WP-CLI:**
```bash
./wp.sh plugin list
./wp.sh post list --post_type=event
./scrape.sh                          # run all scrapers now, logged to Events → Scraper Log
```

---

## Code Standards

```bash
composer run phpcs    # check
composer run phpcbf   # auto-fix
```

WordPress-Extra ruleset. Text domains: `vandrekalender-theme`, `vandrekalender-events`. Pre-commit hook blocks violations on staged PHP files.

---

## Key Files

```
wp-content/plugins/vandrekalender-events/
├── vandrekalender-events.php        ← plugin bootstrap
└── includes/
    ├── event/class-event.php        ← CPT, taxonomies, meta registration
    ├── class-event-rest-api.php     ← REST endpoints
    ├── class-scraper-base.php       ← abstract scraper + upsert logic
    ├── class-scraper-scheduler.php  ← WP-Cron setup
    └── scrapers/
        └── class-scraper-mammut.php

wp-content/themes/vandrekalender-theme/
├── theme.json          ← design tokens
├── templates/          ← FSE page templates
└── parts/              ← header, footer

.github/workflows/
├── ci.yml                    ← push to main → staging
└── deploy-to-nordicway.yml   ← reusable deploy workflow
```

---

## Reference Projects (on this machine)

| Project | Path | What to reference |
|---|---|---|
| master-of-magic-wp | `/Users/petyanaydenova/it-projects/wordpress/master-of-magic-wp` | Docker setup, Nordicway deploy workflow |
| paychex-wp | `/Users/petyanaydenova/it-projects/wordpress/paychex-wp` | Composer config, pre-commit hook, build tooling |
