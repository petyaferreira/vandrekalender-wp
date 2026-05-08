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

## Deploy

Push to `main` → auto-deploys to Nordicway staging via GitHub Actions.

For production: Actions → `Deploy to Nordicway` → choose `production`.

## See Also

[CLAUDE.md](CLAUDE.md) — full developer and AI context for this project.
