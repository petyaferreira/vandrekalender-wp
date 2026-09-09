# Fonts

Font files are not committed to this template. `theme.json` expects six woff2
files in this directory:

```
Inter-Regular.woff2
Inter-Italic.woff2
Inter-SemiBold.woff2
Inter-SemiBoldItalic.woff2
Newsreader-Light.woff2
Newsreader-LightItalic.woff2
```

Until they exist the site falls back to `system-ui` and `Georgia`, which looks
fine but is not the intended design.

Both fonts are on Google Fonts under the SIL Open Font License, so they can be
self hosted on client sites without a licence fee.

- Inter: https://fonts.google.com/specimen/Inter
- Newsreader: https://fonts.google.com/specimen/Newsreader

## Adding them

Nothing to configure. `theme.json` already declares the font faces with
`file:./assets/fonts/...` paths, and WordPress generates the `@font-face` CSS
from that. You only have to put the files here under the right names.

**1. Download woff2.** Google Fonts hands you ttf, which is larger and needs
converting. Use google-webfonts-helper (https://gwfh.mranftl.com) instead, which
gives woff2 directly.

Charsets: `latin` covers Danish, including æ ø å. Add `latin-ext` for broader
European coverage, and `cyrillic` only if a project needs it. Every subset adds
weight.

Styles: Inter 400, 400 italic, 600, 600 italic. Newsreader 300, 300 italic.

**2. Rename.** Downloads arrive named like `inter-v13-latin-regular.woff2`.
Rename them to the six names above. Renaming the files is easier than editing
`theme.json`, but either works.

**3. Drop them in this directory and reload.** No build step. `theme.json` is
read directly.

**4. Verify.** DevTools, Network tab, filter by Font, reload. Every request
should go to your own domain. If you see `fonts.gstatic.com`, something is
still pulling from the CDN.

## Self host, do not use the Google CDN

Self hosting means the browser fetches the fonts from your domain. The
alternative is a `<link>` to `fonts.googleapis.com` in the page head, or an
`@import` in CSS, which makes every visitor's browser contact Google directly
and hands Google their IP address.

A German court found that breaches GDPR without consent (LG München I,
3 O 17493/20, January 2022), and Danish clients fall under the same regulation.
Self hosting avoids the question entirely.

This template has no CDN link anywhere, so there is nothing to remove. Just do
not add one.

## Changing the fonts for a project

Replace the files here and update `settings.typography.fontFamilies` in
`theme.json`. Keep the `slug` values (`inter`, `newsreader`) or update every
`var:preset|font-family|...` reference in `theme.json` to match.
