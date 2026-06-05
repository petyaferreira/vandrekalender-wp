# Vandrekalender

Walking events calendar for Denmark — [vandrekalender.dk](https://vandrekalender.dk)

## Quick Start

```bash
cp .env.example .env          # configure ports and DB credentials
composer install               # install PHP tools + set up git hooks
./start.sh                     # start Docker stack (WP + MariaDB + phpMyAdmin)
```

WordPress runs at `http://localhost:${WORDPRESS_PORT}` (default 8080).

## Build Assets

```bash
# Theme
cd wp-content/themes/vandrekalender-theme && npm install && npm run build

# Plugin blocks
cd wp-content/plugins/vandrekalender-events && npm install && npm run build
```

## Plugins & Themes

WordPress plugins and themes are managed via Composer using [WPackagist](https://wpackagist.org).
Composer-managed packages are **not committed** — they are installed fresh on every CI deploy.

**Add a plugin or theme:**
```bash
composer require wpackagist-plugin/akismet        # plugin from wordpress.org
composer require wpackagist-theme/twentytwentyfive # theme from wordpress.org
```

**Update a specific package:**
```bash
composer update wpackagist-plugin/safe-svg
```

**Update all packages:**
```bash
composer update
```

**Install after cloning (dev setup):**
```bash
composer install   # installs everything in composer.lock including wp-content packages
```

Commit `composer.lock` after any `require` or `update` so CI uses the exact same versions.

## WP-CLI

```bash
./wp.sh plugin list
./wp.sh cron event run vandrekalender_run_scrapers   # run scrapers manually
```

## Code Standards

```bash
composer run phpcs     # check PHP
composer run phpcbf    # auto-fix PHP
```

## Database

```bash
./mysql-export.sh              # export to .sql
./mysql-import.sh backup.sql   # import from .sql
```

**After importing a production dump**, check that `WORDPRESS_DB_PREFIX` in `.env` matches the table prefix in the dump. If WP-CLI reports a prefix mismatch, update `.env` and restart with `./start.sh` before continuing.

Then run a URL search-replace so WordPress uses your local URL instead of the live site:

```bash
./wp.sh search-replace 'https://vandrekalender.dk' 'http://localhost:8080' --skip-columns=guid
```

Replace `8080` with your actual `WORDPRESS_PORT` from `.env` if you changed it. The `--skip-columns=guid` flag preserves post GUIDs, which should keep pointing to the origin URL.

## Deploy

Push to `main` → auto-deploys to Nordicway staging via GitHub Actions.

For production: Actions → `Deploy to Nordicway` → choose `production`.

## See Also

[CLAUDE.md](CLAUDE.md) — full developer and AI context for this project.
