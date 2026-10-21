# 0008: An organiser's own content lives in theme pattern files, not in the database

Decided 21 October 2026 with Petya ([#32](https://github.com/petyaferreira/vandrekalender-wp/issues/32)).

## Decision

One shared template, `taxonomy-event_organiser.html`, holds the organiser page's layout. What differs per organiser (the hero text, website button and official badge; the "Om …" text with its facts and source line; the FAQ) is in pattern files in the theme, one per part: `patterns/organiser-<term slug>-<part>.php`, with the parts `hero`, `about` and `faq`. The **Organiser Content** block (`vandrekalender/organiser-content`, attribute `part`) renders the current organiser's file for its part; without a file it renders nothing. The FAQ uses Details blocks. Everything computed from the walks (counts, regions, km range, next walk, summary sentence) stays in blocks.

Organisers have no term meta. A first version (PR [#113](https://github.com/petyaferreira/vandrekalender-wp/pull/113), never merged) stored the short fields as term meta and the prose in a synced pattern (`wp_block`) chosen by ID.

## Why

- **The same on every environment.** Term meta and synced patterns are database content: they would be typed in again on local, staging and production, and a pattern's post ID differs per environment. Pattern files are in git, reviewed in PRs and deployed with the theme.
- **Organisers differ.** DVL has departments and a membership, Mammutmarch has distances and cities. In a pattern, each organiser gets the rows and blocks it needs, without a facts repeater with free labels.
- **One layout.** The page design is one template; a template per organiser would copy the shared parts into each file and still need a general one for organisers without a file.
- **A new organiser gets a working page.** A scraper can add a term; its page shows the name, walks and numbers, and the parts stay empty until someone writes the files.
- **No structured FAQ needed:** the spec says not to add FAQPage markup (section 7), so the questions do not have to be data.

Cost: changing an organiser's text is a PR, not an admin edit. Draft in the block editor, copy the markup from the Code editor into the file, delete the draft.

## Do not propose

Term meta or a fields plugin for the organiser's texts, synced patterns chosen per organiser, or one template per organiser, unless one of the conditions below holds.

## Revisit when

The facts or FAQ are needed as data (FAQPage markup, filters, or JSON-LD such as the address in Organization), or organisations edit their own pages ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)): then the content has to live in the database.
