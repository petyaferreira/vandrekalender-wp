# 0007: A scheduled agent works one `agent-ready` issue a day, within the Pro subscription

Decided 19 October 2026 with Petya ([#79](https://github.com/petyaferreira/vandrekalender-wp/issues/79)).

## Decision

A Claude Code scheduled cloud agent runs once a day at 22:00 (Europe/Copenhagen) and works one issue labelled `agent-ready`, following [daily-agent.md](../contributing/daily-agent.md). Low-risk PRs it merges itself ([0006](0006-agents-merge-low-risk-prs.md)); others wait for Petya.

## Why

- Agents filed issues but worked them only when Petya started a session, so the board only grew. Kody solves this with a daily sweep over its `friction` issues.
- **Budget:** only Petya's Pro subscription, no paid usage. One issue per run, stop early, at most two review rounds keeps a run small and its cost predictable. 22:00 leaves the 5-hour session window reset by morning and does not compete with Petya's own sessions. Each run also triggers one review-bot run on the same subscription.
- **Only `agent-ready`:** the daily agent never decides what is worth doing or clear enough. Kody does the same the other way round (`friction-skipped`). Until 23 October 2026 only Petya added the label, and issues waited for her. Since then the agent that files an issue in a session with Petya adds it when the issue can be done alone in one small PR ([#140](https://github.com/petyaferreira/vandrekalender-wp/issues/140), [tasks-and-issues.md](../contributing/tasks-and-issues.md)). The daily agent still never labels its own issues, so it cannot choose its own next work.
- **Instructions in the repo**, not in the scheduler prompt, so changes to them are reviewed in PRs.

## Do not propose

More than one issue per run, more runs a day, or the agent choosing issues without `agent-ready`, without checking `/usage` with Petya first.

## Revisit when

The weekly limit runs out because of the agent, or runs keep finishing with room to spare and Petya wants more done.
