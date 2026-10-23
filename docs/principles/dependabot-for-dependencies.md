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

Once a week, Dependabot opens a PR for each dependency with a newer version. It opens a PR straight away when a security advisory affects one. CI runs on those PRs like on any other. Composer covers the WordPress plugins installed from `composer.json` (Rank Math, Polylang, Fluent Forms and others) as well as the dev tools.

Leaflet was loaded from cdnjs, where nothing told us about new versions. Moving it to npm with Dependabot fixed that ([decision 0010](../decisions/0010-leaflet-bundled-from-npm.md)).

## Rules

- **Adding the first `package.json` or `composer.json`** to a project or a new folder: add its `directory` to `dependabot.yml` in the same PR. Create the file if it does not exist yet.
- **Moving or removing one:** update or remove its entry ([clean up after every change](cleanup.md)).
- **Commit the lockfile** (`package-lock.json`, `composer.lock`). Dependabot updates it, and it holds the exact versions and integrity hashes.
- **Pin exact versions** (`"leaflet": "1.9.4"`, not `^1.9.4`) for libraries that ship to visitors, so an upgrade happens only through a reviewed Dependabot PR.
- **A third-party library loaded from a CDN** cannot be watched this way. Install it from npm instead.
- **A new project** copies the `dependabot` check from `validate.sh` along with this page, so the rule is checked there too.

## Related

- [Clean up after every change](cleanup.md)
- [Fail loudly at the right layer](fail-loudly.md)
