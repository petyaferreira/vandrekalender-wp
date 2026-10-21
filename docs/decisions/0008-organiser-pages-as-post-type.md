# 0008: Organiser pages are a post type, linked to the organiser taxonomy

Decided 21 October 2026 with Petya ([#32](https://github.com/petyaferreira/vandrekalender-wp/issues/32)).

## Decision

An organiser's page is a post of the post type `organiser` (`/arrangoerer/<slug>/`), linked to its `event_organiser` term. The taxonomy stays what events, scrapers, filters and counts use (plan steps 1–4); the post only holds the page.

- **Edited in the block editor**, like the site's other pages. A new organiser post starts from the section patterns (hero, stats, results, about with regions, FAQ), locked so only their content can change: texts, images, links, FAQ questions, not the order or the sections.
- **Computed sections are blocks** inside those patterns (numbers, walks, regions); they count from the linked term. "Andre arrangører" is in the post type's template, below the content.
- **Content lives in the database** on each environment, like every other page. Production is where it is written; local and staging are test copies.

## Why

- **Flexible content.** Organisers differ (DVL has departments and a membership, Mammutmarch has distances and cities). In the editor each one gets the text, images and FAQ it needs, without fields designed in advance.
- **Organisers can later edit their own page** ([#86](https://github.com/petyaferreira/vandrekalender-wp/issues/86)). WordPress already separates editing your own post from editing others', keeps revisions, and the locked patterns keep their page in the site's design. Still to build in #86: several members per organisation, and review before changes to a published page go live.
- **The taxonomy keeps working.** Moving events from the taxonomy to a post type would redo steps 1–4 (scrapers, filters, admin column, info card) for nothing the page needs.

## Rejected

On the way to this, in PR [#113](https://github.com/petyaferreira/vandrekalender-wp/pull/113) (never merged):

- **Term meta plus a synced pattern chosen by ID:** the texts are fields designed in advance, and the pattern's ID differs per environment.
- **Theme pattern files per organiser** (`patterns/organiser-<slug>-<part>.php`), or one data file per organiser: the content would be in git and changed only by a PR, three files per organiser, and organisers could never edit their own page.
- **One template per organiser:** copies the layout into every file.

## Do not propose

Moving events from the taxonomy to the post type, or the organiser's texts in term meta or in theme files, unless the conditions below change.

## Revisit when

The facts or FAQ are needed as data (FAQPage markup, filters, JSON-LD such as the address in Organization): then those parts need fields, on the post.
