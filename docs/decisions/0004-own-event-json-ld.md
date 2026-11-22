# 0004: Our own JSON-LD (events and organiser pages) is the only structured data; Rank Math's schema stays off

Decided 13 August 2026, when `Vandrekalender_Event_Schema` was added.

## Decision

`includes/class-event-schema.php` prints the schema.org/Event JSON-LD on event pages: one block on upcoming events, nothing on past events or events without a location. `includes/organiser/class-organiser-schema.php` (#224) prints one block on organiser pages (Organization, WebPage, BreadcrumbList and a list of upcoming walks). These two classes are the only structured data on the site. Rank Math stays installed for Open Graph, Twitter and robots meta and its XML sitemap, and emits no structured data of its own.

## Why

From the class docblock: "One canonical Event block is emitted per upcoming event. Past events output nothing — expired Event markup is index noise and trips Search Console errors." A second source would break the "one canonical block" rule. The organiser class follows the same approach, so the organiser page does not extend Rank Math's schema through `rank_math/json_ld` as the design spec's section 7 suggests.

Checked on 17 October 2026 on a production event page (`/begivenhed/over-stok-og-sten-silkeborg-2026/`): exactly one `application/ld+json` block, `@type: Event`, with Rank Math active.

## Do not propose

Turning on Rank Math's Event schema, or a "set Rank Math schema type to None" step: it emits none today.

## Revisit when

Rank Math is reconfigured or replaced. Re-check that an event page has exactly one Event block and an organiser page exactly one JSON-LD block.
