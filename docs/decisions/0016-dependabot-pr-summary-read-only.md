# 0016: Dependabot PRs get a two-line summary from a read-only Claude, posted by the workflow

Decided 3 November 2026 ([#232](https://github.com/petyaferreira/vandrekalender-wp/pull/232)).

## Decision

`.github/workflows/dependabot-summary.yml` posts one comment on each Dependabot PR: **What changed** and **Check after merging**. Claude gets `Read`, `Grep` and `Glob` only, with `/proc`, `.git` and the runner's home and temp folders denied, and returns structured output. The workflow, not Claude, posts the comment, after refusing anything that looks like a token or is longer than a short sentence. It runs on a Dependabot-scoped copy of `CLAUDE_CODE_OAUTH_TOKEN`, because a workflow started by Dependabot cannot read repository secrets.

## Why

Release notes and changelogs in a Dependabot PR are written by the package's authors, outside the project: untrusted text. A crafted note could tell Claude to run a command, call GitHub or repeat a secret in the public comment. With no shell, no network and no GitHub access, Claude can only read the repo and answer; the posting step and its token check are the last line before anything is public.

The full Claude review skips Dependabot PRs ([#146](https://github.com/petyaferreira/vandrekalender-wp/issues/146)): it sees only a version number and a lockfile diff, cannot read changelogs, and costs a full review from the Pro subscription. `Validate` is the real test of an update. What Petya still lacked was what the update changes and what to check after merging. This summary gives that at the cost of one short read-only run, and only when the version changes (the PR title), not on a rebase.

## Do not propose

- Giving Claude `Bash`, `gh` or a GitHub token so it can post the comment itself.
- Running the full `claude-code-review.yml` review on Dependabot PRs instead.
- Dropping the summary and reading release notes by hand.
- Putting the release notes into a shell command or the prompt text, instead of files Claude reads.

## Revisit when

`claude-code-action` can post a comment without giving Claude a write-capable tool or token, or Dependabot PRs are no longer reviewed by Petya (for example, merged automatically after `Validate`).
