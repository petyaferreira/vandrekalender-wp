# Migration: vandrekalender.dk → allevandreture.dk

Brief for the migration session. Written 4 September 2026 from the project's current state. The person doing this (Petya) is learning software development: explain each step in plain terms before running it, run one step at a time, and verify after each step before moving on. Staging first, production only after staging is fully verified.

## Why

The site is being renamed from Vandrekalender (vandrekalender.dk) to Alle Vandreture (allevandreture.dk), because Dansk Motions Forbund has used the name "Vandrekalenderen" since 1974 and is rebuilding vandrekalenderen.dk. DMF will later buy vandrekalender.dk from Petya, so the old domain must keep redirecting to the new one for roughly three months after the move, and then it goes away entirely. Nothing about the old name may remain load-bearing after this migration.

## Current state (verified)

- Hosting: Nordicway. SSH access already set up from this Mac.
- cPanel Domains:
  - `vandrekalender.dk` (primary domain)
  - `allevandreture.dk` (alias, shares document root)
  - `staging.vandrekalender.dk`(separate staging copy, own database)
  - `staging.allevandreture.dk` (same staging copy)
- Email: `kontakt@allevandreture.dk` exists. `kontakt@vandrekalender.dk` still exists and forwards to the new address.
- Right now, visiting allevandreture.dk redirects to vandrekalender.dk. That is the thing this migration changes.
- WordPress: block theme (FSE), Polylang free (Danish + English), Rank Math (Organization schema currently named "Vandrekalender", social share image with the old logo, sitemap), GA4 and Search Console connected, Google login (OAuth) for users, custom post type Events with Regions/Lengths taxonomies, scrapers that import events from external sources.
- Live users exist (a handful of organisers). Keep downtime to minutes, and do production at a quiet time.

## Before touching anything

1. Take a full backup of production: database dump (`wp db export` over SSH, or cPanel backup) and a copy of `wp-content/uploads`. Note where the backup is. Also back up staging's database.
2. Look for hard-coded old URLs in the repo: `grep -ri "vandrekalender" --include=*.php --include=*.js --include=*.scss --include=*.json --include=*.html --include=*.yml --include=*.md .` Expect hits in theme text, `theme.json`, block patterns, SCSS, GitHub Actions workflows (deploy targets, paths), Docker/wp-env config, README. Make a list; decide per hit whether it's a URL (change), a folder path on the server (do NOT change, the staging folder is still called staging.vandrekalender.dk), or brand text (change).
3. Check `wp-config.php` on the server for `WP_HOME` / `WP_SITEURL` defines. If present, they override the database settings and must be edited too.
4. Check the scrapers: do they store or compare absolute URLs to the old domain anywhere (e.g. for internal links or de-duplication)? Note it.

## Staging rehearsal (do this fully first)

Target: staging.vandrekalender.dk becomes staging.allevandreture.dk.

1. SSH in, `cd ~/staging.vandrekalender.dk`.
2. Dry run: `wp search-replace 'https://staging.vandrekalender.dk' 'https://staging.allevandreture.dk' --all-tables --precise --dry-run`. Explain: this finds every occurrence of the old address in every database table (posts, options, postmeta, plugin settings), handling WordPress's serialized data safely. Review the counts.
3. Also dry-run the scheme-less form to catch `//staging.vandrekalender.dk` and plain `staging.vandrekalender.dk` occurrences, in that order (https first, then bare). Be careful: the bare form would also match inside folder paths stored in the DB (e.g. upload paths containing the account's home directory + `staging.vandrekalender.dk/`). Check the dry-run output for such path hits; if there are any, use a more specific pattern or skip those tables/columns.
4. Run for real (drop `--dry-run`). Then `wp option get siteurl` and `wp option get home` must both show the new address. `wp cache flush`. If a persistent object cache or a page cache plugin exists, clear it.
5. Update `.htaccess` in the staging folder if it contains the old hostname (rewrite rules, https redirects).
6. Verify in the browser at https://staging.allevandreture.dk: homepage loads under the new name without redirecting away; padlock present; Danish and English pages (Polylang) both load and the language switcher points to new-domain URLs; calendar, filters, map view and an event page all work; images load (media library URLs were rewritten); admin login works; permalinks page saved once (Settings → Permalinks → Save, to rewrite rules).
7. Google login will fail on staging until its redirect URI is added (see Google section); that is expected. Test it after that step.
8. Deployment: push a trivial change and confirm the GitHub deploy to staging still works (the deploy targets a folder path, which has not changed; if a workflow references the hostname, fix it).

## Production

Same procedure, on `~/public_html`, with `https://vandrekalender.dk` → `https://allevandreture.dk`. Pick a quiet time. Steps in order:

1. Fresh backup (again, right before).
2. Put a note in the notes file with the timestamp; announce nothing publicly until verified.
3. `wp search-replace` dry run, review, run, then the scheme-less variants with the same path caution as on staging.
4. Confirm `siteurl`/`home`, flush caches, save permalinks.
5. Settings → General: Administration Email Address → `kontakt@allevandreture.dk`. Also any "from" address in an SMTP/mail plugin if one is used.
6. Redirects from the old domain. Because both domains share the same document root, the same `.htaccess` serves both, so the redirect must be conditional on the requested hostname. At the very top of `public_html/.htaccess`, before the WordPress block:

    ```
    RewriteEngine On
    RewriteCond %{HTTP_HOST} ^(www\.)?vandrekalender\.dk$ [NC]
    RewriteRule ^(.*)$ https://allevandreture.dk/$1 [R=301,L]
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
    ```

    Explain: the first rule says "if the visitor asked for vandrekalender.dk, send them permanently (301) to the same path on allevandreture.dk". The second forces https on the new domain. Test with `curl -I https://vandrekalender.dk/some-event-path` and confirm `301` with a `Location:` on the new domain and the same path. Test `http://allevandreture.dk` → `https://allevandreture.dk`.
7. Rank Math: Titles & Meta → Organization name "Alle Vandreture"; update the logo and the social share image (new 1200×630, brand green #2d5f3f, new wordmark); check the sitemap at `/sitemap_index.xml` now lists new-domain URLs; resubmit later in Search Console.
8. Theme and content: site title and tagline (Settings → General), header logo, footer text, "Om os" page, frontpage copy, email templates, any pattern or template part with the old name. Do this in the repo where it's code, in the editor where it's content. Suggested tagline: "Alle vandreture i Danmark, samlet i én kalender".
9. Verify everything in the browser as on staging, on both languages, on phone too.

## Google and third parties (after production)

- **Google Cloud Console** (the OAuth client used for "Log in with Google"): add `https://allevandreture.dk` to Authorized JavaScript origins and the exact callback URL(s) on the new domain to Authorized redirect URIs (keep the old ones for a while; add the staging ones too). Also update the OAuth consent screen's app name, home page and privacy policy URL. Test Google login on the live site immediately after.
- **Search Console**: add `allevandreture.dk` as a new property (Domain property, verify via DNS TXT record in cPanel Zone Editor), submit the new sitemap, then on the OLD property use Settings → Change of Address → point to the new property. This formally tells Google the site moved.
- **GA4**: Admin → Data Streams → edit the web stream URL to the new domain. Measurement ID stays the same, nothing to change in the site.
- **Rank Math / Search Console connection** inside WordPress: reconnect to the new property if it shows the old one.
- Facebook page / Instagram: rename, update links. GitHub repo: renaming is optional and cosmetic; if renamed, update the local remote URL.
- Nordicway account: later, ask support to make allevandreture.dk the primary domain of the cPanel account (before the old domain is transferred to DMF, not now).

## Communication after go-live

- Short email to the existing organisers: new name, same login, old links keep working.
- Email to the DMF contact with the new address, as promised.
- Signature: only `kontakt@allevandreture.dk` from now on.

## Rollback

If production breaks badly: restore the database dump, remove the redirect block from `.htaccess`, flush caches. The old domain then works as before. Because files did not move and the domains share a folder, rollback is a database restore, nothing more.

## Do not

- Do not rename the server folder `staging.vandrekalender.dk`; the cPanel domains point at it. Renaming it can wait indefinitely.
- Do not delete the old domain from cPanel; it must keep serving the redirect for about three months.
- Do not run search-replace without `--dry-run` first, and never on production before staging is verified.
- Do not touch the scrapers' source URLs; they point at external sites, not at this one.

## Log

Add a dated line here for each step completed, and anything that surprised you, so the project notes can be updated afterwards.

### 2026-09-04 — Pre-flight + staging rehearsal (Claude Code session)

**Pre-flight**
- Staging DB backed up to `~/vandrekalender-migration-backup/2026-09-04/staging-db.sql` (1.3 MB, 26 tables). Production backup deferred to immediately before the production run, per the doc.
- Repo scan: 2080 raw `vandrekalender` hits, but only ~11 lines are the real old *domain* in tracked code — footer email + `© Vandrekalender.dk` (`theme/parts/footer.html`), User-Agent strings (`class-geocoder.php` ×4, `class-scraper-base.php`, `class-scraper-sportstiming.php`, `class-facebook-importer.php`), the scraper bot's placeholder email (`class-scraper-base.php:319`), `Plugin URI` header, and 3 prose comments. Everything else is the theme/plugin *slugs* (`vandrekalender-theme`, `vandrekalender-events`), block namespace `vandrekalender/*`, REST namespace `vandrekalender/v1`, PHP namespace `\Vandrekalender\`, text domains, function prefixes — all internal identifiers, NOT renamed (see note below).
- `wp-config.php` on both prod and staging: **no** `WP_HOME` / `WP_SITEURL` defines. The address lives only in the DB, so search-replace is sufficient.
- Scrapers do not store or compare the site's own domain for dedup or internal links. Safe.
- Meta keys are unprefixed (`event_date`, …) — not affected by any slug.

**Decision — repo/theme/plugin rename:** rename the GitHub repo (cheap, safe, do it after the migration is verified). Leave the theme folder, plugin folder, block namespace, and REST namespace as `vandrekalender*` — they are wired into saved block content and the DB's active-theme/active-plugin records; renaming is a separate project with its own rehearsal and zero SEO/user benefit. Removing the old *domain* from code (Bucket B above) is a small separate commit, folded into the production content work.

**Bucket B applied** — the old domain is now gone from tracked code (`git grep -in "vandrekalender\.dk"` over `*.php *.js *.scss *.html`, excluding docs, returns nothing). 9 files changed, 18/18 lines:
- `theme/parts/footer.html` — mailto link + `Email:` text → `kontakt@allevandreture.dk`; `© Vandrekalender.dk` → `© Alle Vandreture.dk`.
- `theme/style.css` — `Theme URI` → `https://allevandreture.dk` (found during the re-scan, same pattern as the plugin header).
- `plugin/vandrekalender-events.php` — `Plugin URI` → `https://allevandreture.dk`; a comment mentioning `wordpress@…` updated.
- `class-geocoder.php` (×4), `class-scraper-base.php`, `class-scraper-sportstiming.php`, `class-facebook-importer.php` — the outbound User-Agent `Vandrekalender/1.0 (+https://…)` now points at the new domain.
- `class-scraper-base.php` — the bot user creation now uses the new domain for its placeholder email, plus the display name "Alle Vandreture Robot" (only affects a *fresh* environment; the existing production/staging bot users were already updated directly — see the user-accounts entry above).
- `class-event-attendees.php`, `event-info-card/render.php` — prose comments reworded to "our own site" instead of naming the domain.
- Theme/plugin *names*, text domains, block namespace, REST namespace, PHP namespace, function prefixes — left untouched, per the Bucket A decision above.
- `composer run phpcs` on the changed files: clean, no violations. Not yet committed/pushed — will go out with the normal deploy.

**Revisited — full internal rename (folders, text domain, PHP namespace, block namespace)**
- Asked again whether we should rename every internal `vandrekalender` identifier too, not just the domain in code strings.
- Laid out the real scope: block namespace (`wp:vandrekalender/event-*`, 10 blocks) is serialized into saved post content — confirmed **62 posts on production** contain it, plus every FSE template. Renaming the registered block name without migrating that content breaks the calendar/filters/map/cards/info-card silently on every page that uses them, in both languages, until a DB migration runs in lockstep with the code deploy. Also touches: PHP namespace `\Vandrekalender\`, class prefixes `Vandrekalender_Event_*`, REST namespace `vandrekalender/v1`, active-theme/active-plugin DB records, Polylang string translations.
- **Decision: keep it out of this migration.** Not necessary (invisible to users/SEO, purely internal), and doing it now adds real risk to the domain cutover. If wanted later, it's its own dedicated project with its own staging rehearsal for the content migration.

**Staging rehearsal — `~/staging.vandrekalender.dk` → `staging.allevandreture.dk`**
- Dry runs: `https://staging.vandrekalender.dk` → 70 hits (2 options, 6 post_content, 61 guid, 1 user_url). Scheme-less `//` and bare forms found the same 9 non-guid hits — no protocol-relative or bare-only occurrences, and **`postmeta.meta_value` = 0**, so no server folder paths (e.g. the account's home directory + `staging.vandrekalender.dk/…`) are stored in the DB. The bare-form path hazard from the doc does not apply here.
- Real run: `wp search-replace 'https://staging.vandrekalender.dk' 'https://staging.allevandreture.dk' --all-tables --precise --skip-columns=guid` → **9 replacements**. `--skip-columns=guid` per the project's own convention (GUIDs are permanent IDs, not links). `siteurl`/`home` now both `https://staging.allevandreture.dk`. `wp cache flush` + `wp rewrite flush` done.
- No page-cache / object-cache plugin, no cache drop-ins. Staging `.htaccess` has no hostname (only `RewriteBase /`) — nothing to edit.
- Polylang is **inactive** on staging (installed but off) — the DA/EN language-switcher check can only be done on production.

**Verification (staging)**
- `staging.allevandreture.dk/` → 200 direct. Homepage HTML: 99× new domain, 0× old.
- REST `vandrekalender/v1/events` → 200, `permalink` fields on new domain.
- Screenshots: homepage hero, **filter bar** (region/length/date/free), **calendar view with event-count badges**, **map view** (Leaflet + OSM tiles + marker clusters over Denmark), single event page (info card: price/date/time/place/"I'm going", featured image loads). All render correctly — the JS blocks are successfully querying the REST API on the new domain.
- `wp-admin/` → 302 to `wp-login.php` on the new domain; `wp-login.php` → 200.
- **Surprise / finding:** old-domain deep links (e.g. `/sample-page/`, `/begivenhed/<slug>/`) serve **200 under the old host**, not a 301 — only the site root canonical-redirects. The returned HTML is correct (new-domain canonical tag + 65 new-domain URLs), so it's not broken, but WordPress's canonical redirect alone is **not** a full old→new redirect. This is exactly why the **production** plan (step 6) adds an explicit `.htaccess` `RewriteCond %{HTTP_HOST} … RewriteRule … [R=301,L]` that catches every path before WordPress runs. Staging never got that rule (the doc doesn't ask for one) and its old hostname is allowed to keep resolving.
- Not yet done on staging: Google login (needs the redirect URI added in Google Cloud Console — expected to fail until then) and the "push a trivial change, confirm GitHub deploy still works" check.

**Length filter on the frontpage calendar — pre-existing staging data bug, NOT caused by the migration**
- Reported: length filter pills did nothing on the calendar.
- Cause: the filter UI (`blocks/event-filters/render.php`) and the plugin's own length assignment (`class-event.php:386–390`) only ever use the fixed buckets `kort` / `mellem` / `lang`. Production events are tagged that way (`kort` 295, `mellem` 190, `lang` 22) and the filter works there. **Staging's** events were tagged with English slugs `short` (10) / `medium` (19) / `long` (13) — `mellem` and `lang` didn't even exist as terms — so "Medium"/"Long" matched nothing. Old hand-made / legacy test data.
- Confirmed unrelated to the rename: the search-replace dry run showed `terms.slug` and `term_taxonomy` = 0 replacements; the REST filter logic itself worked on staging throughout.
- Fix (staging only): `wp eval-file` script remapped every event `short→kort`, `medium→mellem`, `long→lang`, deleted the three empty English terms, and set the term names to match production. Backup taken first: `~/vandrekalender-migration-backup/2026-09-04/staging-db-pre-lengthfix.sql`. After: `event_length` = `kort` 11 / `mellem` 19 / `lang` 13; server-rendered calendar now filters (`?maaned=2026-09&length=lang` → 5 event-days vs 7 unfiltered). **No production action needed** — production's taxonomy is already correct.

**WordPress user accounts and email on the old domain** (account names/emails genericized below per a PR review — a public repo shouldn't name real admin login identifiers; run `wp user list` on the server for actual values when executing this)
- Production users: 6 total. Only two touch the old domain — the primary admin account and the scraper's bot account (author role, byline for scraped events). All four real organisers are on gmail/hotmail and are unaffected. The site's `admin_email` option also pointed at the old domain on both environments.
- Impact when the old domain is handed to DMF (~Dec 2026): the admin account can still log in (password / Google), but a password-reset for it would bounce. The bot account is inert — never logs in, never emailed; its address is only written once at creation and it is looked up by login name thereafter. The real risk is `admin_email` — registration notices, comment moderation, Site Health, password-reset admin copies — which currently only *forwards* from the old domain.
- Applied on **staging** (`wp option update` / `wp user update`, no confirmation emails): `admin_email` → `kontakt@allevandreture.dk` (the site's public contact address); the admin account's email updated to match; the bot account's email and display/nickname updated to the new domain / "Alle Vandreture Robot". Verified.
- Also cleared a **stale `new_admin_email`** option on staging (pointing at an old-domain admin address) — a pending admin-email change from staging's original setup that was never confirmed because staging has no working outbound mail. `wp option delete new_admin_email`. Was causing the "pending change" nag in Settings → General. Unrelated to this migration; check `wp option get new_admin_email` on production too (it was empty when checked 2026-09-04).
- Note: staging has no working outbound mail (no Mailpit on the server, PHP `mail()` fallback undelivered). Not needed for the migration; only affects end-to-end testing of flows that send mail (e.g. Google-login new-user registration). Production uses FluentSMTP (bundled, currently inactive — see `docs/deployment.md`).

**Full browser verification pass #2 (staging, after the length + user/email fixes)**
- `siteurl`/`home` = `https://staging.allevandreture.dk`. Old root → 301 → new. Homepage, event page, `?maaned=` calendar page, and `/region/hovedstaden/` archive: **0** old-domain strings in the HTML. All six REST endpoints (`events`, `events/count`, `count?length=`, `count?region=`, `events/days`, `events/locations`) → 200.
- Homepage: full render — hero, filter bar, calendar, CTA sections, footer. Calendar view: month grid + event-count badges.
- **Length filter now works** — selecting "Long (25+ km)" narrows the September calendar from 5 event-days to 3 (`12, 13, 26`), matching the REST `days?length=lang` result. The term-remap fix holds.
- Region: `/region/hovedstaden/` archive → 3 cards (matches `count?region=hovedstaden`), region preset applied; the "efter længde" links show the corrected Danish term names ("Korte/Mellemlange/Lange vandreture …").
- List view: 19 cards with date, place, distances, price (`from X kr` / `Free`), region badge. Map view: Leaflet + OpenStreetMap tiles + marker clusters over Denmark. Single event page: info card (price/date/time/place/directions/"I'm going") + featured image. `wp-login.php`: renders with the "Login with Google" button. Mobile (390px): header collapses to hamburger, hero scales.
- Non-issue found: `/vandreregion/<term>/` 404s — that was a wrong guess at the URL; the region taxonomy `rewrite` slug is `region`, so the archive is `/region/<term>/` and it 200s on both staging and production. Footer still shows "Vandrekalender" / `kontakt@vandrekalender.dk` — Bucket B content, not migration damage.
- Still not verifiable on staging: Google login end-to-end (needs the staging redirect URI in Google Cloud Console — doc "Google and third parties"), Polylang DA/EN switcher (Polylang inactive on staging), and the deploy-still-works check (doc staging step 8).
- **Production TODO at cutover:** same three account changes as staging (see the user-accounts entry above). And decide how the bare-form search-replace treats user emails — recommended: run it with `--skip-columns=guid,user_email` and make the account changes explicitly, rather than letting a blind replace rewrite `users.user_email` / comment-author emails.

### 2026-09-05 — Redacted the doc's infra details from git history; refreshed staging from a real production clone

**Doc sanitisation** — an automated PR review flagged that this doc, committed to a *public* repo, named the cPanel account username, host, absolute server paths, and a DMF contact's name. Confirmed: repo `petyaferreira/vandrekalender-wp` is public, PR #11 was open, and the pushed commit (`09c6032`) had the full un-redacted "Current state" section from before Petya's own cleanup pass. Fixed by redacting the remaining tokens (DB name, one absolute path, the named contact) and then `git commit --amend` + `git push --force-with-lease` to replace `09c6032` with a clean commit (`89ffa24`) — verified via a fresh `git fetch` that the old commit is no longer reachable by any ref and the new one contains none of the flagged strings. Kept the file tracked in git rather than gitignoring it (redacting was sufficient; the log has ongoing value).

**Staging refreshed from a real production clone**, at Petya's request, to re-rehearse the migration on realistic data before merging PR #11:
1. Backed up staging's DB again (`staging-db-pre-prodclone.sql`).
2. Exported production's DB (6.4 MB) and imported it into staging — table prefix matches on both (`ZYLiJirU_`), so this was a clean drop-in replace.
3. Copied `wp-content/uploads` from production to staging directly on the server (same account, no need to transfer off-box) — 346 MB.
4. Re-ran the URL swap, this time as one **bare-domain** pass (`vandrekalender.dk` → `staging.allevandreture.dk`, `--skip-columns=guid,user_email`) rather than three separate passes — bare-domain is a superset of the `https://`/`//` forms, so one pass covers everything once confirmed safe. Real production data actually exercised the doc's stated risks for the first time: the bare form matched `users.user_email` (2 rows — the admin and bot accounts) and email-log `to`/`from` fields, which the first (synthetic) staging rehearsal never surfaced. Checked the `postmeta` hits by hand for the "server folder path" hazard — none found, just Rank Math SEO text and a real media URL. 1425 replacements made.
5. Redid the three admin/bot-email fixes (same commands as before — they don't persist through a full DB re-import).
6. Running the account-email fixes itself queued 4 new log rows (WordPress's own "your email was changed" security notices, sent to FluentSMTP's log since staging has no real mail transport) — swept those with one more small pass.
7. Verified: root 301 redirect from the old host works; 0 old-domain strings in the homepage HTML; REST count matches the visible "515 ture matcher dine valg" on the page; full real homepage (hero photo, organiser ticker, filter bar, calendar with real per-day counts, "sådan fungerer" copy, footer with the correct new contact email) and the map view (515 events, real regional clusters up to 181) both render correctly.
8. Noted, not a bug: the site title (`blogname`) is still literally "Vandrekalender" (visible in the header, browser tab, and email subject lines like "[Vandrekalender.dk] …") — that's the doc's Production step 8 (site title/tagline/content), separate from this URL+email migration.

Next: Petya to review PR #11 (containing the Bucket B code cleanup) before merging.

### 2026-09-05 (cont.) — Content rollout strategy, Google login, staging blocked from search engines

**Decision — how staging's content changes reach production:** Petya proposed perfecting staging fully, then cloning staging's DB onto production. Flagged the risk: production is live and continuously changing in ways staging can't capture — the scrapers only run on production (daily 02:12), plus real organiser signups, Google logins, and event joins happen there right now. Cloning staging → production at cutover would silently discard everything produced since our last staging refresh, and the gap only grows the longer we wait. **Decided instead:** staging is a rehearsal/preview only. Content edits (site title, tagline, footer, logo, Rank Math org name/social image) get made and previewed on staging, tracked as an explicit checklist, then applied directly to production itself during the real cutover — matching the doc's existing Production step 7–8. No database is ever cloned staging → production.

**Google login on staging:** WordPress-side config already correct (plugin active, mu-plugin with shared client ID/secret deployed, login page correctly requests `redirect_uri=https://staging.allevandreture.dk/wp-login.php`). The only missing piece is in Google Cloud Console (Petya-only access) — needs `https://staging.allevandreture.dk/wp-login.php` added to Authorized redirect URIs (and the origin, if One Tap is on), alongside the existing entries. Not yet done by Petya as of this log entry.

**Staging blocked from search engines** — raised by Petya: staging was never protected from crawling/indexing, on *either* the old or new staging hostname, and this matters more now that staging holds a full clone of real production content. Confirmed the gap: `blog_public` had been cloned from production as `1` (indexable), the dynamic `robots.txt` allowed everything and advertised a sitemap, and the homepage served `<meta name="robots" content="index,follow">`. Fixed with three layers (all apply to both `staging.vandrekalender.dk` and `staging.allevandreture.dk` — same document root):
1. `wp option update blog_public 0` → Rank Math correctly switched the meta tag to `noindex, nofollow`.
2. **Rank Math's own robots.txt filter ignores `blog_public` entirely** (a known SEO-plugin quirk — it kept serving the permissive version with a Sitemap line after the option flip). Fixed by placing a **physical `robots.txt`** (`Disallow: /`) in the staging docroot — a real file on disk takes priority over anything WordPress/Rank Math generates dynamically.
3. Added an `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` header via `.htaccess` (top of file, own marker block) as a backstop that covers non-HTML files (images, PDFs) and doesn't depend on any plugin.
- **Caveat flagged to Petya, not yet actioned:** all three are polite signals well-behaved crawlers respect; none of them physically restrict access. If real access control is wanted, the next layer is HTTP Basic Auth via `.htpasswd` — offered, decision pending.
- **Not touched:** production's `blog_public`/robots.txt — production is meant to stay indexable, this fix is staging-only.
