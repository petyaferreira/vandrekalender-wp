# 0018: Old walks are archived after 12 months and deleted 3 years later

Decided 28 October and 10 November 2026 ([#37](https://github.com/petyaferreira/vandrekalender-wp/issues/37)). Steps: [plans/past-walks-retention-plan.md](../plans/past-walks-retention-plan.md).

## Decision

- A walk older than `Vandrekalender_Past_Walks::RETENTION_MONTHS` (12) months gets the status `archived`, automatically, unless its "Behold" flag is ticked. An archived walk answers 410 Gone and is left out of every list; its row and files stay.
- An archived recurring occurrence keeps the existing 301 to the next upcoming occurrence. No other 301s.
- 3 years after it was archived, the walk is deleted for good, with its attendees and its images and GPX files, but only files nothing else uses.
- "Behold" is a checkbox on each walk, off by default, with a column and a "Due for removal" filter in the Events list. No separate admin screen.

## Why

- **Undoable.** Deleting at 12 months loses the data at once; a bug in the job or a wrong cutoff could not be fixed. Archived walks can be restored ("Gendan").
- **Space is cheap.** A walk is one post row and a few dozen meta rows (about 5–20 KB); thousands of archived walks a year are a few tens of MB.
- **Shared files.** Editors can pick one Media Library image for several walks, so files are only deleted after checking nothing else uses them.
- **Nobody has to act.** Removal runs on its own; checking Search Console before it is optional.

## Do not propose

- Deleting walks (or their images) at the 12-month mark.
- A separate "keep list" admin screen instead of the flag.
- Redirecting archived walks to the front page or another page without a real match (Google treats it as a soft 404).

## Revisit when

The database or uploads grow enough to matter, or Search Console shows that old walk pages still bring traffic (then keep them longer, or keep them automatically from the Search Console API).
