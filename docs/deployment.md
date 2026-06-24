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
