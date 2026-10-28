# Decisions

Decisions already taken, so nobody proposes the opposite again without knowing why. Check here before proposing a change to how the project is set up, built or run. To change a decision, ask Petya; if she agrees, update the record (or mark it replaced) in the same PR as the change.

| # | Decision | Date |
|---|---|---|
| [0001](0001-no-branch-protection.md) | No branch protection on `main` | 14 Oct 2026 |
| [0002](0002-no-frontend-event-form.md) | No frontend event form; organisers use the WordPress editor | 14 Oct 2026 |
| [0003](0003-no-claude-code-hooks.md) | No Claude Code hooks; the pre-commit hook and `./validate.sh` do the checking | 14 Oct 2026 |
| [0004](0004-own-event-json-ld.md) | Our own Event JSON-LD is the only structured data; Rank Math's schema stays off | 13 Aug 2026 |
| [0005](0005-unit-tests-without-wordpress.md) | Tests run with plain PHPUnit and stand-ins, not the WordPress test suite | 19 Oct 2026 |
| [0006](0006-agents-merge-low-risk-prs.md) | Agents merge low-risk PRs, only through `./merge-low-risk.sh` | 19 Oct 2026 |
| [0007](0007-daily-scheduled-agent.md) | A scheduled agent works one `agent-ready` issue a day, within the Pro subscription | 19 Oct 2026 |
| [0008](0008-organiser-pages-as-post-type.md) | Organiser pages are a post type, linked to the organiser taxonomy | 21 Oct 2026 |
| [0009](0009-assets-versioned-by-build.md) | The plugin's own scripts and styles are versioned by the build's content hash, not by file time | 22 Oct 2026 |
| [0010](0010-leaflet-bundled-from-npm.md) | Leaflet and its plugins are bundled from npm, not loaded from a CDN | 8 Oct 2026 |
| [0011](0011-php-84-everywhere.md) | PHP 8.4 everywhere, the version Nordicway runs | 23 Oct 2026 |
| [0012](0012-node-24-everywhere.md) | Node 24 everywhere, set once in `.nvmrc` | 23 Oct 2026 |
| [0013](0013-github-rest-only-in-the-cloud.md) | In the cloud, agents use the GitHub REST API only; GraphQL is blocked by design | 23 Oct 2026 |
| [0014](0014-no-enrichment-queue.md) | No wp-admin queue of scraped events missing data | 28 Oct 2026 |
| [0015](0015-past-walks-page-is-a-page.md) | The past walks page `/tidligere-ture/` is a normal page built from a pattern, not a route and template in code | 28 Oct 2026 |

## Format

A record is written in the same PR as the change that makes the decision ([pull-requests.md](../contributing/pull-requests.md#before-opening-a-pr)). A new record is the next number, `NNNN-short-name.md`, even when the decision was taken earlier: numbers follow the order records were written, and the date is when the decision was taken (0004 and 0010 are dated before the record above them). A record is only for a decision that would otherwise be proposed again. A two-way tweak needs no record ([two-way doors](../principles/two-way-doors.md)).

```markdown
# NNNN: <the decision, as a statement>

Decided <date> (<issue link>).

## Decision
## Why
## Do not propose
## Revisit when
```
