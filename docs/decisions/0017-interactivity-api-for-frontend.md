# 0017: Frontend behaviour uses the Interactivity API; plain JS only for self-contained widgets

Decided 2 November 2026 ([#202](https://github.com/petyaferreira/vandrekalender-wp/issues/202)), after a filter bug on the past walks page (PR #186).

## Decision

Frontend behaviour uses the WordPress Interactivity API (`viewScriptModule`, a store, directives, `@wordpress/interactivity-router`) whenever a script:

- updates server-rendered content (filters, "Vis flere", month navigation), or
- shares state with another block (filters and the results they drive).

Plain JavaScript (`viewScript`) is only for self-contained widgets that share nothing with other blocks and replace no server HTML: today Tabs, and the Leaflet maps on the event page (Event Route Map, Event Info Card; Event Info Card also ships a `viewScriptModule`, `join.js`). Each such file says at the top why it is plain JS, as `blocks/event-route-map/view.js` does.

Blocks talk to each other through a shared Interactivity API store, not custom DOM events (`document.dispatchEvent( new CustomEvent( 'vk:…' ) )`) and not by writing the URL with `history.replaceState` before the router navigates. The router changes the URL itself.

Known exceptions until reworked: Event Filters (`vk:filters-change`, [#203](https://github.com/petyaferreira/vandrekalender-wp/issues/203)), and the event page pair Event Info Card and Event Route Map, which talk through `vk:route-change`. When either is reworked, it moves to a shared store.

## Why

- **One way of doing it.** Most interactive blocks already use the Interactivity API (Event Cards, Event Calendar, Event Map, Past Walks, Slider), and [frontend.md](../frontend.md) says new interactive blocks follow that pattern. The exception, Event Filters, is plain JS that hands off to those blocks by hand.
- **The hand-off breaks.** Event Filters writes the new filters into the URL and then dispatches `vk:filters-change`. On the past walks page the router loaded only after that, cached the old list under the new URL and showed it instead of fetching (fixed in PR #186, commit bdf2f54, with `navigate( url, { force: true } )`). A shared store and the router's own `navigate()` avoid that class of bug.
- **It is the WordPress API for this job**, maintained with core, so its rules are documented, not ours to invent.

## Rejected

Plain JS with custom DOM events between blocks, as Event Filters does today.

## Costs we accept

Module scripts and the router are heavier than a few lines of plain JS. Generator actions and `getElement()` take some learning.

## Do not propose

New `vk:…` document events, or a new interactive block as a plain `viewScript`, to save a few lines.

## Revisit when

The Interactivity API or its router is deprecated or replaced in WordPress core, or a block needs behaviour it cannot express.
