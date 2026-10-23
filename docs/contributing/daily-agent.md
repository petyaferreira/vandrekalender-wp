# Daily agent

Open this when you are the daily agent, or when changing what it does.

A Claude Code scheduled cloud agent runs every day at 22:00 (Europe/Copenhagen). Its prompt only says to follow this page, so what it does is reviewed here like any other change. It exists because agents file issues but only work them when Petya starts a session, so the board only grew. It runs within Petya's Pro subscription, so every run is small and stops early. Why: [decision 0007](../decisions/0007-daily-scheduled-agent.md).

## One run

1. **Pick one issue.** Only an open issue with the `agent-ready` label, without `needs-decision`, and without an open PR for it. Take the lowest issue number (the oldest), not the board order: the sandbox blocks GitHub's GraphQL API ([decision 0013](../decisions/0013-github-rest-only-in-the-cloud.md)), so the `gh` subcommands built on it fail there. Run `./gh-rest.sh agent-ready`: it lists the open `agent-ready` issues without `needs-decision` and without an open PR that mentions them (`#<n>` in the PR body, or `<n>` in its branch name), oldest first, and the first line is yours. Never add `agent-ready` yourself, not even to issues you file during the run.
2. **Nothing to pick: stop.** Do not look for other work.
3. **Read the issue** (`gh api repos/petyaferreira/vandrekalender-wp/issues/<n>`). If it is unclear, or bigger than one small PR, comment on the issue with the question or a proposed split, remove `agent-ready`, and stop.
4. **Do the work** as in [tasks-and-issues.md](tasks-and-issues.md) and [pull-requests.md](pull-requests.md): run `git fetch origin` and branch from `origin/main`, not from the local `main`, only what the issue asks plus its [cleanup](../principles/cleanup.md), `./validate.sh`, a PR that says `Closes #<n>`. Cleanup: before opening the PR, `git grep` the old names the change replaces and fix every hit. #115 replaced the `vandrekalender.dk` domain in one table and left it in README and three docs. Skip moving the board card to In Progress: moving a card is GraphQL and fails in the sandbox. Open the PR with `./gh-rest.sh pr-create <title> <body-file>` after pushing the branch. Wherever a `gh` command fails with the GraphQL error, use `./gh-rest.sh` or the `gh api` REST call instead.

   The sandbox may reuse an old clone, so its local `main` can be days behind. On 22 October the agent branched from a `main` without decisions 0008 and 0009, numbered its record 0008, and its push conflicted with `main` ([#139](https://github.com/petyaferreira/vandrekalender-wp/issues/139)). Before every push, run `git fetch origin && git merge origin/main`, resolve any conflict (for a decision record, take the next free number), and run `./validate.sh` again.
5. **Review loop, at most two rounds.** The GraphQL `gh` commands fail here; use `./gh-rest.sh pr-checks <PR>` (name, status and conclusion of each check) and `./gh-rest.sh pr-review <PR>` (the last review summary, then the inline comments).

   Run `pr-checks` every minute until `Validate` and `claude-review` are `completed`. If neither has started 3 minutes after a push, check first whether the PR conflicts with `main`: GitHub runs no `pull_request` workflows on a conflicting PR. Run `git fetch origin && git merge-tree --write-tree HEAD origin/main`; exit code 1 means a conflict, so merge `origin/main` as above and push again. Do not ask the API for `mergeable_state`, because `.claude/settings.json` denies `gh api *merge*`. Once both checks have completed, read the last review summary and the inline comments. The review is done only when the summary ends with `Verdict: no findings`. The `claude-review` check passes whatever the verdict says: #115 stopped at a green check with `Verdict: changes requested`. Otherwise fix what is right, push, and check again. After two rounds without `no findings`, comment on the PR what is blocking and stop. Every run ends with a merge or a PR comment that says why not.
6. **Merge or hand over.** Low risk (`./pr-risk.sh <PR>`): run `./merge-low-risk.sh <PR>`. Medium or high: leave the PR for Petya.

One issue per run, even when it finished early. In the cloud there is no Docker stack: say in the PR that the change was not tested on the local stack.

## Setup in the cloud

Before `./validate.sh`, install what CI installs (`.github/workflows/ci.yml`). Run these as **four separate Bash commands, in this order, each with `timeout: 600000`**. Each fits in 10 minutes on its own, so it finishes in the foreground. Times measured on 23 October 2026 ([#154](https://github.com/petyaferreira/vandrekalender-wp/issues/154)):

```bash
# 1. Composer: 4–9 minutes
export COMPOSER_ALLOW_SUPERUSER=1
composer config --global 'preferred-install.wpackagist-plugin/*' dist
composer config --global 'preferred-install.wpackagist-theme/*' dist
composer config --global 'preferred-install.*' source
composer install --no-interaction
```

```bash
# 2. Root npm (Prettier): seconds
export PATH=/opt/node24/bin:$PATH && npm ci
```

```bash
# 3. Theme: about 1½ minutes
export PATH=/opt/node24/bin:$PATH && cd wp-content/themes/vandrekalender-theme && (npm ci || npm install)
```

```bash
# 4. Plugin: under a minute
export PATH=/opt/node24/bin:$PATH && cd wp-content/plugins/vandrekalender-events && (npm ci || npm install)
```

Do not put them in one command: on 23 October the four together ran past 10 minutes and were moved to the background ([#155](https://github.com/petyaferreira/vandrekalender-wp/issues/155)). If a command is moved to the background anyway, wait for its completion notification. Do not write wait loops (`until … pgrep …; sleep`) and do not use `pkill`: on 23 October such a loop waited 10 minutes for nothing, and `pkill` killed the agent's own shell.

Shell state does not carry over between commands, so start **every** command that runs `node`, `npm` or `./validate.sh` with `export PATH=/opt/node24/bin:$PATH`. Otherwise the sandbox's own Node 22 runs, and the `versions` check in `./validate.sh` fails.

PHP 8.4 and Node 24 come from the environment's setup script, not from the repo: the sandbox ships PHP 8.3 and Node 20–22, and we use PHP 8.4 and Node 24 everywhere ([decisions 0011](../decisions/0011-php-84-everywhere.md) and [0012](../decisions/0012-node-24-everywhere.md)). The script installs PHP 8.4 from the `ondrej/php` PPA (key from `keyserver.ubuntu.com`, packages from `ppa.launchpadcontent.net`, both on the environment's allowed domains) and Node 24 from `nodejs.org` into `/opt/node24`. It ends by checking both versions, so a session on the wrong version fails at startup ("Setup script failed") instead of running. The script only lives in claude.ai/code → environment settings; its text is in [#150](https://github.com/petyaferreira/vandrekalender-wp/issues/150).

The sandbox runs as root, and Composer then turns its plugins off. This repo needs two: one registers the WordPress standard with PHPCS (without it the `phpcs` check fails), the other installs the WordPress plugins into `wp-content/`. `COMPOSER_ALLOW_SUPERUSER=1` turns them back on; the sandbox is discarded after each run.

The sandbox's network proxy blocks `api.github.com` for every repo but this one, so Composer cannot download GitHub packages (PHPCS, PHPUnit and most others) as zips. The `preferred-install` lines make it clone them with `git` instead, which works. WordPress plugins and themes stay zips from `downloads.wordpress.org`: their fallback is `svn`, which the sandbox does not have. Do not add `--prefer-dist`, it overrides these lines. The install takes about 6½ minutes. The global config only lives in the sandbox; `composer.json`, CI and local installs are unchanged. The environment's network access must allow `wpackagist.org` and `downloads.wordpress.org` (claude.ai/code → environment settings).

Checked by a probe run on 19 October 2026 ([#81](https://github.com/petyaferreira/vandrekalender-wp/issues/81)): Composer 2.8, and `gh` can reach the repo with push rights. `gh auth status` reports the token as invalid there although API calls work; trust the API calls, not that line. The install steps above were checked by probe runs on 20 October 2026: `composer install` exits 0 and `phpcs` lists the WordPress standard.

## For Petya

- Mark a card for it: add the `agent-ready` label. Agents in your sessions add it to issues they file when the issue qualifies ([tasks-and-issues.md](tasks-and-issues.md)); remove it if you disagree. Good cards are clear and small; product-docs cards are finished completely (merged), code cards come back as a PR for review.
- See what it did: `gh pr list --author @me --state all --search "created:>=<date>"`, and merged without you: `gh pr list --state merged --label auto-merged`.
- Cost: check `/usage` the morning after. Each run also triggers one review-bot run.
- Pause or change the time: claude.ai/code → Routines.
