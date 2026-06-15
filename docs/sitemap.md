# Sitemap & Site Structure

> Source of truth for all pages, URLs, authentication, and navigation.

**Version 1.0 — Updated June 2026**

---

## Overview

Vandrekalender.dk is a single-page-primary site where the homepage is the primary—and for most users, the only—page they visit. It combines discovery, filtering, and browsing in a single scrollable experience with three interactive tabs: calendar view, event list view, and map view. All filters update results in real time.

---

## Public Pages (No Auth Required)

| URL | Purpose | Phase |
|---|---|---|
| `/` | Homepage — hero, search, filters, calendar/list/map tabs (all real-time filtered) | V1 |
| `/begivenhed/[slug]` | Single event detail page | V1 |
| `/om` | About page — site mission, how it works, membership info | V1 |
| `/kontakt` | Contact form | V1 |
| `/arrangor/[slug]` | Organizer profile page — all events by one organization | V2 |
| `/privatlivspolitik` | Privacy policy (GDPR) | V1 |
| `/vilkaar` | Terms of use | V1 |
| `/cookies` | Cookie policy | V1 |

---

## Authentication Flow

See `docs/authentication.md` for full details.

| URL | Purpose | Auth method |
|---|---|---|
| `/wp-login.php` | WordPress native login | Google OAuth or email/password |
| `/wp-login.php?action=register` | WordPress native registration | Email/password (with email verification required) |
| `/auth/callback` | OAuth redirect handler (technical, not user-facing) | Google OAuth callback |
| `/wp-login.php?action=lostpassword` | Password reset | Native WordPress |

**User roles:** All new users are assigned the "Event Organizer" role automatically. See `docs/authentication.md` for organizer taxonomy, email domain matching, and team membership.

---

## Logged-in User Pages (Native WordPress)

Users with "Event Organizer" role access these WordPress native pages. No custom front-end pages in V1.

| URL | Purpose | Notes |
|---|---|---|
| `/wp-admin/post-new.php?post_type=event` | Create/edit event | Standard WP post editor. Dashboard widget shows onboarding video on first visit |
| `/wp-admin/edit.php?post_type=event` | My events list | Filtered to show organization events + user's own private events. Author column, "Posted as" column (organization/private), filter tabs |
| `/wp-admin/profile.php` | User settings | Avatar, display name, email, language preferences, password |

---

## Admin Pages

| URL | Purpose | Access |
|---|---|---|
| `/wp-admin` | WordPress dashboard | Administrators only |
| `/wp-admin/post-new.php?post_type=event` | Manage all events | Administrators only |
| `/wp-admin/edit.php?post_type=event` | Event list with approval/removal | Administrators only |
| `/wp-admin/edit-tags.php?taxonomy=organizer` | Manage organizations | Administrators only |

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

- All URLs use Danish words for local SEO (e.g., `/begivenhed`, `/arrangor`, `/privatlivspolitik`)
- Event slugs auto-generated from titles: lowercase, hyphens, Danish characters (æ→ae, ø→oe, å→aa)
- No trailing slashes
- No date or ID in event URLs — slug only

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

## Phase Plan

| Feature | Phase |
|---|---|
| All public pages (homepage, event detail, about, contact, legal) | V1 |
| Google OAuth + native WP registration/login/password reset | V1 |
| Event editor with organizer/team support | V1 |
| User settings (profile page) | V1 |
| Membership management (email domain match, admin assignment) | V1 |
| Membership copy on `/om` and `/vilkaar` | V1 |
| Organizer archive page (`/arrangor`) | V2 |
| Member invitations | V2 |
| Custom admin/creator pages (beyond native WP) | V2+ |

---

*vandrekalender.dk — Sitemap v1.0*
