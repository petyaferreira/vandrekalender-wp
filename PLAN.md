# Vandrekalender — Project Plan
*Walking events calendar for Denmark. AI-first, from scratch to CI/CD and production.*

---

## Context

Vandrekalender is a WordPress-based platform that aggregates and displays walking events across Denmark. Events come from three sources: (1) scraped from organiser websites, (2) pulled via Facebook API/events, and (3) manually created by registered users. The long-term vision is a multi-country outdoor events network, but v1 is scoped strictly to **walking tours in Denmark**.

The project is built AI-first: CLAUDE.md, hooks, skills, and Linear tasks are set up before a single line of product code is written.

---

## Architecture Decisions

| Concern | Decision | Reason |
|---|---|---|
| Hosting | Nordicway | Existing working CI/CD pattern |
| Local dev | Docker (WordPress + MariaDB + phpMyAdmin) | Matches master-of-magic-wp pattern |
| WP setup | Standard WP core via Composer vendor + custom theme + custom plugin | Matches existing projects |
| Theme | Custom FSE block theme (`vandrekalender-theme`) | Modern WP, no page-builder dependency |
| Plugin | Custom blocks + events plugin (`vandrekalender-events`) | Single plugin for CPT, scraper, blocks, REST API |
| Scraping | PHP within WP (WP-Cron) | Keep stack simple, single repo |
| Build tooling | `@wordpress/scripts` (webpack) | Same as paychex-wp |
| Code standards | PHPCS (WordPress-Extra) + Prettier + pre-commit hooks | Same as both reference projects |
| CI/CD | GitHub Actions → Nordicway via SSH/SCP | Direct copy of master-of-magic-wp workflow |
| Project tracking | Linear (new account) | Lightweight for solo dev |

---

## Repository Structure

```
vandrekalender-wp/
├── CLAUDE.md                          ← AI context file (first thing created)
├── README.md
├── Dockerfile                         ← PHP 8.4 Apache + WP-CLI (from master-of-magic)
├── docker-compose.yml                 ← WP + MariaDB + phpMyAdmin
├── custom.ini                         ← PHP upload limits
├── .env.example                       ← Template (never commit .env)
├── start.sh                           ← docker compose up wrapper
├── wp.sh                              ← WP-CLI inside container wrapper
├── wp-su.sh                           ← WP-CLI as www-data (inside image)
├── mysql-export.sh
├── mysql-import.sh
├── composer.json                      ← PHPCS + dev tools + setup-hooks
├── package.json                       ← Prettier only at root
├── phpcs.xml                          ← WordPress-Extra standards
├── .prettierrc
├── .gitignore
├── setup-hooks.sh
├── .githooks/
│   └── pre-commit                     ← PHPCS on staged PHP files
├── .github/
│   └── workflows/
│       ├── ci.yml                     ← Push to main → staging
│       └── deploy-to-nordicway.yml    ← Reusable deploy workflow
├── .vscode/
│   └── settings.json
└── wp-content/
    ├── themes/
    │   └── vandrekalender-theme/
    │       ├── style.css              ← Theme header
    │       ├── theme.json             ← Design tokens, palette, typography
    │       ├── package.json           ← @wordpress/scripts build
    │       ├── resources/             ← Source JS + SCSS
    │       ├── public/                ← Built assets (gitignored)
    │       ├── templates/             ← FSE page templates
    │       ├── parts/                 ← Header, footer, sidebar
    │       └── patterns/              ← Block patterns
    └── plugins/
        └── vandrekalender-events/
            ├── vandrekalender-events.php      ← Plugin bootstrap
            ├── package.json           ← @wordpress/scripts for blocks
            ├── src/                   ← Block JS source (gitignored build output)
            ├── build/                 ← Compiled blocks (gitignored)
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

## Phase 0: Foundation (Do First)

### 0.1 Linear Setup
- Create Linear account at linear.app
- Create team: **Vandrekalender**
- Create project: **v1 — Denmark Launch**
- Labels: `infrastructure` `theme` `plugin` `scraping` `auth` `bug` `chore`
- Workflow states: Backlog → Todo → In Progress → In Review → Done
- Import all tasks below as Linear issues

### 0.2 GitHub Repository
- Create repo: `vandrekalender-wp` (private)
- Default branch: `main`
- Add secrets for Nordicway: `NORDICWAY_SSH_KEY`, `NORDICWAY_SSH_HOST`, `NORDICWAY_SSH_PORT`, `NORDICWAY_SSH_USERNAME`, `NORDICWAY_DEST_PATH`
- Add GitHub environment: `staging`, `production`

### 0.3 CLAUDE.md (Root)
Write `CLAUDE.md` covering:
- Project purpose and v1 scope
- Architecture decisions and why
- Local dev: `./start.sh` → browse to localhost
- WP-CLI: `./wp.sh plugin list`
- Build: `cd wp-content/themes/vandrekalender-theme && npm run build`
- Code standards: `composer run phpcs`, `composer run phpcbf`
- Deploy: push to `main` → auto-deploys staging via GitHub Actions
- Key files and what they do
- Custom Post Type fields reference

### 0.4 Docker Local Dev
Files to create (modelled on `master-of-magic-wp`):
- `Dockerfile` — PHP 8.4 Apache + WP-CLI + sudo
- `docker-compose.yml` — WordPress + MariaDB 10.6 + phpMyAdmin, volumes for vendor/wp-content
- `custom.ini` — upload limits 64M
- `.env.example` — PROJECT_NAME, ports, DB credentials
- `start.sh`, `wp.sh`, `wp-su.sh`, `mysql-export.sh`, `mysql-import.sh`

---

## Phase 1: WP Skeleton + Tooling

### 1.1 Composer Setup (root)
Modelled on `paychex-wp/composer.json`:
```json
{
  "name": "petya/vandrekalender",
  "require-dev": {
    "wp-coding-standards/wpcs": "^3.1",
    "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
    "squizlabs/php_codesniffer": "^3.11"
  },
  "scripts": {
    "phpcs": "./vendor/bin/phpcs",
    "phpcbf": "./vendor/bin/phpcbf",
    "format": "./node_modules/.bin/prettier --write",
    "setup-hooks": "bash setup-hooks.sh",
    "post-install-cmd": ["@setup-hooks"],
    "post-update-cmd": ["@setup-hooks"]
  }
}
```

### 1.2 Pre-commit Hook + PHPCS
- `setup-hooks.sh` — copies `.githooks/pre-commit` → `.git/hooks/pre-commit`
- `.githooks/pre-commit` — runs PHPCS on staged PHP files only
- `phpcs.xml` — WordPress-Extra, text-domain `vandrekalender`, excludes vendor/node_modules/build/public

### 1.3 Theme Skeleton (`vandrekalender-theme`)
- `style.css` with theme header (Theme Name: Vandrekalender)
- `theme.json` — colour palette (greens/earth tones for outdoors), fluid typography, spacing scale
- `package.json` with `@wordpress/scripts` start/build scripts (resources/ → public/)
- Blank `templates/index.html`, `templates/single-vandrekalender_event.html`
- Blank `parts/header.html`, `parts/footer.html`

### 1.4 Plugin Skeleton (`vandrekalender-events`)
- `vandrekalender-events.php` — main plugin file, loads includes
- Blank class stubs for each `includes/` file
- `package.json` for block compilation

---

## Phase 2: Events Data Model

### Custom Post Type: `vandrekalender_event`
File: `includes/class-event-post-type.php`

#### Taxonomies

| Taxonomy | Slug | Terms | Notes |
|---|---|---|---|
| Activity type | `vandrekalender_activity` | naturvandring, nattevandring, familietur, motionsvandring, … | v1 ships with a core set, extensible |
| Region | `vandrekalender_region` | Nordjylland, Vestjylland, Østjylland, Fyn, Sønderjylland, Sjælland, Nordsjælland, København, Bornholm, … | **Auto-derived** from start coordinates via reverse geocoding on save — never entered manually |
| Distance range | `vandrekalender_distance_range` | short (0–10 km), medium (10–25 km), long (25+ km) | **Auto-assigned on save from all routes** — an event with routes [10, 50, 100 km] gets all three terms: `short`, `medium`, `long`. This makes taxonomy filtering a fast SQL query. |
| Organiser type | `vandrekalender_organiser_type` | klub, forening, individuel | Distinguishes official club events from community/individual events |

> **Difficulty skipped for v1.** Region is a taxonomy term, not a meta field — auto-derived so it never needs manual input.

#### Meta Fields — Event Level (shared across all routes)

| Field | Type | Notes |
|---|---|---|
| `_event_date` | string | ISO 8601 date (YYYY-MM-DD) — time is per-route, not event-level |
| `_event_address` | string | Full address (street, zip, city) |
| `_event_location_name` | string | Human-readable display name |
| `_event_start_lat` | float | Shared start point latitude (most events start from same location) |
| `_event_start_lng` | float | Shared start point longitude |
| `_event_organiser` | string | Organiser name |
| `_event_source_url` | string | Original URL (scraped events) — used for deduplication |
| `_event_claim_status` | string | `unclaimed` / `claimed` |
| `_event_routes` | string (JSON) | Array of route/distance options — see structure below |

#### `_event_routes` JSON Structure

Each event has one or more route options (e.g. mammutmarch.dk: 75 km or 100 km on the same day). Each route is independent — its own distance, price, start time, cutoff time, and map. Stored as a JSON array in `_event_routes`. One WP post per event.

```json
[
  {
    "km": 75,
    "price": 250,
    "start_time": "13:00",
    "max_time": "24:00",
    "finish_lat": 55.851,
    "finish_lng": 12.571,
    "route_map_url": "https://...",
    "registration_url": "https://...",
    "description": "En udfordrende rute..."
  },
  {
    "km": 100,
    "price": 350,
    "start_time": "08:00",
    "max_time": "30:00",
    "finish_lat": 55.851,
    "finish_lng": 12.571,
    "route_map_url": "https://...",
    "registration_url": "https://...",
    "description": "Den lange rute..."
  }
]
```

- `km` — distance in kilometres
- `price` — price in DKK; `0` = free
- `start_time` — departure time for this route (HH:MM)
- `max_time` — maximum allowed completion time (e.g. `"30:00"` = 30 hours); can exceed 24h for ultra-distance events
- `finish_lat` / `finish_lng` — optional; omit for circular routes (same as event-level start)
- `route_map_url` — GPX file or image URL for this specific route
- `registration_url` — sign-up link for this specific route

**Computed on save (not user input):**
- `vandrekalender_distance_range` taxonomy terms — derived from **all** routes in the array. An event with [10, 50] km gets both `kort` and `lang` terms. This keeps distance filtering as a fast SQL taxonomy query rather than PHP JSON iteration.

#### Routes Admin UI

The WP admin meta box (and frontend event creation form) uses an add/remove list pattern for routes:

- **"Add route"** button opens a mini-form: `km`, `price`, `start_time`, `max_time`, `registration_url` (required fields), plus optional `description`, `route_map_url`, `finish_lat/lng`
- Confirming adds the route to a list below the button
- Each route in the list shows a summary row: `75 km — 13:00 — 250 kr — [Edit] [×]`
- **Edit** re-opens the mini-form pre-filled for that route
- **×** removes the route
- At least one route is required to save/publish
- On save: array serialised to `_event_routes` JSON; distance range taxonomy terms auto-assigned from all km values

Single event page renders a tab switcher when multiple routes exist (same pattern as mammutmarch.dk).

### REST API
File: `includes/class-event-rest-api.php`
- `GET /wp-json/vandrekalender/v1/events` — filter params: `date_from`, `date_to`, `region`, `distance_range`, `activity`, `organiser_type`, `is_free`
- `GET /wp-json/vandrekalender/v1/events/{id}`

---

## Phase 3: Frontend Calendar

### Block: Event Calendar (`vandrekalender-events/src/event-calendar/`)
- Uses `@fullcalendar/core` or a lightweight custom list view
- Fetches from REST API
- Renders events as cards with: title, date, distance, difficulty badge, location

### Block: Event Filters (`vandrekalender-events/src/event-filters/`)
- Region dropdown (`vandrekalender_region` taxonomy)
- Distance range pills: kort / mellem / lang (`vandrekalender_distance_range`)
- Activity type filter (`vandrekalender_activity`)
- Organiser type filter (`vandrekalender_organiser_type`)
- Free/paid toggle
- Date range picker
- Filters update URL params → calendar block re-fetches

### Theme Templates
- `templates/page-calendar.html` — Calendar + Filters blocks
- `templates/single-vandrekalender_event.html` — Event detail: map, description, organiser, sign-up CTA (v2)

---

## Phase 4: User Auth + Event Creation

- Standard WP user registration (email)
- Google OAuth via plugin (e.g. `nextend-social-login` via Composer, or custom implementation)
- Logged-in users can create events via frontend form (custom block or WP form)
- Event creation: activity type (walking), title, description, date/time, start location, distance, difficulty

---

## Phase 5: Scraping Pipeline

### Scheduler
File: `includes/class-scraper-scheduler.php`
- Registers WP-Cron events on plugin activation
- Runs each scraper on its own schedule (weekly default)

### Base Scraper
File: `includes/class-scraper-base.php`
- Abstract class: `fetch()`, `parse()`, `upsert_event()`
- Deduplication by source URL
- Creates `vandrekalender_event` posts with `_event_claim_status = unclaimed`

### Initial Scrapers (v1 — 2-3 sources)
- `class-scraper-loberdk.php` — løber.dk walking events
- `class-scraper-mammut.php` — mammut-march.dk

Each scraper:
1. Fetches HTML (wp_remote_get)
2. Parses with DOMDocument or regex
3. Calls `$this->upsert_event()` on base class

---

## Phase 6: CI/CD to Nordicway

Modelled directly on `master-of-magic-wp/src/.github/workflows/`:

### `ci.yml`
```yaml
on:
  push:
    branches: [main]
  workflow_dispatch:
jobs:
  deploy-staging:
    uses: ./.github/workflows/deploy-to-nordicway.yml
    with:
      environment: staging
      ref: ${{ github.ref_name }}
    secrets: inherit
```

### `deploy-to-nordicway.yml`
Steps:
1. Checkout code
2. Node 20 setup
3. `npm ci && npm run build` in `wp-content/themes/vandrekalender-theme`
4. `npm ci && npm run build` in `wp-content/plugins/vandrekalender-events`
5. rsync theme + plugin into `nordicway/` staging dir (exclude node_modules, src, resources, webpack configs)
6. Write SSH key + known_hosts
7. SCP `nordicway/` to `NORDICWAY_DEST_PATH`
8. Post-deploy SSH sanity check (`ls -lah` on remote)

Required secrets: `NORDICWAY_SSH_KEY`, `NORDICWAY_SSH_HOST`, `NORDICWAY_SSH_PORT`, `NORDICWAY_SSH_USERNAME`, `NORDICWAY_DEST_PATH`

---

## CLAUDE.md Skills & Hooks to Configure

### CLAUDE.md (root) — always available context for Claude Code sessions
- Project overview, v1 scope
- Local dev commands
- CPT field reference
- Scraper pattern (how to add a new scraper class)
- Deploy notes

### Hooks to configure (`.claude/settings.json`)
- `PostToolUse(Edit)` → run `phpcs` on changed PHP file and surface errors
- Allow `./wp.sh` commands without prompt
- Allow `docker compose` commands without prompt

---

## Linear Issue Breakdown (v1)

**Infrastructure**
- [ ] Create GitHub repo + branch protection
- [ ] Docker local dev setup (Dockerfile, docker-compose, scripts)
- [ ] Composer + PHPCS + pre-commit hooks
- [ ] Root package.json + Prettier config
- [ ] GitHub Actions CI/CD to Nordicway staging
- [ ] Configure Nordicway environment + secrets
- [ ] Write CLAUDE.md

**Theme**
- [ ] Theme skeleton (style.css, theme.json, FSE templates)
- [ ] Design tokens (colours, typography, spacing)
- [ ] Header + footer block parts
- [ ] Homepage template
- [ ] Event detail template
- [ ] Calendar page template
- [ ] Mobile responsive pass

**Plugin — Data Model**
- [ ] Plugin bootstrap file
- [ ] Custom post type `vandrekalender_event`
- [ ] Meta fields registration (`_event_distances` JSON array, all fields per plan)
- [ ] Taxonomies: `vandrekalender_activity`, `vandrekalender_region`, `vandrekalender_distance_range`, `vandrekalender_organiser_type`
- [ ] Auto-derive region from coordinates (reverse geocoding on save)
- [ ] Auto-assign distance range from `_event_distance_km` on save
- [ ] REST API endpoints (list + single) with new filter params

**Plugin — Blocks**
- [ ] Event Calendar block
- [ ] Event Filter block
- [ ] Event Card block pattern

**Auth & User Events**
- [ ] WordPress user registration (email)
- [ ] Google OAuth integration
- [ ] Frontend event creation form
- [ ] Event submission → draft or published logic

**Scraping**
- [ ] Scraper base class + scheduler
- [ ] løber.dk scraper
- [ ] mammut-march.dk scraper
- [ ] Deduplication logic
- [ ] Admin UI to view scraper run log

---

## Verification Checklist (end-to-end)

1. `./start.sh` → WP running at localhost
2. `./wp.sh plugin list` → vandrekalender-events active
3. Create event via WP admin → appears in REST API response
4. Calendar block renders events on frontend
5. Filters update event list without page reload
6. Manual event creation as logged-in user works
7. Run scraper manually via `./wp.sh cron event run vandrekalender_scrape` → events created
8. Push to `main` → GitHub Actions green → files appear on Nordicway staging
9. PHPCS pre-commit hook blocks a commit with a standards violation

---

## Files to Reference During Build

| Reference | What to copy/model |
|---|---|
| `master-of-magic-wp/Dockerfile` | WP-CLI + PHP 8.4 image |
| `master-of-magic-wp/docker-compose.yml` | 3-service local stack |
| `master-of-magic-wp/start.sh` + `wp.sh` | Shell script pattern |
| `master-of-magic-wp/src/.github/workflows/deploy-to-nordicway.yml` | Full CI/CD workflow |
| `paychex-wp/composer.json` | Dev dependencies + scripts |
| `paychex-wp/setup-hooks.sh` | Git hooks installer |
| `paychex-wp/.githooks/pre-commit` | PHPCS pre-commit hook |
| `paychex-wp/.prettierrc` | Prettier config |
| `paychex-wp/wp-content/themes/paychex/package.json` | @wordpress/scripts build |
