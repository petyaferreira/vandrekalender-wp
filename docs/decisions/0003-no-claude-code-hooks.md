# 0003: No Claude Code hooks; the pre-commit hook and `./validate.sh` do the checking

Decided 14 October 2026 with Petya ([#48](https://github.com/petyaferreira/vandrekalender-wp/issues/48)).

## Decision

No hooks in `.claude/settings.json`. The first build plan wanted a `PostToolUse(Edit)` hook that runs PHPCS after every edit; it was dropped.

## Why

Recorded in #48: the pre-commit hook already covers PHPCS. Since then `./validate.sh` (#55) checks everything in one command and CI runs the same script, so the check works the same for every agent and person, in any tool, not only in Claude Code.

## Do not propose

Claude Code hooks that run PHPCS, Prettier or builds. Add a check to `./validate.sh` instead ([harness-engineering.md](../contributing/harness-engineering.md)).

## Revisit when

A check is needed that cannot run as a command at commit or validate time.
