# 0010: Leaflet and its plugins are bundled from npm, not loaded from a CDN

Decided 8 October 2026 with Petya, done in [#31](https://github.com/petyaferreira/vandrekalender-wp/issues/31).

## Decision

Leaflet, leaflet.markercluster and leaflet-gpx are exact npm dependencies of the events plugin. The map blocks load them with a dynamic `import()` and ship Leaflet's CSS in the block stylesheet. Dependabot (weekly, plugin and theme) opens PRs for new versions and security advisories.

## Why

- A CDN file has no integrity check; the versions were written in four places; nothing signalled security fixes; and every visitor's IP went to Cloudflare (GDPR).
- npm gives one place for the version, a lockfile with integrity hashes, and Dependabot.

## Do not propose

Going back to cdnjs or another CDN for the map libraries.

## Revisit when

The bundle chunks become a measurable performance problem on pages with a map.
