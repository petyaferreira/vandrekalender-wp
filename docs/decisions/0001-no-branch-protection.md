# 0001: No branch protection on `main`

Decided 14 October 2026 with Petya ([#48](https://github.com/petyaferreira/vandrekalender-wp/issues/48)).

## Decision

`main` has no branch protection rules and no required status checks. Petya is the only person who merges; agents never merge or push to `main` (`CLAUDE.md`).

## Why

Recorded in #48 only as "not wanted"; the longer reason was not written down.

## What follows from it

Checks are information, not gates. A red check does not stop a merge, which is what lets Petya merge a PR that changes the review workflow after reviewing it by hand, although its `claude-review` check is red by design ([pull-requests.md](../contributing/pull-requests.md)). What protects production is the deploy: staging only deploys after `./validate.sh` passes on `main` (`needs: validate` in `ci.yml`).

## Do not propose

Required status checks, required reviews, or "protect main" settings.

## Revisit when

A second person gets merge rights.
