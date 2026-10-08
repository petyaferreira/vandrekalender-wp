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

Branch: `fix/address-autocomplete-2-geocoder`, from PR 1 (stacked, as in `CLAUDE.md`).

File: `includes/class-geocoder.php`. Same public method names and return shape, `{ lat, lng, municipality }`, so scrapers (Sportstiming, Mammut, DVL, Opdag Verden) and the Facebook importer need no change.
- `geocode()`: search `/husnumre/soeg`, take the first `husnummer` hit, look it up by ID, convert coordinates, map the municipality code.
- Port the JS helper `resources/event-meta-fields/utm-to-latlng.js` (from PR 1) to PHP line for line, as a small helper class (same constants, same Krüger series, same 7-decimal rounding). Check that it gives exactly the same latitude/longitude as the JS helper for the addresses tested in PR 1 (Helgolandsgade 3, Rønne, Christiansø, Skagen, Esbjerg). Point the JS helper's comment at the PHP file so the two stay in sync.
- Keep the transient cache. Do NOT cache failures for a month: a `410` or network error must not be cached as "no result".
- `geocode_place()` (`stednavne2`) and anything else that needs Datafordeler stay as is for now but must fail gracefully and be logged to the Scraper Log.
- Run `./scrape.sh` and check Events, Scraper Log. Report how many events got coordinates before and after.
- After merging, existing events scraped since 1 Oct without coordinates need a re-geocode. Write a one off WP-CLI command for that, or note it for Petya.

### PR 3 (later, needs a Datafordeler API key)

Place names (`stednavne2`) and nearest address from coordinates. Petya must first create a user, an IT system and an API key in Datafordelerens Administration (https://portal.datafordeler.dk). Plan this PR only after the key exists.

Adressevælger has no reverse geocoding (confirmed in the KDS FAQ: "Adressevælgeren tilbyder ikke funktionaliteten Reverse Geokodning … Vi henviser til de muligheder datafordeleren stiller til rådighed"), so this PR is the only way to get an address from coordinates.

**Reverse address from coordinates (must be part of this PR).** Since PR 1, pasting coordinates in the editor sets `event_lat` / `event_lng` and clears any earlier `event_address` and `event_municipality` (they would describe the old point). The event then has no address, no municipality and therefore no `event_region`, so it is missing from region filters. On the frontend it falls back to the `Event::coordinates_label()` text ("GPS 55.67286° N, 12.56103° Ø") and a directions link to the coordinates (added in PR 1). This PR must:
- Look up the nearest address and the municipality code for pasted coordinates (Datafordeler DAR GraphQL), and fill `event_address` and `event_municipality` (name via `data/municipalities.json`, which also sets the region). The pasted coordinates stay as they are; they are the source of truth for the pin.
- Call Datafordeler from the server through our own REST endpoint, so the API key never reaches the browser. The key follows the same constant / GitHub Environment secret / generated mu-plugin pattern as `VANDREKALENDER_ADRESSEVAELGER_TOKEN` (see `docs/deployment.md`).
- Restore the editor help text ("…and the nearest address is looked up for you") and the matching Danish translation.
- Re-run the lookup for existing events that have coordinates but no address or municipality (a one-off WP-CLI command, like PR 2's re-geocode). List how many events it fixed.
- Use the same lookup in the server-side geocoder for DVL, which brings its own coordinates and only needs the municipality.
- Keep the `coordinates_label()` / coordinate directions fallback. It is still needed when the lookup fails or finds nothing. Update the comment in `Event::coordinates_label()`, which points at this PR.

### PR 4 (last step): Danish translation catch-up

Branch: `fix/address-autocomplete-4-translations`, from PR 3 (stacked).

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

## Docs to update in each PR

- `docs/data-model.md` and `docs/scrapers.md`: replace DAWA mentions.
- `docs/deployment.md`: the new token (environment table, secrets list, deploy steps).
- Code comments that say "DAWA" (for example in `class-geocoder.php`, `class-event-schema.php`).
