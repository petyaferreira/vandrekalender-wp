# Local dev

Set up and run the site on your machine. Checks before a PR: [code-standards.md](code-standards.md).

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

**Email (local):** every `wp_mail()` is caught by Mailpit instead of being delivered — read it at `http://localhost:${MAILPIT_PORT}` (8025). Wired at the PHP level (`sendmail_path` in the Dockerfile), so there is no SMTP plugin to activate and nothing in the database. See [deployment.md → Email](../deployment.md#email).

**WP-CLI:**
```bash
./wp.sh plugin list
./wp.sh post list --post_type=event
./scrape.sh                          # run all scrapers now, logged to Events → Scraper Log
```

**Plugins** are installed with Composer (`composer require wpackagist-plugin/<slug>`, then `./wp.sh plugin activate <slug>`), never with `./wp.sh plugin install` or the WP admin: plugin folders are not committed, so anything else is invisible to deploys and gone after the next `composer install`. Details: [deployment.md](../deployment.md).

## Reference projects (on this machine)

| Project | Path | What to reference |
|---|---|---|
| master-of-magic-wp | `/Users/petyanaydenova/Documents/petya/master-of-magic-wp` | Nordicway deploy workflow |
| paychex-wp | `/Users/petyanaydenova/Documents/paychex` | Composer config, pre-commit hook, build tooling, Interactivity API block patterns (`src/wp-content/plugins/paychex-blocks/`) |
