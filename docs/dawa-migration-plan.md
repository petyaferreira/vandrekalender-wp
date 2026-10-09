# DAWA migration plan (address search)

Status: DAWA was shut down on 1 October 2026. Every call to `api.dataforsyningen.dk` now returns `410 Gone`. The event editor address field shows no suggestions and the server side geocoder (scrapers, Facebook importer) cannot find coordinates. Production is affected.

Work one PR (one step) at a time, as described in `CLAUDE.md`. This is a hotfix, so branch from `main`, not from a GPX branch. Do not touch the `feature/gpx-*` branches.

## What replaces what

| DAWA (gone) | Replacement | Needs |
|---|---|---|
| `autocomplete?type=adresse` | Adressevælger search | a token (see below) |
| `adgangsadresser/reverse` (coordinates to nearest address) | Datafordeler (GraphQL) | Datafordeler API key |
| `stednavne2` (place names) | Datafordeler | Datafordeler API key |
| `kommuner/{code}` (municipality name) | a static list of the 98 municipalities in our own code | nothing |

Adressevælger only searches addresses and road names. It does not return coordinates in the search result.

Official sources (Klimadatastyrelsen):
- Overview: https://confluence.kds.dk/pages/viewpage.action?pageId=234782998
- Search (phonetic): https://confluence.kds.dk/pages/viewpage.action?pageId=244318431
- Lookup by ID: https://confluence.kds.dk/pages/viewpage.action?pageId=246743156
- Token / user management: https://confluence.kds.dk/display/ADV/Brugerstyring
- FAQ: https://confluence.kds.dk/display/ADV/FAQ
- DAWA closing notice: https://www.lovguiden.dk/det-offentlige/klimadatastyrelsen/2026-07-02-dawa-applikationen-lukker-1-oktober-2026-datafordeler-og-adressevaelger-overtager

## How the new API works (tested live on 8 Oct 2026)

Base URL: `https://adressevaelger.dk`. Every call needs `token=<token>`.

1. Search while typing:
   `GET /husnumre/soeg?tekst=helgolandsgade 3&maksimum=8&token=...`
   Response: `{ status, beskrivelse, fund: [ { type, id, titel, ... } ] }`
   - `type: "husnummer"`: a full house number. `titel` is e.g. `Helgolandsgade 3, 1653 København V`. This is selectable.
   - `type: "vejnavn"` or `"navngivenvejpostnummer"` (seen on `/adresser/soeg`): a street only, with no `husnummer`. Do NOT treat it as a finished address. When picked, put the street text in the input and let the user keep typing.
   - `/adresser/soeg` also returns floor and door (`type: "adresse"`). Events only need the house number, so use `/husnumre/soeg`. Verify in the browser which types actually come back for short input like `helgo`, and handle each.
2. After the user picks a `husnummer`, fetch the details:
   `GET /husnumre/{id}?token=...`
   - Coordinates: `husnummer.adgangspunkt.koordinater.x` and `.y`.
   - Municipality code: `husnummer.navngivenvejkommunedel.kommune`, e.g. `"0101"`. It is a code, not a name.
   - Postcode: `husnummer.postnummer.postnr`.
3. The coordinates are NOT latitude and longitude. They are EPSG:25832 (ETRS89 / UTM zone 32N), in metres. Example: Helgolandsgade 3 returns `x=723913.84, y=6175420.05`. Adding `srid=4326` has no effect (tested). They must be converted to WGS84 latitude and longitude before saving to `event_lat` and `event_lng`. The difference between ETRS89 and WGS84 is below one metre, so ignore it.

Tested from a different origin (a page on example.com) with fetch: the response was readable, so calling it from the browser works.

## Token

No account is needed yet. The Brugerstyring page says to invent a token of at least 10 characters, and recommends `adressevaelger123` so the later switch to real user management is easier. Real user management is expected late 2026 or early 2027. Sign up for their Notifikationsservice (linked from the Brugerstyring page) to hear when it arrives.

Implementation rule: keep the token out of the repo and out of the code, and follow the SAME pattern as the Google login secrets (see `docs/deployment.md`, section about Google login). This way the value can be replaced with the real token later without a code change, and it works in all three environments.

| Environment | Where the value comes from |
|---|---|
| Local | `.env` (`ADRESSEVAELGER_TOKEN=...`), passed into `WORDPRESS_CONFIG_EXTRA` in `docker-compose.yml` as `define( 'VANDREKALENDER_ADRESSEVAELGER_TOKEN', '...' )` |
| Staging | GitHub Environment secret `ADRESSEVAELGER_TOKEN`, written into the generated mu-plugin by the deploy workflow |
| Production | GitHub Environment secret `ADRESSEVAELGER_TOKEN`, same as staging |

Implementation steps (part of PR 1, because PR 1 cannot work without it):
- `.env.example`: add `ADRESSEVAELGER_TOKEN=` with a comment. Never commit the real `.env`.
- `docker-compose.yml`: add the constant to `WORDPRESS_CONFIG_EXTRA`, copying how the Google login constants are done.
- Deploy workflow (`.github/workflows/deploy-to-nordicway.yml`): extend the step that writes `mu-plugins/00-vk-google-login.php`, or write a second small mu-plugin, so it defines `VANDREKALENDER_ADRESSEVAELGER_TOKEN` from the environment secret. Fail the deploy loudly if the secret is missing, as the Google login step does.
- Plugin code must read ONLY the constant. If the constant is not defined, the editor shows the visible "Address search is not configured" message and the geocoder logs it and returns null. No hard coded fallback in the repo.
- Pass the constant to the editor script from PHP (`wp_add_inline_script` or `wp_localize_script`). Do not write it in `index.js`.
- Update `docs/deployment.md`: the environment table, the Required GitHub Secrets list, and the deploy steps list.

Note: the token still reaches the browser of logged in editors (the editor script needs it to search), so it is hidden from the repo and from visitors, but not from someone who opens the dev tools in wp-admin. That is acceptable for this kind of token, but do not reuse it for anything else.

**Decide when real tokens arrive.** Today the token is the public `adressevaelger123`, so showing it in the editor exposes nothing. When KDS launches real user management (expected late 2026 or early 2027) and issues a personal token, decide before switching the secret whether it may stay visible in wp-admin:
- If KDS treats it as a browser-side key (like the public one, or with a domain restriction), keep the current setup and only change the secret and `.env`.
- If it must stay secret, add a small REST endpoint of our own (logged-in editors only) that calls Adressevælger with the token from the server, and point the editor's search and lookup at it. The cost: every search keystroke goes through our server, which is slightly slower and adds load on the Nordicway hosting. PR 3's Datafordeler endpoint will already use this pattern, so reuse it.
Check the KDS Brugerstyring page (and the Notifikationsservice mail) for how they expect the token to be used.

Before merging PR 1 Petya must create the GitHub Environment secret `ADRESSEVAELGER_TOKEN` in BOTH `staging` and `production`, otherwise the deploy fails. For now the value is any string of 10 or more characters, and KDS recommends `adressevaelger123` (tested and working). She also adds the same line to her local `.env`.

## PR plan

### PR 1 (hotfix): editor address field

Branch: `fix/address-autocomplete-1-editor`, from `main`.

File: `wp-content/plugins/vandrekalender-events/resources/event-meta-fields/index.js` (the `LocationPanel`).

1. Replace the three DAWA constants (`DAWA_AUTOCOMPLETE`, `DAWA_KOMMUNE`, `DAWA_REVERSE`) with the Adressevælger base URL and the token passed in from PHP.
2. `onQueryChange`: search `/husnumre/soeg`. Keep the 300 ms debounce and the "clear derived fields when typing" behaviour.
3. Check that the response is OK and `fund` is an array before using it. Today a failed request is silently swallowed, which is why nobody saw the DAWA problem. Show a small visible message in the panel when the search fails ("Address search is unavailable").
4. `onSelect`: for a `husnummer` result, call `/husnumre/{id}`, convert x and y to latitude and longitude, set `event_address` (the `titel`), `event_lat`, `event_lng`. For a street only result, fill the input and keep the dropdown open for more typing.
5. Municipality: map the `kommune` code to the name with a static list (all 98 municipalities, codes like `0101` København). Put the list in one small shared file so the PHP side can reuse the same data in PR 2. Check how `event_municipality` is used (region taxonomy) so the names match exactly what DAWA used to return, for example "København" and "Aarhus".
6. Coordinate conversion in JS: our own helper, `resources/event-meta-fields/utm-to-latlng.js` (inverse transverse Mercator, Krüger series, GRS80). **Decision (8 Oct 2026):** the plan first said to use the `proj4` npm package, but it grew the editor script from 13 KiB to 147 KiB for one conversion. The helper is about 25 lines, matches `proj4` within 1 cm across Denmark (Christiansø included), and is the same formula PR 2 ports to PHP. Details in `docs/data-model.md` → Coordinate conversion.
7. The "paste coordinates" box (`onCoordsChange`, reverse lookup) cannot be fixed in this PR. Keep pasted coordinates working as they do (they set the pin), but skip the nearest address lookup and fail quietly. Say clearly in the PR description that this is deferred to PR 3.

Tests (local Docker stack, `http://localhost:8080`):
- `npm run build` in the plugin, `composer run phpcs`.
- In the editor type `helgo`, `helgolandsgade 3`, `hel`, and an address in Jutland. Helgolandsgade 3 should end near 55.67 N, 12.55 E (Copenhagen Central Station area). Check the pin on the map.
- Break the token on purpose and confirm the visible error message shows.
- Browser clicking cannot be done by Claude Code from the terminal, so list those checks as "not run" in the PR description if so.

### PR 2: server side geocoder

Branch: `fix/address-autocomplete-2-geocoder`. PR 1 was merged before this started, so it branches from `main` and targets `main`.

File: `includes/class-geocoder.php`. Same public method names and return shape, `{ lat, lng, municipality }`, so scrapers (Sportstiming, Mammut, DVL, Opdag Verden) and the Facebook importer need no change.
- `geocode()`: search `/husnumre/soeg`, take the first `husnummer` hit, look it up by ID, convert coordinates, map the municipality code.
- Port the JS helper `resources/event-meta-fields/utm-to-latlng.js` (from PR 1) to PHP line for line, as a small helper class (same constants, same Krüger series, same 7-decimal rounding). Check that it gives exactly the same latitude/longitude as the JS helper for the addresses tested in PR 1 (Helgolandsgade 3, Rønne, Christiansø, Skagen, Esbjerg). Point the JS helper's comment at the PHP file so the two stay in sync.
- Keep the transient cache. Do NOT cache failures for a month: a `410` or network error must not be cached as "no result".
- `geocode_place()` (`stednavne2`) and anything else that needs Datafordeler stay as is for now but must fail gracefully and be logged to the Scraper Log.
- Run `./scrape.sh` and check Events, Scraper Log. Report how many events got coordinates before and after.
- After merging, existing events scraped since 1 Oct without coordinates need a re-geocode. Write a one off WP-CLI command for that, or note it for Petya.

**Done in PR 2 (decisions made while building it, 8 Oct 2026):**
- **Choosing the hit by locality.** Tested on every scraped address: taking the first house number pinned "Skovvejen 26, Brædstrup" in Slagelse (126 km off) and five more Sportstiming addresses 96–253 km off, because the search ignores a town without a postcode. The geocoder now picks the hit matching the input's postcode or town (200 results for town-only input) and rejects ambiguous input, plus a same-street fallback for house numbers that do not exist. Result: 21 of 35 addresses found, all within 3 km of DAWA's stored point, 20 of 21 with the same municipality. Details in `docs/scrapers.md` → Geocoding.
- **Place names and reverse lookup** no longer call the dead DAWA endpoints; they return nothing and say so in the Scraper Log until PR 3.
- **Scraper Log warnings**: geocoder problems are attached to each scraper's row and printed by `./scrape.sh`.
- **`Vandrekalender_Municipalities`** (`includes/class-municipalities.php`) now reads `data/municipalities.json` for both the geocoder and the region map.
- **Re-geocode command**: `wp vandrekalender regeocode [--since=2026-10-01] [--dry-run]`. Ran on production on 9 Oct 2026 (see PR 3 below).

### PR 3: regions for DVL events (higher priority than "later")

Found on production on 8 Oct 2026: scraped events from DVL (organiser "DVL Trekantomraadet" and the other DVL areas) are published with NO region. They do have the address and exact coordinates, because the DVL feed supplies the coordinates. The region taxonomy comes from the municipality, and `Vandrekalender_Geocoder::municipality_from_coords()` used DAWA for that (reverse lookup). Adressevælger cannot do a reverse lookup, so after PR 1 and PR 2 these events will STILL have no region. Events that already had a municipality keep it (the scraper does not overwrite it with an empty value), for example "Isenbjerg og Gludsted Plantage" still shows Midtjylland.

Also affected: `geocode_place()` (`stednavne2`, used by Opdag Verden) and the editor's "paste coordinates" box.

Options for the municipality from coordinates (decide with Petya before building):
1. Datafordeler GraphQL (DAGI, the municipality register). This is the official route. Needs a Datafordeler API key that Petya creates in Datafordelerens Administration (https://portal.datafordeler.dk): a user, an IT system and an API key. The key is a real secret: store it the same way as the Adressevælger token (`.env`, GitHub Environment secret, generated mu-plugin).
2. No API at all: ship simplified municipality boundaries (DAGI, 98 municipalities) as a static file in the plugin and do a point in polygon test in PHP. Works offline and cannot be shut down, but the file adds size and a point very close to a border can land in the wrong municipality (acceptable if only the 5 regions matter, check this).
3. A fallback for the editor only: geocode the typed address text with Adressevælger and take the municipality code from the lookup. Not usable for DVL, because DVL addresses often have no house number or postcode.

Recommended: start creating the Datafordeler key now, because option 1 also covers place names and the nearest address lookup.

After the fix is live, re-geocode events scraped since 1 Oct (both the missing coordinates from PR 2 and the missing municipalities here) with a one off WP-CLI command. This ran on production on 9 Oct 2026: 25 events fixed, 1 without a match. Petya decided to keep `wp vandrekalender regeocode` rather than remove it; it only touches events missing coordinates or a municipality, so running it again is safe.

**Done in PR 3 (option 1, Datafordeler; 8 Oct 2026):**
- Petya created a private (MitID) user, the IT system "allevandreture" and an API key in Datafordelerens Administration (production). The key needed about 15 minutes before it was accepted. It is stored as `DATAFORDELER_API_KEY` in `.env` and in both GitHub environments, and the deploy writes `mu-plugins/00-vk-datafordeler.php` (see `docs/deployment.md` → Datafordeler API key).
- `Geocoder::reverse()`: nearest current address to a point, via `DAR/v3` (address points in a widening square → current house numbers → Adressevælger lookup for the text and municipality code). `municipality_from_coords()` uses it, so new DVL events get their municipality and region again. Checked against DAWA's stored municipalities: 60 of 60 found, 59 the same; the one difference was a border case in the same region.
- Editor: pasting coordinates fills the nearest address and municipality through `GET /vandrekalender/v1/geocode/reverse` (`edit_events`: administrators and organisers; 60 lookups per user per 10 minutes; key stays on the server). The help text "…and the nearest address is looked up for you" is back.
- `wp vandrekalender regeocode` also backfills a missing municipality for events with coordinates. Locally it fixed 164 of 165 DVL events (the miss was in Germany). Ran on production on 9 Oct 2026 and kept, as noted above.
- Both services retry once on a dropped connection before treating the service as down for the run.
- Not done: DAGI/v2 (`DAGI_Kommuneinddeling`) could give the municipality by point-in-polygon instead of "the nearest address's municipality". Only worth it if border cases ever matter.

### Place names (follow-up, not planned yet)

`Geocoder::geocode_place()` (Opdag Verden landmarks such as "Stevns Klint", "Mols Bjerge") still returns nothing; Opdag Verden falls back to the address search. Datafordeler has a place-name register, **`DS/v2`** (Danske Stednavne), reachable with the same key, but:
- `DS_Stednavn` can only be filtered with `eq` / `in` on the exact spelling (`skrivemaade`). There is no case-insensitive or partial match, and Opdag Verden writes "Stevns klint".
- The geometry is not on the name: it is on the named place (`navngivetSted_objectid`), which lives in one of ~30 category types (`DS_Naturareal`, `DS_Landskabsform`, `DS_Bebyggelse`, `DS_Soe`, `DS_Sevaerdighed` and so on), each with full polygon geometry in EPSG:25832. We would need to query the right type and compute a representative point ourselves.
- Opdag Verden had **0 events** at the source on 17 Sep and 8 Oct 2026.

Revisit when Opdag Verden lists events again. A cheaper alternative is a small hand-kept map of their recurring landmarks to coordinates.

### PR 4 (last step): Danish translation catch-up

Branch: `fix/address-autocomplete-4-translations`, from `main` after PR 3 is merged (or from PR 3 if it is still open).

PRs 1 to 3 add only their own strings to the translation files, by hand, so their diffs stay small. Regenerating the template from the code shows a backlog from earlier features that this PR clears in one go. Measured on 8 Oct 2026, plugin `vandrekalender-events` only:

- 69 untranslated strings out of 227. Most are in `includes/` (Facebook importer, Scraper Log admin screen, GPX upload errors, onboarding video notices), the rest in the `event-route-map`, `event-info-card`, `slider`, `tabs` blocks and the plugin header.
- 2 fuzzy entries with wrong Danish, for example `Event Route Map` (block title) is translated as "Kort over begivenheder", which is the Event Map block's name.
- The `.po` header has no `Plural-Forms`, so `msgfmt --check` fails with 2 fatal errors. Add `Plural-Forms: nplurals=2; plural=(n != 1);` and fill in `Language: da_DK`.
- `docs/i18n.md` says compiled `.mo` files are gitignored, but `languages/vandrekalender-events-da_DK.mo` is committed and is not ignored. Decide which is right (the server has no build step for `.mo`, so committing it is probably correct) and make the doc match.
- Only the editor script (`resources/event-meta-fields/index.js`) has a JS translation JSON. Check whether the front-end view scripts with translated strings need their own JSON from `wp i18n make-json`.

Steps:
1. `./wp.sh i18n make-pot wp-content/plugins/vandrekalender-events wp-content/plugins/vandrekalender-events/languages/vandrekalender-events.pot --exclude=node_modules,vendor,resources` (build output is scanned for JS strings; `resources` is the unbuilt source). Add the exclude flag to the command in `docs/i18n.md`.
2. `msgmerge --update` the Danish `.po` against it. Translate every untranslated string and fix the fuzzy ones. Strings that are not for users (plugin URI, author name) can stay untranslated; list them in the PR description.
3. `msgfmt --check` must pass. Compile the `.mo` and regenerate the JSON files with `wp i18n make-json --no-purge`.
4. Check in the browser with the site in Danish: the Facebook importer screen, the Scraper Log screen, a GPX upload error, the route map block and the event editor.
5. Include the theme (`vandrekalender-theme` text domain) in the same check if it has the same gaps, or note it for Petya as a separate task.

**Done in PR 4 (8 Oct 2026):**
- All 59 untranslated strings translated, plus 5 found later (GPX strings in the event editor and two short ones). Wording follows the existing Danish (vandretur/tur, begivenhed, arrangør, rute, Faneblad). Left untranslated on purpose: the plugin name, its URI, the author and the brand "Vandrekalender".
- Fuzzy fixed: the `Event Route Map` block title is now "Rutekort" (it said "Kort over begivenheder", the Event Map's name).
- 38 obsolete entries removed. The header now has `Language: da_DK`, `Plural-Forms` and translator fields, so `msgfmt --check` passes.
- **Correction to step 1:** `--exclude=…,resources` also excludes `build/resources/`, which dropped every event-editor string. The working command excludes only `node_modules,vendor`; see `docs/i18n.md`.
- JS translations: 10 JSON files, one per built script. The block editors (slider, tabs, link box, info card, route map and the rest) get Danish for the first time. JSON files for source paths are deleted, because WordPress never loads them.
- `.mo` stays committed (the servers do not compile it); `docs/i18n.md` now says so and documents the full procedure.
- Front-end `view.js` scripts have no translatable strings, so they need no JSON. The theme has no translatable strings at all (its patterns are written directly in the templates), so there is nothing to translate there.

## Docs to update in each PR

- `docs/data-model.md` and `docs/scrapers.md`: replace DAWA mentions.
- `docs/deployment.md`: the new token (environment table, secrets list, deploy steps).
- Code comments that say "DAWA" (for example in `class-geocoder.php`, `class-event-schema.php`).
