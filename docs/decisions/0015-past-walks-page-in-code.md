# 0015: The past walks page is defined in code, not as a page in the database

Decided 28 October 2026 with Petya ([#176](https://github.com/petyaferreira/vandrekalender-wp/issues/176)).

## Decision

`/tidligere-ture/` (and `/tidligere-ture/side/N/`) is a route the plugin adds with `add_rewrite_rule()` (`Vandrekalender_Past_Walks`, `includes/class-past-walks.php`). The main query is skipped, and the theme template `past-walks.html` holds the heading, the Past Walks block and the call to action. There is no WordPress page for it. The automatic rewrite flush (`vandrekalender_events_maybe_flush_rewrite_rules()`) includes these rules in its signature, so a deploy that adds or changes them needs no "Save permalinks".

## Why

- **No setup per environment.** A page in the database has to be created by hand on local, staging and production, with the right slug and the block in it. The route exists wherever the code is deployed.
- **The URL can't be broken from wp-admin.** The page is linked from every past event, the front page and organiser pages, and it will be in the sitemap. A page could be renamed, drafted or deleted in the editor; the route can only change in a PR.
- **Pagination and month pages need rules anyway.** `/side/N/` and the month pages of step 3 (`/tidligere-ture/2026/10/`) are rewrite rules in either case. With a page, the base URL would live in the database and the rest in code.

## Do not propose

Turning the past walks page into a page edited in WordPress, or moving its pagination to the core `paged` query var: WordPress answers 404 to a paged request whose main query has no posts, and this page's main query is always empty.

## Revisit when

Its text has to be edited by someone without a PR, or the page gets an English version (#34). Then the slug has to be translatable, which Polylang does for pages and post types but not for our own rewrite rules.

The URL itself is close to a [one-way door](../principles/two-way-doors.md): once indexed and linked, changing it needs a 301 from the old one.
