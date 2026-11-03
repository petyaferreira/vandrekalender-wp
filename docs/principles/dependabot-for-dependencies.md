# Dependabot watches every dependency file

When a project gets dependencies, it gets `.github/dependabot.yml` in the same PR. Every folder with a committed `package.json` (npm) or `composer.json` (Composer) has an entry there. A dependency nobody watches stays on the version it was added with, and its security fixes go unnoticed.

The `dependabot` check in `./validate.sh` enforces this: it fails when a committed `package.json` or `composer.json` has no entry, and names the entry to add.

## Example

`.github/dependabot.yml` in this repo has one entry per dependency file:

```yaml
version: 2
updates:
  - package-ecosystem: npm
    directory: /
    schedule:
      interval: weekly
  - package-ecosystem: npm
    directory: /wp-content/plugins/vandrekalender-events
    schedule:
      interval: weekly
  - package-ecosystem: npm
    directory: /wp-content/themes/vandrekalender-theme
    schedule:
      interval: weekly
  - package-ecosystem: composer
    directory: /
    schedule:
      interval: weekly
```

Once a week, Dependabot opens a PR for each dependency with a newer version. It opens a PR straight away when a security advisory affects one. `Validate` runs on those PRs like on any other; the review bot skips them, because it only sees a version number and a lockfile, so `Validate` (and for frontend packages a look at the site) is the real test ([#146](https://github.com/petyaferreira/vandrekalender-wp/issues/146)). Composer covers the WordPress plugins installed from `composer.json` (Rank Math, Polylang, Fluent Forms and others) as well as the dev tools.

Leaflet was loaded from cdnjs, where nothing told us about new versions. Moving it to npm with Dependabot fixed that ([decision 0010](../decisions/0010-leaflet-bundled-from-npm.md)).

## Summary on every PR

`.github/workflows/dependabot-summary.yml` posts one comment on each Dependabot PR: **What changed** (the most important changes between the two versions, breaking changes and security fixes first) and **Check after merging** (what in this project to test: which block, which wp-admin screen, or that the build is enough). Claude reads the release notes and how the project uses the package, with `Read`, `Grep` and `Glob` only: release notes are written outside the project, so Claude cannot run commands, post or reach the network, and the workflow posts the comment itself after refusing anything that looks like a token. It reruns when Dependabot moves the PR to a newer version (the title changes), not on a rebase.

It needs `CLAUDE_CODE_OAUTH_TOKEN` twice: as a repository secret (like the review) and as a **Dependabot secret**, because a workflow started by Dependabot cannot read repository secrets. Set or update the Dependabot one in the browser, Settings → Secrets and variables → **Dependabot**: `claude setup-token` prints the token over two lines, and a terminal prompt cuts it at the line break, while the browser's text box shows the break so you can remove it. A broken Dependabot secret shows as "Claude did not return structured_output" after about 2 seconds, at no cost.

To check it, or to summarise a PR opened before the workflow existed: Actions → **Dependabot summary** → Run workflow, with the PR number.

## Alerts and security updates are switched on in every repo

`dependabot.yml` only drives the weekly version updates. Two more things are repo settings, so a new repo, or one copied from a template, starts with both off:

- **Dependabot alerts**: GitHub flags a dependency with a known vulnerability.
- **Dependabot security updates**: Dependabot opens the fix PR straight away instead of waiting for the weekly run. Needs alerts on.

Without them the weekly PRs still arrive, so a repo looks covered when it is not. This repo had both off until 3 November 2026. Switch them on when the repo is created, from its directory with `gh` logged in as someone with admin rights on the repo (anything less gets a 403):

```bash
gh api -X PUT "repos/{owner}/{repo}/vulnerability-alerts"
gh api -X PUT "repos/{owner}/{repo}/automated-security-fixes"

# check: 204 = alerts on; {"enabled":true,...} = security updates on
gh api -i "repos/{owner}/{repo}/vulnerability-alerts" | head -1
gh api "repos/{owner}/{repo}/automated-security-fixes"
```

Or by hand: Settings → Advanced Security (Code security on older repos) → enable **Dependabot alerts** and **Dependabot security updates**.

## Rules

- **Adding the first `package.json` or `composer.json`** to a project or a new folder: add its `directory` to `dependabot.yml` in the same PR. Create the file if it does not exist yet.
- **Moving or removing one:** update or remove its entry ([clean up after every change](cleanup.md)).
- **Commit the lockfile** (`package-lock.json`, `composer.lock`). Dependabot updates it, and it holds the exact versions and integrity hashes.
- **Pin exact versions** (`"leaflet": "1.9.4"`, not `^1.9.4`) for libraries that ship to visitors, so an upgrade happens only through a reviewed Dependabot PR.
- **A third-party library loaded from a CDN** cannot be watched this way. Install it from npm instead.
- **A new project** copies the `dependabot` check from `validate.sh` along with this page, so the rule is checked there too.
- **A new repo** gets alerts and security updates switched on (commands above) the day it is created. `validate.sh` cannot check repo settings, so this is on the setup checklist ([project-setup.md](../contributing/project-setup.md)).

## Related

- [Clean up after every change](cleanup.md)
- [Fail loudly at the right layer](fail-loudly.md)
