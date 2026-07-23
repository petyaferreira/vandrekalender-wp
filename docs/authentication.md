# Vandrekalender.dk — Authentication & Organization Architecture

**Version 1.1 · Technical Document**

Defines the authentication flow, user roles, email verification policy, the organizer entity, and how multiple users share ownership of an organization's events.

---

## Overview

Authentication serves two kinds of users:

1. **Solo organizers** — individuals posting their own walks. Default WordPress behaviour covers them: they see and edit only their own events.
2. **Organization members** — multiple people (e.g. two DVL employees with different emails) who must all see and edit the same shared list of their organization's events, while keeping individual profiles so it is always clear who created or edited what.

WordPress has no built-in concept of group ownership (every post has exactly one `post_author`), so this document introduces an **Organizer entity** as the ownership layer above individual users.

---

## User Roles

| Role | Capabilities | Notes |
|---|---|---|
| Administrator | Full site management | Site admin only |
| Event Organizer | `edit_posts`, `publish_posts` on the `event` post type only | Assigned automatically to every new user. Restricted wp-admin: only the Events menu is visible |

Role management via the Members plugin (as defined in the Sitemap document).

## Login & Registration Methods

| Method | Flow | Email verified? |
|---|---|---|
| Google OAuth | `/login` → Google consent → `/auth/callback` → user created or logged in → redirect to wp-admin Events | Yes — Google has already verified ownership of the email |
| Email + password | `/registrer` → account created in **pending** state → confirmation email sent → user clicks link → account activated | Only after the confirmation link is clicked |

## Email Verification — Required for All Users

**Decision (new):** every user must have a confirmed email address before they are allowed into the system.

- **Google OAuth users:** verified implicitly. Google guarantees the user owns the email, so they are active immediately after the OAuth callback.
- **Email/password users:** the account is created in a *pending* state. A single-use confirmation token is emailed (same token pattern as the event claim flow: stored with an expiry, cleared after use). Until the link is clicked, the user cannot log in, cannot create events, and is **not** attached to any organization.

Why this matters for security: without verification, anyone could register as `formand@dvl.dk` without owning that inbox and the domain-match logic below would hand them edit access to every DVL event. Verification first, attachment second — always in that order.

## The Organizer Entity

The organizer is promoted from a free-text field to a real entity, implemented as a **custom taxonomy** named `organizer`.

- Each organization (DVL, Mammutmarch, …) is one taxonomy term.
- Each event is attached to exactly one organizer term.
- Taxonomy term archive pages provide the public `/arrangor/[slug]` profile pages for free.
- Term meta `email_domain` stores the organization's email domain (e.g. `dvl.dk`).
- The scraper seeds organizer terms automatically: when importing from `dvl.dk`, it creates/finds the DVL term and saves the domain extracted from `event_source_url`.

> **Data model impact:** `event_organiser_name` (free string) remains for display/scraper fallback, but ownership and querying use the `organizer` taxonomy term. To be reflected in DataModel v4.

## Linking Users to an Organization

Each user optionally carries a user meta field `organizer_id` containing the term ID of their organization. Users without it are solo organizers.

### V1 — Automatic: email domain match

1. User completes email verification (OAuth or confirmation link).
2. The system extracts the domain from the email address (the part after `@`).
3. If an organizer term exists with matching `email_domain` term meta, `organizer_id` is saved to the user's meta.
4. From then on they see the shared organization event list.

```php
function vk_attach_user_to_organizer($user_id, $email) {
    $domain = substr(strrchr($email, '@'), 1); // text after the @

    if (vk_is_public_email_domain($domain)) {
        return; // gmail.com etc. can never map to an organization
    }

    $terms = get_terms([
        'taxonomy'   => 'organizer',
        'meta_key'   => 'email_domain',
        'meta_value' => $domain,
        'hide_empty' => false,
    ]);

    if (!empty($terms)) {
        update_user_meta($user_id, 'organizer_id', $terms[0]->term_id);
    }
}
```

**Public email provider blocklist (hardcoded):** gmail.com, googlemail.com, hotmail.com, outlook.com, live.com, yahoo.com, icloud.com, protonmail.com, mail.dk, youmail.dk, ofir.dk. These domains may never be saved as an organization's `email_domain`, and never trigger attachment.

### V1 — Fallback: admin manual assignment

Many volunteer organizations (DVL chapters in particular) have members on private Gmail addresses, so domain match will not catch everyone. The site admin can set `organizer_id` on any user from wp-admin. Low volume, ~30 seconds per case.

### V2 — Member invitations

A verified organization member can invite a colleague by email, regardless of the colleague's email domain:

1. Member enters the invitee's email in their dashboard.
2. System emails a single-use, expiring invitation token (same token pattern as claim flow and email verification).
3. Invitee clicks the link, registers or logs in, confirms their email if not already verified.
4. On success, `organizer_id` is set to the inviter's organization.

This solves the Gmail-volunteer problem: the first verified DVL person pulls in colleagues themselves, no admin involvement.

## One Organization Per Email — Decided

A user belongs to **at most one organization**. The `organizer_id` user meta is a single value, never an array. A person who acts for two organizations, or who wants a fully separate personal identity, uses a second email address (= a second account). Multi-organization membership is rejected as a data model feature.

## Posting Context — Organization or Private

An organization member is not forced to post everything under the organization's name. The block editor's **Organiser panel** gets a "Post as" toggle (stored as a meta field):

- **Organization (default):** the event is attached to the user's organizer term. It appears in the shared list and on the organization's public `/arrangor/` page.
- **Private:** no organizer term is attached. The event behaves like a solo organizer's event: visible only to its author in wp-admin, displayed publicly under the person's own name.

**Author-only rule:** the toggle is visible and editable only by the event's `post_author`. Without this rule, a colleague could flip someone's private walk into an organization event from the shared list.

## Organization Membership Lifecycle

**Flat structure — decided.** All members of an organization are equal. There is no owner or admin role inside an organization, no internal permission matrix.

- **Joining:** domain match (automatic), admin assignment (V1 fallback), invitation (V2). All require a verified email first.
- **Removal / leaving:** handled exclusively by the **site admin**, on request. Organizations contact the site to have a member removed (e.g. an employee who left). No self-service removal in V1/V2.
- **Stale membership:** there is no automatic detection when someone leaves the real-world organization. Their access remains until the organization requests removal.

**Site content task:** the contact-based membership management must be communicated on the site — a short paragraph on `/om` and a line in `/vilkaar` for V1, later consolidated into a dedicated "For arrangører" info page.

**Future monetization note (non-binding):** manual membership management is the free tier. Self-service member management, multiple organization admins, verified badges, or analytics are candidate paid features. Removal of a member is never paywalled.

## Shared Event List in wp-admin

The Events list for an organization member must show **organization events AND the member's own private events**. WordPress's `WP_Query` cannot natively express "taxonomy term X OR author = me", so the plugin uses the `post__in` technique: fetch the IDs of both groups cheaply, merge them, and constrain the main query to that ID list.

```php
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'event') {
        return;
    }

    $user_id = get_current_user_id();
    $org_id  = get_user_meta($user_id, 'organizer_id', true);

    if (!$org_id) {
        return; // solo user: default WP behaviour, own posts only
    }

    // List 1: all events belonging to the organization
    $org_events = get_posts([
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post_status'    => 'any',
        'tax_query'      => [[ 'taxonomy' => 'organizer', 'terms' => (int) $org_id ]],
    ]);

    // List 2: everything this user created themselves (incl. private events)
    $own_events = get_posts([
        'post_type'      => 'event',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post_status'    => 'any',
        'author'         => $user_id,
    ]);

    $ids = array_unique(array_merge($org_events, $own_events));

    $query->set('post__in', $ids ?: [0]); // [0] = show nothing, never everything
    $query->set('author', '');
});
```

> ⚠️ The `?: [0]` guard is critical: an empty `post__in` array makes WordPress drop the restriction and show **all** posts. `[0]` forces an empty result instead.

**Edit permission (`map_meta_cap`).** Seeing an event is not the same as being allowed to edit it. Every WordPress permission check passes through the `map_meta_cap` filter, where the plugin allows editing a specific event if and only if the event's organizer term matches the user's `organizer_id`, **or** the user is the event's author (covers private events). DVL members can edit DVL events, never anyone else's.

### Dashboard display

- **"Posted as" column:** added via `manage_event_posts_columns`. Shows the organization name ("DVL") or "Privat" per event.
- **Filter tabs (views):** the native "All | Mine | Published" links above the list are replaced via the `views_edit-event` filter with e.g. **Alle (47) | DVL (43) | Mine private (4)**. Each tab adds a query parameter that `pre_get_posts` reads to narrow the ID list.
- Solo users see the unmodified default list, no tabs, no column.

## Tracking Who Created and Edited What

- **Creator:** `post_author` remains the individual user who created the event. Shown in the admin list's Author column.
- **Edit history:** WordPress revisions record every save together with the user who made it.
- **At-a-glance:** a small `event_last_edited_by` meta field (user ID) is updated on every save, so "last edited by Jonas" can be shown without opening revision history.

Profiles stay strictly personal per user: avatar, display name, language preference, and password are managed on the user's own profile page (`/min-konto` / wp-admin profile), independent of organization membership.

## The Join Gate — logging in mid-action

The "Jeg kommer" button (in the Event Info Card, only on events with `event_source = manual`) is the first place an anonymous visitor is asked to have an account. The flow must not lose what they were doing.

1. The button is a real form posting to `admin-post.php?action=vk_join_event`, so it works without JavaScript — and, more importantly, so the logged-out branch runs server-side.
2. Logged out: `Vandrekalender_Event_Join` sets a 30-minute, HttpOnly, `SameSite=Lax` cookie `vk_pending_join = <event_id>` and redirects to `wp_login_url( <event permalink> )`. Lax is deliberate and is the strictest setting that survives Google's callback, which arrives as a cross-site top-level navigation.
3. **One hook covers both login methods.** Login with Google runs through the standard `authenticate` filter on `wp-login.php`, so `wp_login` fires for Google exactly as it does for a native login. `process_pending_join()` (priority 5, ahead of the Google plugin's own redirect) reads the cookie, records the join, sends both emails, and clears the cookie. There is no separate OAuth callback handler to keep in sync.
4. Destination: `redirect_to` on the login URL already points at the event, which the Google plugin copies into its OAuth `state`. The `login_redirect` filter (priority 20, after the organizer dashboard's) is the belt-and-braces for the native path. Either way the visitor lands on the event page with `?joined=1`, never in wp-admin.
5. No pending cookie → every one of these hooks is a no-op and login behaves exactly as before.

The logged-out branch deliberately skips the nonce check: it changes nothing but a cookie, and a logged-out nonce baked into page-cached HTML would go stale and greet real visitors with "Are you sure you want to do this?". The logged-in branch checks the nonce normally.

**Cancelling.** The same button cancels once you are signed up. The `admin-post.php` handler is a toggle, so the no-JS path works in both directions; with JavaScript a confirmation dialog stands in front of the `DELETE`. Cancelling is never gated on `is_joinable()` — an event that stops accepting sign-ups must not trap the people already on the list.

**Emails.** Four in total, all plain text through `wp_mail()` (`Vandrekalender_Event_Join_Mailer`), and only ever sent when the database actually changed — a repeated click is silent:

| Event | To attendee | To organiser |
|---|---|---|
| Join | Confirmation + event details | Who joined, plus the running total |
| Cancel | Confirmation they are off the list | Who cancelled, plus the **updated** total |

The organiser address is `event_organiser_email`, falling back to the event's author (on a manually created event, that is the person running the walk). From defaults to the site admin address rather than WordPress's `wordpress@<domain>`, which rarely passes SPF; Reply-To is crossed over so the two can simply reply to each other. Transport per environment is in `docs/deployment.md` → Email.

Because email verification is not yet enforced (see above), a join today only proves control of the account, not of the address. When verification lands, the pending-join cookie should be consumed *after* the verification step, not before.

## Interaction with the Claim Flow

When a scraped event is claimed (domain-matched token email, per DataModel v3), the claim attaches the event to the **organizer term**, not just to the claiming individual. Every member of that organization then benefits from the claim. `event_claimed_by` still records which individual performed the claim.

## Phase Plan

| Feature | Phase |
|---|---|
| Event Organizer role + restricted wp-admin | V1 |
| Google OAuth login | V1 |
| Email/password registration with mandatory email verification | V1 |
| Organizer taxonomy + term `email_domain` seeded by scraper | V1 |
| Automatic attachment via email domain match (post-verification) | V1 |
| Public email provider blocklist | V1 |
| Admin manual organization assignment | V1 |
| Shared event list (`post__in` merge + `map_meta_cap`) | V1 |
| "Post as" toggle in the Organiser panel (author-only) | V1 |
| "Posted as" admin column + filter tabs (views) | V1 |
| `event_last_edited_by` meta | V1 |
| Membership management copy on `/om` and `/vilkaar` | V1 |
| Member invitations | V2 |
| Organizer claim flow attaching to organizer term | V2 |
| "For arrangører" dedicated info page | V2 |
| Self-service member management (candidate paid feature) | V3+ |

## Resolved Decisions Log

| Question | Decision |
|---|---|
| Multiple organizations per user? | No. One `organizer_id` per email. Second context = second email/account |
| Can org members post privately? | Yes — "Post as" toggle, organization is the default, author-only control |
| Member removal — who and how? | Site admin only, on request from the organization. Flat structure, no internal roles |
| Invitation permission levels? | None. All members equal (flat). Hierarchy deferred to V3 at the earliest |
| Stale membership after leaving the real organization? | No automatic detection. Removed by admin on request |

## Open Questions

- Final placement and wording of the membership management copy (short `/om` paragraph + `/vilkaar` line for V1; dedicated "For arrangører" page later).
- Monetization model and timing for organization tooling (self-service management, multiple admins, verified badge, analytics). Principle fixed: member removal is never paywalled.

---

*vandrekalender.dk — Authentication & Organization Architecture v1.1*
