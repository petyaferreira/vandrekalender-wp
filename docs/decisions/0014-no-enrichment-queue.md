# 0014: No wp-admin queue of scraped events missing data

Decided 28 October 2026 with Petya ([#38](https://github.com/petyaferreira/vandrekalender-wp/issues/38)).

## Decision

There is no wp-admin screen or flag that lists scraped events missing data (no distance, no pin, no region) so they can be fixed by hand. A route without a distance is published without one, and its card shows no distance badge ([scrapers.md → Layer 3](../scrapers.md#layer-3--leave-empty-and-flag)).

## Why

Petya rarely or never goes through the events list in wp-admin (500+ events), so a queue there would not be looked at. A missing distance does not hide an event, so leaving it empty is acceptable. Problems that need attention reach Petya through the Scraper Log, the warnings in `./scrape.sh` and the alert email ([scrapers.md → Alerts](../scrapers.md#alerts-when-a-source-breaks)).

## Do not propose

A "needs enrichment" flag, meta field, admin column, filter or screen for scraped events with missing fields.

## Revisit when

Events held as drafts (no coordinates, no date) turn out to be common and go unnoticed. Then report them where Petya already looks, the Scraper Log or the alert email, not in a new queue.
