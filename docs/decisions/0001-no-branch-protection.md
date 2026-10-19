# 0001: No branch protection on `main`

Decided 14 October 2026 with Petya ([#48](https://github.com/petyaferreira/vandrekalender-wp/issues/48)).

## Decision

`main` has no branch protection rules and no required status checks. Agents never push to `main`, and merge only low-risk PRs through `./merge-low-risk.sh`, which refuses unless every check is green ([0006](0006-agents-merge-low-risk-prs.md)). Petya merges the rest.

## Why

Recorded in #48 only as "not wanted"; the longer reason was not written down.

## What follows from it

Checks are information, not gates. A red check does not stop a merge, which is what lets Petya merge a PR that changes the review workflow after reviewing it by hand, although its `claude-review` check is red by design ([pull-requests.md](../contributing/pull-requests.md)). What protects production is the deploy: staging only deploys after `./validate.sh` passes on `main` (`needs: validate` in `ci.yml`).

## Do not propose

Required status checks, required reviews, or "protect main" settings.

## Revisit when

A second person gets merge rights, or agents merge more than low-risk PRs (0006).
