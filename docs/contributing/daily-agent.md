# Daily agent

Open this when you are the daily agent, or when changing what it does.

A Claude Code scheduled cloud agent runs every day at 22:00 (Europe/Copenhagen). Its prompt only says to follow this page, so what it does is reviewed here like any other change. It exists because agents file issues but only work them when Petya starts a session, so the board only grew. It runs within Petya's Pro subscription, so every run is small and stops early.

## One run

1. **Pick one issue.** Only an open issue with the `agent-ready` label, without `needs-decision`, and without an open PR for it (`gh pr list --state open --search "<number> in:body"`). Take the one highest on the board (`gh project item-list 1 --owner petyaferreira --format json`, board order); if the board cannot be read, the lowest issue number. Only Petya adds `agent-ready`; never add it yourself.
2. **Nothing to pick: stop.** Do not look for other work.
3. **Read the issue** (`gh issue view <n>`). If it is unclear, or bigger than one small PR, comment on the issue with the question or a proposed split, remove `agent-ready`, and stop.
4. **Do the work** as in [tasks-and-issues.md](tasks-and-issues.md) and [pull-requests.md](pull-requests.md): branch from `main`, only what the issue asks, `./validate.sh`, a PR that says `Closes #<n>`.
5. **Review loop, at most two rounds.** Wait for the checks (`gh pr checks <PR> --watch`), read the review, fix what is right. If Validate or the review is still not green after two rounds, comment on the PR what is blocking and stop.
6. **Merge or hand over.** Low risk (`./pr-risk.sh <PR>`): run `./merge-low-risk.sh <PR>`. Medium or high: leave the PR for Petya.

One issue per run, even when it finished early. In the cloud there is no Docker stack: say in the PR that the change was not tested on the local stack.

## For Petya

- Mark a card for it: add the `agent-ready` label. Good cards are clear and small; product-docs cards are finished completely (merged), code cards come back as a PR for review.
- See what it did: `gh pr list --author @me --state all --search "created:>=<date>"`, and merged without you: `gh pr list --state merged --label auto-merged`.
- Cost: check `/usage` the morning after. Each run also triggers one review-bot run.
- Pause or change the time: claude.ai/code → Routines.
