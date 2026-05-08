# Vandrekalender

Walking events calendar for Denmark. WordPress site that aggregates events from scrapers, Facebook, and user submissions.

**v1 scope:** Walking tours in Denmark only. No other sports, no other countries yet.

---

## Local Development

**Prerequisites:** Docker, Node.js 20+, Composer

```bash
# 1. Copy and configure environment
cp .env.example .env
# Edit .env — set a unique PROJECT_NAME and ports that don't conflict

# 2. Install PHP dev tools and set up git hooks
composer install

# 3. Start Docker stack
./start.sh
# WordPress:   http://localhost:${WORDPRESS_PORT}
# phpMyAdmin:  http://localhost:${PHPMYADMIN_PORT}

# 4. Build theme and plugin assets
cd wp-content/themes/vandrekalender-theme && npm install && npm run build
cd wp-content/plugins/vandrekalender-events && npm install && npm run build
```

**WP-CLI** (while Docker is running):
```bash
./wp.sh plugin list
./wp.sh option get siteurl
./wp.sh post list --post_type=vandrekalender_event
```

**Database:**
```bash
./mysql-export.sh              # exports timestamped .sql to project root
./mysql-import.sh backup.sql   # imports from file
```

---

## Code Standards

```bash
composer run phpcs             # check all PHP files
composer run phpcbf            # auto-fix where possible
composer run phpcs -- wp-content/plugins/vandrekalender-events/includes/class-event-post-type.php
```

Standards: WordPress-Extra ruleset. Text domains: `vandrekalender-theme`, `vandrekalender-events`.

A pre-commit hook (installed by `composer install`) blocks commits with PHP violations.

---

## Building Assets

Both theme and plugin use `@wordpress/scripts`:

```bash
# Theme
cd wp-content/themes/vandrekalender-theme
npm run start   # dev watch mode
npm run build   # production build → public/

# Plugin (Gutenberg blocks)
cd wp-content/plugins/vandrekalender-events
npm run start   # dev watch mode
npm run build   # production build → build/
```

Source files live in `resources/` (theme) and `src/` (plugin). Built output is gitignored.

---

## Deployment (CI/CD)

Push to `main` → GitHub Actions → auto-deploys to Nordicway **staging**.

Manual production deploy: GitHub Actions → workflow_dispatch → choose `production`.

Required GitHub secrets (set in repo Settings → Environments):
- `NORDICWAY_SSH_KEY`
- `NORDICWAY_SSH_HOST`
- `NORDICWAY_SSH_PORT`
- `NORDICWAY_SSH_USERNAME`
- `NORDICWAY_DEST_PATH`

The deploy workflow builds assets, strips source files via rsync, then SCPs to Nordicway.

---

## Repository Structure

```
vandrekalender-wp/
├── CLAUDE.md                    ← you are here
├── Dockerfile                   ← PHP 8.4 + WP-CLI
├── docker-compose.yml           ← WP + MariaDB + phpMyAdmin
├── .env.example                 ← copy to .env, never commit .env
├── start.sh / wp.sh             ← local dev helpers
├── composer.json                ← PHPCS dev tools + git hook setup
├── phpcs.xml                    ← WordPress coding standards config
├── .githooks/pre-commit         ← PHPCS on staged PHP files
├── .github/workflows/
│   ├── ci.yml                   ← trigger: push to main → staging
│   └── deploy-to-nordicway.yml  ← reusable deploy workflow
└── wp-content/
    ├── themes/vandrekalender-theme/
    │   ├── style.css            ← theme header (no styles here)
    │   ├── theme.json           ← design tokens
    │   ├── resources/           ← source JS + SCSS
    │   ├── public/              ← built assets (gitignored)
    │   ├── templates/           ← FSE page templates (.html)
    │   └── parts/               ← FSE template parts (.html)
    └── plugins/vandrekalender-events/
        ├── vandrekalender-events.php   ← plugin bootstrap
        ├── src/                        ← Gutenberg block source
        ├── build/                      ← compiled blocks (gitignored)
        └── includes/
            ├── class-event-post-type.php
            ├── class-event-meta.php
            ├── class-event-rest-api.php
            ├── class-scraper-scheduler.php
            ├── class-scraper-base.php
            └── scrapers/
                ├── class-scraper-loberdk.php
                └── class-scraper-mammut.php
```

---

## Custom Post Type: `vandrekalender_event`

Registered in `includes/class-event-post-type.php`.

| Meta key | Type | Description |
|---|---|---|
| `_event_date` | string | ISO 8601 datetime |
| `_event_distance_km` | number | nullable |
| `_event_difficulty` | string | `easy` / `moderate` / `hard` |
| `_event_location_name` | string | Human-readable location |
| `_event_lat` | number | Latitude |
| `_event_lng` | number | Longitude |
| `_event_organiser` | string | Organiser name |
| `_event_source_url` | string | Original URL (scraped events) |
| `_event_claim_status` | string | `unclaimed` / `claimed` |
| `_event_region` | string | Danish region |

Taxonomy: `vandrekalender_activity` (slug: `walking` for v1).

REST API: `GET /wp-json/vandrekalender/v1/events` — params: `date_from`, `date_to`, `region`, `difficulty`, `distance_max`.

---

## Adding a New Scraper

1. Create `includes/scrapers/class-scraper-{source}.php`
2. Extend `Vandrekalender_Scraper_Base`
3. Implement `fetch()` — returns raw HTML string
4. Implement `parse( $html )` — returns array of event arrays
5. Register in `class-scraper-scheduler.php`

Each event array passed to `upsert_event()` must include: `post_title`, `_event_date`, `_event_source_url` (used for deduplication), and any available meta fields.

---

## Reference Projects (on this machine)

| Project | Path | What to reference |
|---|---|---|
| master-of-magic-wp | `/Users/petyanaydenova/it-projects/wordpress/master-of-magic-wp` | Docker setup, Nordicway deploy workflow |
| paychex-wp | `/Users/petyanaydenova/it-projects/wordpress/paychex-wp` | Composer config, pre-commit hook, build tooling |
