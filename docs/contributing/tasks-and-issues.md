# Tasks and issues

Open this when starting work, picking the next task, or finding a problem outside the current task.

GitHub Issues (`petyaferreira/vandrekalender-wp`) is the project's only to-do list. There is no separate board tool.

- **Something found during work that is not part of the current task** is fixed now, filed as an issue, or dropped. Never a note in a doc or a TODO in code. Every issue is a card someone has to work later, so the bar is high:
  - **Fix it in the current PR** when it is small, low-risk and in files the PR already changes. Say so in the PR description. Example: a stale line in a doc the PR already edits.
  - **File an issue** only when all three hold: it is real (checked, for example on production, not only locally), it will matter again (not a one-off), and the next agent can act on it without this session: what is wrong, where, and how to see it. Do not file one-off confusion, nits that only matter to this session, or guesses.
  - **Search first**: `gh issue list --state open --search "<words>"`. If an open issue covers it, comment there instead of filing a second one.
  - Do not ask Petya before filing: create it with `gh issue create` (a clear title, what is wrong or wanted, where it was found, and labels), and tell Petya its number and title in the next message. If it turns out not to be a problem, say so instead.
- **Work starts from an issue.** Read the issue (`gh issue view <n>`) before starting. If Petya asks for something that has no issue yet, create one first.
- **Branches** for issue work branch from `main`: `fix/<issue>-<short-name>` for bugs, `feature/<issue>-<short-name>` otherwise (for example `fix/30-opdagverden-landmarks`). Steps of a planned feature follow [pull-requests.md → Planned features](pull-requests.md#planned-features).
- **PRs** say `Closes #<n>` in the description, so merging closes the issue. A PR that does only part of an issue says `Part of #<n>` instead.
- **Priority comes from the project board** "Alle Vandreture" (GitHub Projects, project number 1, columns Backlog → Next → In Progress → Done). It decides *which* issue is next: the top card in **Next** (`gh project item-list 1 --owner petyaferreira --format json`, items come in board order). Petya sets the order; do not reorder cards. A card labelled `needs-decision` is skipped; ask Petya instead.
  - When work starts, move the card to **In Progress**: `gh project item-edit --project-id PVT_kwHOAG4YNs4BnB9o --id <item id from item-list> --field-id PVTSSF_lAHOAG4YNs4BnB9ozhlob-s --single-select-option-id 47fc9ee4` (IDs from `gh project list` and `gh project field-list 1 --owner petyaferreira --format json`; refresh them if the Status field is recreated). Needs the `project` scope on the local `gh` login (`gh auth refresh -s project`); if the move fails, say so and carry on.
  - The board's own workflows (set up in GitHub, not in the repo) put new issues in **Backlog** and move a card to **Done** when its issue closes, i.e. when the PR with `Closes #<n>` is merged. `gh project item-list` can lag a few minutes behind the board, so a brand-new issue may be missing from it for a while; that is not a failed auto-add.
- **Labels:** `bug`, `enhancement`, `documentation`, plus the area (`scrapers`, `frontend`, `admin`), `needs-decision` (Petya decides before any work starts), `agent-ready` (Petya marked it for the [daily agent](daily-agent.md); only Petya adds it) and `v2` (later, after v1).
- **Docs describe how things work now.** Open work in a doc is a one-line link to its issue, not a to-do list. When an issue is closed, update the doc so it describes the finished behaviour and remove the link.
- **Plan files** (`docs/plans/<feature>-plan.md`) are only for features that need several PRs. A plan links to its issue, and gets a short status note when a step is done, dropped or moved. When the last step is merged, the topic docs describe the result and the plan moves to `docs/history/`.
