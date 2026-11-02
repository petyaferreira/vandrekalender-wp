# Local dev

Set up and run the site on your machine. Checks before a PR: [code-standards.md](code-standards.md).

```bash
cp .env.example .env          # first time only
composer install              # installs PHPCS + git hooks
./start.sh                    # starts Docker stack
# WordPress:  http://localhost:${WORDPRESS_PORT}
# phpMyAdmin: http://localhost:${PHPMYADMIN_PORT}

nvm use                       # Node version from .nvmrc (decision 0012)
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

## Clean up what you start

Whoever creates a worktree or a preview container removes it. Nobody else knows it is safe to delete.

**Preview containers.** The main stack serves the main checkout. To check a worktree's branch in the browser, an agent may start a second WordPress container that mounts the worktree's plugin and theme (for example on port 8081). Name it `vk-preview-<issue>`. It uses the **main database**, so anything it writes changes the main site too. Remove it in the same session, as soon as the check is done:

```bash
docker rm -f -v vk-preview-<issue>   # -v also drops its /var/www/html volume
```

**Worktrees.** When the PR is merged, remove the worktree and its local branch. `git branch -d` refuses an unmerged branch, so it doubles as a check:

```bash
git worktree remove <path> && git branch -d <branch>
```

`node_modules/` and `vendor/` are the only untracked files a worktree should have. If `git -C <path> status` shows anything else, ask Petya before removing it.

Why: in #176 a preview container (`vk-worktree`) kept serving the merged `past-tours` worktree against the main database for days after PR #190 merged. Four merged worktrees were left behind as well (#194).

## Reference projects

| Project | Where | What to reference |
|---|---|---|
| master-of-magic-wp | [petyaferreira/master-of-magic-wp](https://github.com/petyaferreira/master-of-magic-wp) (public) | Nordicway deploy workflow |
| paychex-wp | Private work repo, only on Petya's laptop (`~/Documents/paychex`). Agents elsewhere (CI, review bot, cloud) cannot read it | Composer config, pre-commit hook, build tooling, Interactivity API block patterns (`src/wp-content/plugins/paychex-blocks/`) |
