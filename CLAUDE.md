# Vandrekalender

Walking events calendar for Denmark (v1: walking tours in Denmark only). Aggregates events from scrapers, Facebook and user submissions. WordPress: plugin `wp-content/plugins/vandrekalender-events`, theme `wp-content/themes/vandrekalender-theme`, local Docker stack.

`./validate.sh` is the single check that a change is ready. CI runs the same script, and staging deploys only when it passes.

Never merge a PR, never push to `main`, never force push. Petya merges.

This file is a map, not the docs. Open the page the task needs:

- Work starts from a GitHub issue; the board decides what is next: [docs/contributing/tasks-and-issues.md](docs/contributing/tasks-and-issues.md)
- How we work (PRs and review loop, code standards, local dev): [docs/contributing/](docs/contributing/index.md)
- How we build (cleanup, source of truth, failing loudly, one-way doors): [docs/principles/](docs/principles/index.md)
- How the product works (data model, scrapers, frontend, deployment, i18n) and a code map: [docs/](docs/index.md)
