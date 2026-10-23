# Sitemap & Site Structure

> Source of truth for all pages, URLs, authentication, and navigation.

---

## Overview

Vandrekalender.dk is a single-page-primary site where the homepage is the primary—and for most users, the only—page they visit. It combines discovery, filtering, and browsing in a single scrollable experience with three interactive tabs: calendar view, event list view, and map view. All filters update results in real time.

---

## Public Pages (No Auth Required)

| URL | Purpose |
|---|---|
| `/` | Homepage — hero, search, filters, calendar/list/map tabs (all real-time filtered) |
| `/begivenhed/[slug]` | Single event detail page |
| `/om` | About page — site mission, how it works, membership info |
| `/kontakt` | Contact form |
| `/arrangoerer/[slug]` | Organiser page — a post of the `organiser` post type, linked to one `event_organiser` term, e.g. `/arrangoerer/dansk-vandrelaug/` ([data-model.md](data-model.md#custom-post-type-organiser)). An organiser without a published page redirects to `/?organiser=<slug>`. Shows the computed stats, regions and "Andre arrangører"; SEO is step 5e of [the organisers plan](plans/organisers-plan.md) |
| `/privatlivspolitik` | Privacy policy (GDPR) |
| `/vilkaar` | Terms of use |
| `/cookies` | Cookie policy |

---

## Authentication Flow

See `docs/authentication.md` for full details.

| URL | Purpose | Auth method |
|---|---|---|
| `/wp-login.php` | WordPress native login | Google OAuth or email/password |
| `/wp-login.php?action=register` | WordPress native registration | Email/password (with email verification required) |
| `/auth/callback` | OAuth redirect handler (technical, not user-facing) | Google OAuth callback |
| `/wp-login.php?action=lostpassword` | Password reset | Native WordPress |

**User roles:** All new users are assigned the "Event Author" role (`event_author`) automatically. See `docs/authentication.md` for organizer taxonomy, email domain matching, and team membership.

---

## Logged-in User Pages (Native WordPress)

Users with the "Event Author" role access these WordPress native pages. There are no custom front-end pages for them.

| URL | Purpose | Notes |
|---|---|---|
| `/wp-admin/post-new.php?post_type=event` | Create/edit event | Standard WP post editor. Dashboard widget shows onboarding video on first visit |
| `/wp-admin/edit.php?post_type=event` | My events list | Shows only the user's own events (`Vandrekalender_Roles::restrict_event_list_to_own()`). The shared organisation list, "Posted as" column and filter tabs: [#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86) |
| `/wp-admin/profile.php` | User settings | Avatar, display name, email, language preferences, password |

---

## Admin Pages

| URL | Purpose | Access |
|---|---|---|
| `/wp-admin` | WordPress dashboard | Administrators only |
| `/wp-admin/post-new.php?post_type=event` | Manage all events | Administrators only |
| `/wp-admin/edit.php?post_type=event` | Event list with approval/removal | Administrators only |
| `/wp-admin/edit-tags.php?taxonomy=event_organiser&post_type=event` | Manage organisers | Administrators only |

---

## Navigation

### Primary (Header)

- **Kalender** — `/` (scrolls to calendar tab on homepage)
- **Kort** — `/` (scrolls to map tab on homepage)
- **Opret begivenhed** — `/wp-admin/post-new.php?post_type=event` (visible when logged in; shows "Login" when not)
- **Login / Min konto** — `/wp-login.php` or `/wp-admin/profile.php` (toggles based on auth state)

### Footer

- Om Vandrekalender — `/om`
- Kontakt — `/kontakt`
- Privatlivspolitik — `/privatlivspolitik`
- Vilkår — `/vilkaar`
- Cookies — `/cookies`

---

## URL Conventions

- All URLs use Danish words for local SEO (e.g., `/begivenhed`, `/arrangoerer`, `/privatlivspolitik`)
- Event slugs auto-generated from titles: lowercase, hyphens, Danish characters (æ→ae, ø→oe, å→aa)
- No trailing slashes
- No date or ID in event URLs — slug only
- The pre-Polylang `/event/[slug]` base (used before the Danish permalink moved to `/begivenhed/[slug]`) 301-redirects to the current URL. See `docs/history/past-events-brief.md`.

---

## Past Events

**Past events stay published at their existing `/begivenhed/[slug]` URL** — a passed date never drafts a page. See `docs/scrapers.md` → "Past-events cleanup" and `docs/history/past-events-brief.md` for the full picture.

- A **recurring occurrence** (a walk a source republishes as one page per date, e.g. DVL's weekly walks) 301-redirects once its date passes to the next upcoming occurrence of the same walk, if one exists. This is the duplicate-content fix — old occurrences funnel forward instead of accumulating as near-identical pages.
- A **one-off event**, or a recurring one with no next occurrence yet, stays on its own URL with a "this walk has taken place" notice and nearby upcoming walks.
- The Rank Math XML sitemap excludes a redirecting past occurrence (it would only send crawlers into the redirect above); a past one-off event stays in the sitemap.

---

## Homepage Sections

See `docs/homepage.md` for detailed specifications.

1. **Hero** — headline, location search, background image
2. **Search & Filters** — sticky, real-time updates
3. **Three tabs:**
   - Calendar view (current month, navigation arrows)
   - Event list (sortable)
   - Map view (pins, real-time)

All three tabs update simultaneously when filters change.

---

## Not Built Yet

- Organiser overview page (`/arrangoerer/`, all organisers): the `organiser` post type archive exists with the default template; its own template is step 5d of [the organisers plan](plans/organisers-plan.md).
- Organisation membership (email domain match, admin assignment, shared event list, membership copy on `/om` and `/vilkaar`): [#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86).
- Member invitations: [#41](https://github.com/petyaferreira/vandrekalender-wp/issues/41).
