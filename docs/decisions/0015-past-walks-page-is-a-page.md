# 0015: The past walks page is a normal page built from a block and a pattern, not a route and template in code

Decided 28 October 2026 with Petya ([#176](https://github.com/petyaferreira/vandrekalender-wp/issues/176), PR [#186](https://github.com/petyaferreira/vandrekalender-wp/pull/186)).

## Decision

`/tidligere-vandreture/` (`/tidligere-ture/` until 10 November 2026, [#257](https://github.com/petyaferreira/vandrekalender-wp/issues/257)) is a normal WordPress page (slug `tidligere-vandreture`, template "Page (without title)"; the default until #255). It is built like the front page: the Event Filters block (set to Past) and below it the Past Walks block with the results (2 November 2026). Around them: an H1 Heading block (wide, lined up with the list), the intro as a Paragraph and the theme pattern "Call to action: upcoming walks" (`patterns/upcoming-walks-cta.php`). No template and no whole-page pattern. "Vis flere ture" is `?side=N`, a query parameter, not a URL rule. The plugin only adds what the page can't set itself: noindex on filtered and `?side=` URLs, and the links to the page (found by its slug, left out while it doesn't exist).

## Why

- **No templates for single pages.** The site's pages share a few page templates; sections are patterns and blocks. A template per page multiplies templates without need, and so would a pattern that is the whole page.
- **It works like the other pages.** Title, meta description and the content around the list are edited in WordPress and Rank Math, without a PR.
- **No URL rules of our own.** `?side=N` needs none, and it is the parameter the calendar list already uses.

## Rejected

The first version of #186: a route defined in code (`add_rewrite_rule()` for `/tidligere-ture/` and `/tidligere-ture/side/N/`), the main query skipped, and a theme template `past-walks.html`. It needed no setup per site and the URL couldn't be changed in wp-admin, but it added a template for one page and its own title, description and canonical code.

Also rejected (2 November 2026): one Past Walks block holding its own intro, tabs and filter form. The calendar's Event Filters block already does the filtering, and the front page shows the pattern: a filters block, then a results block.

## Costs we accept

- **One manual step per site:** create the page with its blocks and the pattern on local, staging and production (`docs/frontend.md` → Past Walks Page).
- **The page can be renamed or deleted in wp-admin.** The links find it by the slug `tidligere-vandreture`; a changed slug drops them (no broken links) and needs a 301 from the old URL, like any indexed page ([two-way doors](../principles/two-way-doors.md)).

## Do not propose

A template or a code route for the past walks page, unless the conditions below change.

## Revisit when

The month pages of #176 step 3 (`/tidligere-vandreture/2026/10/` in the spec) are built: they need a rule that points to this page, or the month as a query parameter too. Decide that in step 3.
