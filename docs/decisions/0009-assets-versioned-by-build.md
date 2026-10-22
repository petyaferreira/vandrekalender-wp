# 0009: The plugin's own scripts and styles are versioned by the build, not by file time

Decided 22 October 2026 with Petya ([#118](https://github.com/petyaferreira/vandrekalender-wp/issues/118), PR [#131](https://github.com/petyaferreira/vandrekalender-wp/pull/131)).

## Decision

Every script and style of the plugin's own goes through the build (`blocks/` → `build/blocks/`, `resources/` → `build/resources/`) and is registered with the content hash in its `index.asset.php`. A third-party library in `assets/vendor/` is registered with its own version number (Swiper `'12.0.3'`), changed when the library is upgraded. No plugin script or style is versioned by `filemtime()`, and there is no hook that rewrites `ver`. The theme is not covered: it versions its built `screen.css` and `screen.js` by `filemtime()` in `functions.php`, which works because they are rebuilt on every deploy.

## Why

- **A hand-registered file kept its old URL.** `assets/js/filtered-count-view.js` was served as-is with the plugin's fixed `1.0.0`, so after #108 browsers on staging ran the cached old copy. Every built file already had a content hash; this one script was the exception.
- **One path, no special rule.** With all our code in the build, a new script needs nothing extra for its version. A content hash also changes only when the file does, while a file time can change on every deploy.
- **Less code.** PR [#129](https://github.com/petyaferreira/vandrekalender-wp/pull/129) solved it with a `script_loader_src`/`style_loader_src` filter and a class (`Vandrekalender_Asset_Versions`) that set `ver` to the file time of every plugin URL, plus a test. That patched around a folder that did not need to exist; #131 reverted it.

## Do not propose

- A hook or helper that sets `ver` from `filemtime()` for plugin files.
- New scripts or styles of our own in `assets/`, served without the build.

## Revisit when

A file of our own cannot go through `wp-scripts` (for example, it must be served byte for byte as written).
