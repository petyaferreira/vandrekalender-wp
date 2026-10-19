# Decisions

Decisions already taken, so nobody proposes the opposite again without knowing why. Check here before proposing a change to how the project is set up, built or run. To change a decision, ask Petya; if she agrees, update the record (or mark it replaced) in the same PR as the change.

| # | Decision | Date |
|---|---|---|
| [0001](0001-no-branch-protection.md) | No branch protection on `main` | 14 Oct 2026 |
| [0002](0002-no-frontend-event-form.md) | No frontend event form; organisers use the WordPress editor | 14 Oct 2026 |
| [0003](0003-no-claude-code-hooks.md) | No Claude Code hooks; the pre-commit hook and `./validate.sh` do the checking | 14 Oct 2026 |
| [0004](0004-own-event-json-ld.md) | Our own Event JSON-LD is the only structured data; Rank Math's schema stays off | 13 Aug 2026 |
| [0005](0005-unit-tests-without-wordpress.md) | Tests run with plain PHPUnit and stand-ins, not the WordPress test suite | 19 Oct 2026 |

## Format

A record is written in the same PR as the change that makes the decision ([pull-requests.md](../contributing/pull-requests.md#before-opening-a-pr)). A new record is the next number, `NNNN-short-name.md`, and only for a decision that would otherwise be proposed again. A two-way tweak needs no record ([two-way doors](../principles/two-way-doors.md)).

```markdown
# NNNN: <the decision, as a statement>

Decided <date> (<issue link>).

## Decision
## Why
## Do not propose
## Revisit when
```
