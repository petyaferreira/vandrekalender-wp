# Every npm package.json is watched by Dependabot

When a project gets npm dependencies, it gets `.github/dependabot.yml` in the same PR. Every folder with a committed `package.json` has an entry there. A dependency nobody watches stays on the version it was added with, and its security fixes go unnoticed.

## Example

`.github/dependabot.yml` in this repo has one entry per `package.json`:

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
```

Once a week, Dependabot opens a PR for each dependency with a newer version. It opens a PR straight away when a security advisory affects one. CI runs on those PRs like on any other.

Leaflet was loaded from cdnjs, where nothing told us about new versions. Moving it to npm with Dependabot fixed that ([decision 0010](../decisions/0010-leaflet-bundled-from-npm.md)).

## Rules

- **Adding the first `package.json`** to a project or a new folder: add its `directory` to `dependabot.yml` in the same PR. Create the file if it does not exist yet.
- **Moving or removing a `package.json`:** update or remove its entry ([clean up after every change](cleanup.md)).
- **Commit the lockfile** (`package-lock.json`). Dependabot updates it, and it holds the integrity hashes.
- **Pin exact versions** (`"leaflet": "1.9.4"`, not `^1.9.4`) for libraries that ship to visitors, so an upgrade happens only through a reviewed Dependabot PR.
- **A third-party library loaded from a CDN** cannot be watched this way. Install it from npm instead.

## Related

- [Clean up after every change](cleanup.md)
- [Normalized source of truth](normalized-source-of-truth.md)
