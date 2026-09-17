# Brief: keep past event pages public (stop drafting past events)

Written 17 September 2026 in a planning session, for Claude Code to implement. Petya is learning: explain each step in plain terms before doing it, work one step at a time, verify after each step. Local Docker first, then staging, then production. Update `docs/scrapers.md` (Past-events cleanup section) and `docs/sitemap.md` when done.

## Why

Search Console and the server access log (17 Sep 2026) show Googlebot hitting many 404s on event URLs: 110 on `/begivenhed/...` and 59 on the old `/event/...` base, plus bingbot. Cause: `Vandrekalender_Scraper_Scheduler::cleanup_past_events()` moves scraped, unclaimed events to **draft** 7 days after their date. A draft is not public, so every URL Google already indexed becomes a 404. On production there are now 583 published and 503 draft events. Example: ID 274 `sommertur-ved-kalo` is a draft and returns 404.

The cleanup was added because recurring sources (DVL weekly walks) create one near-identical page per occurrence, which Google flags as duplicate content. That problem is real and must stay solved. Example series on production: `tirsdagstraveren` (240, publish), `tirsdagstraveren-2` (242, draft), `tirsdagstraveren-3` (244, draft), `tirsdagstraveren-4-august-2026` (1358, draft), `tirsdagstraveren-11-august-2026` (1360, draft), `tirsdagstraveren-18-august-2026` (1362, publish, although past by more than 7 days; find out why while you are in there).

AI search crawlers (OAI-SearchBot, ChatGPT-User, Claude-User, ClaudeBot) already read the site, so stable public URLs matter for them too.

## Decisions (Petya, 17 Sep 2026)

1. Past events stay **published**. Nothing drafts an event just because its date passed.
2. **Recurring walks:** a past occurrence page **301-redirects to the next upcoming occurrence** of the same walk. If no upcoming occurrence exists, the page stays and shows the "afholdt" notice (point 3). When a new occurrence appears later, the redirect starts working automatically.
3. **One-off events** (and recurring ones with no next date): the page stays public with a clear notice, "Denne tur er afholdt" (English: "This walk has taken place"), and a short list of upcoming walks in the same region (fallback: nationwide), 3 to 5 items.
4. **Restore** the events the cleanup already drafted (past, scraped, unclaimed). Events drafted because they were cancelled or removed at the source stay drafts.
5. Keep `unpublish_stale_events()` as it is (cancelled upcoming events still go to draft).
6. Keep `Vandrekalender_Event_Schema` as it is (past events output no Event JSON-LD).

## Implementation

### 1. Stop the drafting
Remove the call to `cleanup_past_events()` in the scheduler and delete the method, or turn it into a no-op with a comment pointing here. Remove its row from the Scraper Log.

### 2. Series key
Recurring occurrences share a title; `disambiguate_title()` appends " – j. F Y" to later ones (older ones got `-2`, `-3` slugs before that commit, but their titles are identical). Add meta `event_series_key`:
- Value: `sanitize_title( event_source_name . ' ' . base_title )`, where `base_title` is the scraped title **before** the date suffix.
- Set it in `upsert_event()` for every scraped event.
- Backfill existing events (all statuses) with a WP-CLI command. For existing posts derive `base_title` by stripping a trailing ` – {day}. {Danish month} {year}` from `post_title`.
- Add the constant to `\Vandrekalender\Event` and document it in `docs/data-model.md`.
- Manually created events and claimed events get no series key (they are never redirected).

### 3. Behaviour on a past event page
On `template_redirect`, for a singular `event` whose `event_date` < today (site timezone):
- If it has a series key: find the published event with the same key and the earliest `event_date` >= today. If found, `wp_safe_redirect( get_permalink( $next ), 301 )` and exit. Respect Polylang: only redirect to an event in the same language.
- Otherwise render the page normally, with the notice and the upcoming list from decision 3. Prefer a small server-rendered block or a `render_block` hook in `single-event.html` so it is visible without JS and crawlable. Translatable strings, text domain `vandrekalender-events`.
- Hide or disable the "Jeg kommer" join button on past events.

### 4. Listings must show only upcoming events
Now that past events are published again, check **every** place that lists events and make sure it filters `event_date >= today` by default: calendar and filter bar (REST API, see `class-event-rest-api.php` around line 385, which already defaults to today), map view, homepage event count and any "live event count", taxonomy archives (region and length), organiser pages or lists, organiser dashboard counts if relevant, related-event lists. Explicit past date ranges in the REST API should keep working as today.

### 5. Sitemap
Exclude past events that redirect (they have a series key and a next occurrence) from the Rank Math event sitemap. Past one-off events can stay in the sitemap. Use Rank Math's sitemap filters; keep it cheap (no per-entry heavy queries, or cache the set of redirecting IDs).

### 6. Old `/event/` base
Add a permanent redirect from `/event/{slug}/` to `/begivenhed/{slug}/` (and the English equivalent if Polylang uses a different base). A second hop to the next occurrence is fine.

### 7. Restore command
WP-CLI command, for example `wp vandrekalender restore-past-drafts [--dry-run]`:
- Candidates: `post_status = draft`, `event_source = scraped`, not claimed, `event_date` < today.
- Only restore posts the cleanup drafted: `event_date` is at least 6 days before the post's `post_modified` date (the cleanup ran 7+ days after the event). Posts where `event_date` >= `post_modified` date were drafted as cancelled; skip them. Print skipped IDs with the reason.
- Publish with `wp_update_post`, then check that `post_name` is unchanged. Print any slug that changed; the old URLs must keep working.
- Dry run prints counts and a sample list. Run on staging first, verify, then production after a fresh database backup.

## Verification (Petya runs the curl checks from her Mac)

- `curl -sI https://staging.allevandreture.dk/begivenhed/<past one-off slug>/` returns 200 and the page shows the notice and upcoming walks.
- A past recurring occurrence returns 301 with `location:` on the next occurrence.
- A past recurring occurrence without a next date returns 200 with the notice.
- `/event/<slug>/` returns 301 to `/begivenhed/<slug>/`.
- Calendar, map, filters, region and length pages show only upcoming events. The homepage count is unchanged.
- Past event pages have no Event JSON-LD. Upcoming ones still do.
- The event sitemap has no redirecting past occurrences.
- Both languages work.
- PHPCS clean. Scraper run (`./scrape.sh`) still logs normally, with no cleanup row.

## Later (not now)

- Events older than about 12 months: consider deleting and answering **410 Gone**.
- Watch Search Console "Not found (404)" and "Duplicate" rows over the following weeks.
