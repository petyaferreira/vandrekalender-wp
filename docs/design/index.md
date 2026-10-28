# Design

Current design references for the site. Only files that match the site, or the design it is moving to, belong here; outdated ones go to [history/mockups/](../history/index.md). How the frontend is built: [frontend.md](../frontend.md).

| File | What it is |
|---|---|
| `logo-requirements.docx`, `logo-requirements.pdf` | Requirements for the logo |
| `organiser-page/` | Claude Design for the organiser pages (19 October 2026): `Main.dc.html` mobile, `Desktop.dc.html` desktop, `Kort.dc.html` the organiser on event cards (replaced by `tur-kort-2a.html`, 23 October 2026: date and organiser in one row, all tags alike) and the info card, `arrangoer-header-1b.html` the organiser page header with the numbers panel beside the intro (23 October 2026, replaces the hero and stats of `Desktop.dc.html`/`Main.dc.html`), `organiser-page-spec.md` the build spec and draft content. Built as in [the organisers plan](../history/organisers-plan.md), which wins where they differ. Updated 28 October 2026: "Om" and the FAQ side by side without a facts box, "Ture i hele landet" beside "Tidligere ture" (the organiser's last 3 past walks) |
| `passed-events-page/` | Claude Design for the past walks page `/tidligere-ture/` (28 October 2026): `Tidligere.dc.html` mobile, `TidligereDesktop.dc.html` desktop, `past-walks-spec.md` the build spec (month-grouped list, month pages, retention). Build: [#176](https://github.com/petyaferreira/vandrekalender-wp/issues/176) |

Design tokens (colours, type, spacing) live in code: `wp-content/themes/vandrekalender-theme/theme.json`.
