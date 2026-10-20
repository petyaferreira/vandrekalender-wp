# 0008: An organiser's about text, facts and FAQ live in a synced pattern, not in term fields

Decided 20 October 2026 with Petya ([#32](https://github.com/petyaferreira/vandrekalender-wp/issues/32)).

## Decision

An organiser (an `event_organiser` term) keeps only short values as term meta: short description, website, official badge, source URL and date, and which pattern holds its page content (`Vandrekalender_Organiser_Fields`). The prose on its page is one synced pattern (`wp_block`) per organiser, edited in the block editor: the "Om …" text, a facts table or list, and the FAQ as Details blocks. The organiser page template (plan step 5b) renders the chosen pattern between the walks and the computed numbers.

## Why

- **Organisers differ.** DVL has departments and a membership, Mammutmarch has distances and cities. In a pattern, each organiser gets the rows and blocks it needs. Term fields would need a facts repeater with free labels, built and validated by hand.
- **No repeater to build or install.** WordPress has no repeater field for terms, ACF's repeater is Pro only, and a fields plugin would be a new dependency for seven organisers. The block editor already has paragraphs, links, tables and the Details block (native `<details>`/`<summary>`, as the design spec asks).
- **No structured FAQ needed:** the spec says not to add FAQPage markup (section 7), so the questions do not have to be data.
- **One layout:** the template stays shared, so changing the page design is one edit, not one per organiser (unlike a template per term).

## Do not propose

Facts or FAQ repeaters as term meta, a fields plugin for them, or one block template per organiser, unless the page needs the facts as data (see below).

## Revisit when

The facts or FAQ are needed as data: FAQPage markup becomes worth it again, the facts feed filters or JSON-LD (e.g. the address in Organization), or organisations edit their own pages ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)) and must not get the full block editor.
