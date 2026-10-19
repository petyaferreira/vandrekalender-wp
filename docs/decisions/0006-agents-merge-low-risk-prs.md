# 0006: Agents merge low-risk PRs, only through `./merge-low-risk.sh`

Decided 19 October 2026 with Petya ([#74](https://github.com/petyaferreira/vandrekalender-wp/issues/74)).

## Decision

PRs fall into three risk categories, decided by `./pr-risk.sh` from the files a PR changes ([pull-requests.md → Risk categories](../contributing/pull-requests.md#risk-categories)). Agents may merge **low**-risk PRs (product docs) themselves, and only with `./merge-low-risk.sh`. Petya merges medium (theme and plugin code) and high (guardrails) risk PRs.

The script, not branch protection, stops a red merge. Decision [0001](0001-no-branch-protection.md) stays: `main` has no branch protection.

## Why

Petya's review time is the scarce resource, and a docs PR that passed Validate and the review bot rarely needs it.

- **A script decides the category, not the agent**, so an agent cannot mark its own PR "low". A file no rule names is high.
- **Guardrails are high**: `validate.sh`, the workflows, `tests/`, `CLAUDE.md`, `docs/contributing/`, `docs/principles/`, `docs/decisions/`. An agent that could change these and merge the change itself could switch off its own checks or rewrite its own rules.
- **A merge script rather than branch protection** (option B): Petya pushes small things straight to `main` (package updates, for example), and merges PRs that change the review workflow while their `claude-review` check is red by design. Required checks would block both. On `main`, `./validate.sh` still runs on every push, and staging deploys only when it passes.
- **Agents cannot go around the script in Claude Code**: `.claude/settings.json` (shared, in the repo) denies `gh pr merge`, merging through `gh api`, force pushes and pushes to `main`. Before this, those rules lived only in one machine's untracked `settings.local.json`. These are permission rules, not the hooks that decision [0003](0003-no-claude-code-hooks.md) rules out.
- **The worst case is staging**: a merge to `main` deploys to staging; production is deployed by hand.

## Do not propose

- Agents merging medium or high risk PRs, or merging any PR without the script.
- Raising a file's category in a PR that an agent then merges: changing `pr-risk.sh` is itself high risk.
- Branch protection as the gate (see 0001).

## Revisit when

- An auto-merged PR breaks staging or turns out wrong: tighten the low list first.
- Low-risk merges have run without problems for a while and Petya wants a category widened.
- Agents other than Claude Code start opening PRs: the deny rules only bind Claude Code, others rely on `CLAUDE.md`.
