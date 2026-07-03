# Deployment

> Source of truth for CI/CD, environments, and deploy process.

---

## Installing Plugins and Themes

All free plugins and themes are installed via Composer using [WPackagist](https://wpackagist.org). Never install plugins manually through the WP admin.

```bash
# Add a plugin
composer require wpackagist-plugin/plugin-slug

# Add a theme
composer require wpackagist-theme/theme-slug

# Install everything (after cloning or after editing composer.json)
composer install
```

WPackagist is already configured as a repository in `composer.json`. The `composer/installers` package routes plugins to `wp-content/plugins/` and themes to `wp-content/themes/` automatically.

Currently installed via Composer:

| Package | Type | Notes |
|---|---|---|
| `wpackagist-plugin/safe-svg` | Plugin | Safe SVG uploads |
| `wpackagist-plugin/polylang` | Plugin | Multilingual — see `docs/i18n.md` |
| `wpackagist-theme/twentytwentyfive` | Theme | Default WP theme (kept as fallback) |

---

## Environments

| Environment | URL | How to deploy |
|---|---|---|
| Local | http://localhost:${WORDPRESS_PORT} | `./start.sh` |
| Staging | Nordicway staging | Push to `main` → GitHub Actions auto-deploys |
| Production | Nordicway production | GitHub Actions → workflow_dispatch → choose `production` |

---

## Scraper cron (production only)

The event scrapers run daily at **02:12 site time (Europe/Copenhagen)**, but only
on production. Two things enable it: a wp-config flag (turns on the WP-Cron
schedule) and a real system cron (actually fires it, since WP-Cron alone is
traffic-triggered). See `docs/scrapers.md` → *Running & scheduling* for the
application side.

**1. Production `wp-config.php`** — add both constants (above the "stop editing"
line):

```php
define( 'VK_ENABLE_SCRAPING', true );  // schedules the daily 02:12 scrape (prod only)
define( 'DISABLE_WP_CRON', true );     // stop traffic-triggered cron; the system cron drives it
```

`wp-config.php` lives in the **WordPress root** — one level **above**
`NORDICWAY_DEST_PATH` (that secret points at `wp-content`). So if the secret is
`/home/vandreka/public_html/wp-content`, the file is
`/home/vandreka/public_html/wp-config.php`. It is server-only (never in git, never
touched by deploys), so edits persist.

Local and staging omit `VK_ENABLE_SCRAPING`, so they never auto-run — locally the
pipeline is triggered by hand with `./scrape.sh`.

**2. System crontab** (Nordicway is cPanel; use `crontab -e` or cPanel → Cron Jobs).
The server clock is on Copenhagen time, so cron times equal site times — **no UTC
conversion needed**:

```cron
*/15 * * * * cd <wordpress-root> && /usr/local/bin/wp cron event run --due-now >> ~/logs/wp-cron.log 2>&1
```

Runs the WP-Cron catcher every 15 min; the 02:12 job fires within ~15 min and is
recorded in **Events → Scraper Log** as a `cron` run. The every-15-min cadence
also keeps any other scheduled jobs (scheduled posts, core update checks) working.
`<wordpress-root>` is the directory holding `wp-config.php` — the **parent** of
`NORDICWAY_DEST_PATH` (which points at `wp-content`), e.g.
`/home/vandreka/public_html`. Ensure the `~/logs/` directory exists.

**Verify:**

```bash
wp cron event list                              # 'vandrekalender_run_scrapers' shows next run 02:12
wp cron event run vandrekalender_run_scrapers   # force one run, then check Events → Scraper Log
```

---

## GitHub Actions Workflows

- `ci.yml` — triggers on push to `main`, calls the reusable deploy workflow targeting staging
- `deploy-to-nordicway.yml` — reusable workflow: builds assets, rsyncs, SCPs to Nordicway

### Deploy Steps

1. Checkout code
2. Node 20 setup
3. `npm ci && npm run build` in `wp-content/themes/vandrekalender-theme`
4. `npm ci && npm run build` in `wp-content/plugins/vandrekalender-events`
5. rsync theme + plugin into staging dir (excludes node_modules, src, resources, webpack configs)
6. Write SSH key + known_hosts
7. SCP to `NORDICWAY_DEST_PATH`
8. Post-deploy SSH sanity check

---

## Permalinks / rewrite rules

Rewrite rules flush **automatically** when any custom slug changes. The `vandrekalender-events` plugin bootstrap (`vandrekalender_events_maybe_flush_rewrite_rules` in `vandrekalender-events.php`, on `init` priority 99) builds a signature from every non-core post type and taxonomy's rewrite config plus the plugin version, and calls `flush_rewrite_rules()` once whenever that signature changes. After a deploy that touches a slug, the flush happens on the first request, so there is **no need** to manually open Settings → Permalinks and Save.

Because the signature is computed from all registered post types and taxonomies, new CPTs or taxonomies (e.g. the planned organiser taxonomy) are covered automatically with no code change. It only flushes when something changes, so normal requests pay nothing. Bumping `VANDREKALENDER_EVENTS_VERSION` also forces a one-time flush.

---

## Required GitHub Secrets

Set in repo Settings → Environments (`staging`, `production`):

- `NORDICWAY_SSH_KEY`
- `NORDICWAY_SSH_HOST`
- `NORDICWAY_SSH_PORT`
- `NORDICWAY_SSH_USERNAME`
- `NORDICWAY_DEST_PATH`

---

## Local Dev Commands

```bash
./start.sh                        # start Docker stack
./wp.sh plugin list               # WP-CLI inside container
./mysql-export.sh                 # export DB to timestamped .sql
./mysql-import.sh backup.sql      # import DB from file
```
