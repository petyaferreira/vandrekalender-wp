# Keep agent context lean

Agents load guidance on demand. Keep the entry map tiny, put detail one hop away in focused pages, and do not repeat the same rule in several files.

## Rules

- `CLAUDE.md` is a map, not an encyclopedia. Every agent reads it on every task, so a line belongs there only if almost every task needs it. The `claude-md-size` check in `./validate.sh` enforces the line budget; raise `MAX_CLAUDE_MD_LINES` only on purpose.
- Detail lives in focused pages: [docs/](../index.md) (how the product works), [docs/contributing/](../contributing/index.md) (how we work), [docs/principles/](index.md) (how we build).
- Progressive disclosure: an agent opens the map, then a folder's `index.md`, then only the page the task needs. Each index says when to open each page.
- One rule, one place. Link to it instead of copying it.
- A rule that only lives in chat or in one machine's local agent memory does not exist for the next agent or the review bot. Move it into the repo.
- When behaviour changes, update the page that owns it in the same PR.

## Related

- [Examples over prose](examples-over-prose.md)
- [Harness engineering](../contributing/harness-engineering.md)
