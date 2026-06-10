# Scrapers

> Source of truth for the scraping pipeline — sources, patterns, and how to add a new scraper.

**Status: stub — to be filled in when reviewing Vandrekalender_ScrapingSources_v2.docx**

---

## Sources

<!-- List of scraping sources, URLs, and status go here -->

---

## Adding a New Scraper

1. Create `includes/scrapers/class-scraper-{source}.php`
2. Extend `Vandrekalender_Scraper_Base`
3. Implement `fetch()` — returns raw HTML string
4. Implement `parse( $html )` — returns array of event arrays
5. Register the scraper in `class-scraper-scheduler.php`

Each event array passed to `upsert_event()` must include `post_title`, `event_date`, and `event_source_url` (used for deduplication). Include any other available meta fields.

<!-- Review Vandrekalender_ScrapingSources_v2.docx and expand with source-specific notes -->

---

## Deduplication Logic

<!-- How upsert_event() deduplicates by source URL go here -->

---

## Cron Schedule

<!-- WP-Cron setup, frequency, how to trigger manually go here -->
