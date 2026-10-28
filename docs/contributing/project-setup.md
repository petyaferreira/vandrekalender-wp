# Project setup outside the repo

Open this when setting up a new project from this one, or checking this one.

Most of the Claude setup travels with the repo: `CLAUDE.md`, `docs/`, `validate.sh`, `pr-risk.sh`, `merge-low-risk.sh`, `.github/`, `.claude/settings.json`. The parts below live in GitHub and claude.ai, so a copy of the repo does not bring them. Values collected on 23 October 2026.

- [ ] **Board.** GitHub Projects (user project) "Alle Vandreture", number 1. Status field: Backlog, Next, In Progress, Done. Built-in workflows, all on: Auto-add to project, Auto-add sub-issues to project, Item added to project (→ Backlog), Pull request linked to issue (→ In Progress), Item closed (→ Done), Pull request merged (→ Done), Auto-close issue. For a new project, **Make a copy** of the board: it copies the views, fields and workflows but not the auto-add workflow, which must be pointed at the new repo again.
- [ ] **Labels.** Run `./setup-labels.sh` (needs `gh` logged in for the repo; safe to run again). It creates or updates: `bug`, `documentation`, `enhancement`, the areas `scrapers`, `admin`, `frontend` (project-specific), `needs-decision`, `later`, `agent-ready` and `auto-merged`. `dependencies`, `javascript` and `php` are created by Dependabot and are not in the script.
- [ ] **Repo secret** `CLAUDE_CODE_OAUTH_TOKEN` (from `claude setup-token`), used by `claude-code-review.yml` and `claude.yml`. The staging deploy's secrets are listed in [deployment.md](../deployment.md).
- [ ] **Claude GitHub App** installed on the repo. Cloud sessions and the review bot need it.
- [ ] **Cloud environment** (claude.ai/code → environments). Network access must allow `wpackagist.org` and `downloads.wordpress.org` besides the default list. No network secrets: GitHub goes through Anthropic's GitHub proxy (REST only). The setup script is in [#150](https://github.com/petyaferreira/vandrekalender-wp/issues/150); see [daily-agent.md](daily-agent.md#setup-in-the-cloud).
- [ ] **Nightly routine** (claude.ai/code → Routines): "Vandrekalender daily agent", cron `0 21 * * *` UTC, model Sonnet 5.5, tools Bash/Read/Write/Edit/Glob/Grep, no connectors. Prompt:

  > You are the daily agent for the Vandrekalender repo. Read CLAUDE.md, then do exactly one run as described in docs/contributing/daily-agent.md, including its Setup in the cloud section. Everything you need is in the repo; this prompt adds nothing to it.
  >
  > If something the page needs is missing or fails in this environment, do not work around it and do not change any guardrail: stop, and end with a short report of what was missing. Always end with a short report: which issue you picked (or none), the PR link, and whether it was merged or left for Petya.

- [ ] **Dependabot.** `.github/dependabot.yml` is in the repo; alerts and security updates are switched on in the repo settings.
