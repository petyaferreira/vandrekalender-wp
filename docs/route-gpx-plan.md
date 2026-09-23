# Route GPX on events: implementation plan

Status: planned, September 2026. Owner: Petya. Split into small PRs, one per section below, in order. Each PR must pass `composer run phpcs`, build with `npm run build` in the plugin, and be tested on the local Docker stack before opening.

## Goal

An organiser plans a route in an external tool (Komoot, Plotaroute, gpx.studio), exports it as a GPX file, and attaches that file to a route on the event in the WordPress editor. The event page then shows the track on a map and offers the GPX for download. Scrapers pick up GPX files that the source website links to, so scraped events get the same map.

Out of scope (for now): drawing the route inside WordPress, elevation profiles, editing tracks, Komoot embeds.

## Background for whoever picks this up

An event is the `event` custom post type (plugin `vandrekalender-events`). One event can have several distances, called routes, stored as a JSON array in the single post meta `event_routes` (see `docs/data-model.md`). Each route object today has `id`, `distance_km`, `start_time`, `cutoff_time`, `price`, all strings. The block editor sidebar that edits them is `resources/event-meta-fields/index.js` (component `EventDetailsPanel`), which talks to the meta through the REST API, so the schema in `includes/event/class-event.php` `register_meta()` decides what the editor is allowed to save. Anything not in that schema is silently dropped.

A GPX file is an XML file with a list of GPS points (`<trkpt lat=".." lon="..">`). We store it as a normal Media Library attachment and only keep the attachment ID on the route. The browser downloads the file and draws it with Leaflet, which the site already uses in the Event Map block.

## Data model change (decided)

Extend each object in `event_routes` with three optional string fields. Strings on purpose: every existing route field is a string and the editor code (`normalizeRoutes`) assumes strings.

| Key | Type | Set by | Meaning |
|---|---|---|---|
| `gpx_id` | string | editor, scraper | Attachment ID of the GPX file in the Media Library. Empty string when none. |
| `gpx_source_url` | string | scraper only | The URL on the source site the GPX was downloaded from. Used to avoid re downloading on every scraper run, same idea as `_event_source_image` for featured images. Empty for organiser uploads. |
| `gpx_name` | string | editor, scraper | Original filename, for display and for the download link. Optional nicety; can be derived from the attachment instead if simpler. |

No new top level meta keys, no new tables. The REST API adds a derived `gpx_url` per route (resolved from `gpx_id` with `wp_get_attachment_url`) so the frontend never has to resolve IDs itself.

## PR 1: allow GPX files in WordPress and extend the schema

Smallest possible PR. Nothing visible changes yet.

1. In `includes/event/class-event.php` `register_meta()`, add `gpx_id`, `gpx_source_url`, `gpx_name` (all `[ 'type' => 'string' ]`) to the `event_routes` items schema. `additionalProperties` is `false` there, so without this step the editor cannot save the new fields.
2. Add a new class `includes/class-gpx-uploads.php` (hook it up in `vandrekalender-events.php` like the other classes) that:
   - Filters `upload_mimes` to add `'gpx' => 'application/gpx+xml'`.
   - Filters `wp_check_filetype_and_ext`. This is the trap: WordPress sniffs the real file content with PHP's finfo, which reports GPX as `text/xml` or `application/xml`, not `application/gpx+xml`, and then rejects the upload as "not permitted for security reasons". The filter must, when the filename ends in `.gpx` and the sniffed type is one of `text/xml`, `application/xml`, `text/plain`, return `ext => gpx`, `type => application/gpx+xml`.
   - Validates on `wp_handle_upload_prefilter`: for `.gpx` files, read the file with `simplexml_load_file` (with `LIBXML_NONET`, and call `libxml_disable_entity_loader` on PHP < 8 only; on PHP 8 external entities are off by default). Reject with an error message if the root element is not `gpx` or the file has no `trkpt`/`rtept`/`wpt`. Cap size at 5 MB. This keeps random XML out of the media library.
3. Check that the `event_organizer` role has `upload_files` (grep `upload_files` in `includes/class-roles.php`). If not, add it, otherwise organisers cannot upload anything from the editor.
4. Update `docs/data-model.md` `event_routes` table with the three new keys.

Test: upload a GPX through Media > Add New as admin and as an organiser. Upload a renamed `.txt` as `.gpx` and confirm it is rejected.

## PR 2: editor UI, attach a GPX to a route

In `resources/event-meta-fields/index.js`, inside the per route form in `EventDetailsPanel` (where the `distance_km`, `start_time`, `cutoff_time`, `price` `TextControl`s are):

1. Import `MediaUpload` and `MediaUploadCheck` from `@wordpress/block-editor`. Wrap in `MediaUploadCheck` so the button only shows when the user may upload.
2. Render a small "GPX rute" row per route:
   - No file: a `Button` "Upload GPX" opening `MediaUpload` with `allowedTypes={['application/gpx+xml']}`. On select call `updateRoute(index, { gpx_id: String(media.id), gpx_name: media.filename || media.title })`.
   - File set: show the filename, a "Replace" button (same `MediaUpload`) and a "Remove" button that sets `gpx_id: ''`, `gpx_name: ''`.
3. Extend `emptyRoute()` and `normalizeRoutes()` with the three new keys so old routes without them normalise to empty strings and never send `undefined`.
4. Show the filename in the collapsed route summary next to the distance, so the organiser can see which routes have a track.
5. All labels in English wrapped in `__( '...', 'vandrekalender-events' )`, Danish comes from the translation files as elsewhere.

Test: create an event with two routes, attach a GPX to one, save, reload, confirm it persists and the REST response for the post shows `gpx_id` in `meta.event_routes`. Remove it, save, confirm it is gone.

Note: the media modal filters by MIME type. If the GPX does not show in the modal after uploading, the attachment was stored with a different `post_mime_type`, which means PR 1 step 2 is not fully working.

## PR 3: show the route on the event page

Three parts, all frontend.

### 3a REST: `gpx_url`

In `includes/class-event-rest-api.php`, wherever routes are put into a response (`'routes' => $routes`, around line 522, and the `rest_prepare` path around line 256 if routes are exposed there), map each route and add `gpx_url` (from `wp_get_attachment_url( (int) $route['gpx_id'] )`, or empty string). Never expose `gpx_source_url` publicly.

### 3b New block `event-route-map`

New block in `blocks/event-route-map/`, modelled on `blocks/event-map/` but much simpler (no filters, no clustering, no router interaction because the single event page is not a router region).

- `render.php`: returns nothing if no route on the current event has a `gpx_id`. Otherwise enqueues Leaflet CSS from cdnjs server side (same rule as the Event Map block, see `docs/frontend.md`: CSS is never injected from JS) and outputs a wrapper with a `data-vk-routes` attribute holding JSON `[{ id, distance_km, gpx_url, gpx_name }]` for routes that have a file, plus a map div with a fixed height.
- `view.js` (plain `viewScript` is fine here): lazy loads Leaflet 1.9.4 and the `leaflet-gpx` plugin from cdnjs, creates the map, adds one `L.GPX` layer per route with `async: true`, fits bounds to all layers, and adds start and finish markers (leaflet-gpx does this by default, set `marker_options` to plain icons or disable the default images which point to a GitHub URL). Distinct colour per route.
- Listens for a `vk:route-change` custom event (see 3c) with `detail.id` and highlights that route (others dimmed), refits bounds to it.
- Below the map: a download link per route, `<a href="{gpx_url}" download>Download GPX ({distance} km)</a>`.
- `block.json`: `supports.html false`, no attributes needed, `usesContext` not needed (uses `get_the_ID()` in render).

Register the block in `vandrekalender-events.php` next to the others, add it to the `@wordpress/scripts` build.

### 3c Info card hooks

In `blocks/event-info-card/render.php` and its `view.js`: when a route tab is clicked, dispatch `document.dispatchEvent(new CustomEvent('vk:route-change', { detail: { id } }))`. Add `data-vk-route-id` on each tab. Also add a "Download GPX" link in the tab content when the route has a file.

### 3d Template

Add `<!-- wp:vandrekalender/event-route-map /-->` to `themes/vandrekalender-theme/templates/single-event.html` in the main column, under `wp:post-content`. Then, per `docs/frontend.md`, also add the block in the Site Editor on any environment that has a database copy of `single-event` (local, staging, production), otherwise the file change does nothing there.

Test: event with two routes, both with GPX, switch tabs and confirm the map highlights the selected one. Event with no GPX renders no map and no empty space. Test on a phone width.

## PR 4: scrapers pick up GPX files

### 4a Base class support

In `includes/class-scraper-base.php`:

1. Add `protected function sideload_gpx( int $post_id, string $url ): int`, modelled on `set_featured_image_from_url()`: downloads with `remote_get()`, writes to a temp file, runs the same validation as PR 1 (root element `gpx`), then `media_handle_sideload()` with the post as parent. Returns attachment ID or 0. Because the sideload goes through the normal upload path, PR 1's MIME filters apply automatically.
2. In `upsert_event()`, after the meta loop, walk `event_routes`: for each route with a non empty `gpx_source_url`, compare with the route already stored on the post (match by route `id`). If the stored route has the same `gpx_source_url` and a `gpx_id` that still exists (`get_post( gpx_id )`), keep it. Otherwise sideload and set `gpx_id` and `gpx_name`. Write the updated routes back once. Delete the previous attachment when replaced, so the media library does not fill up.
3. Scrapers only ever set `gpx_source_url`; they never set `gpx_id`.

### 4b Which sources have GPX

Before touching individual scrapers, audit the four sources (`dvl`, `mammut`, `opdagverden`, `sportstiming`) by opening three event pages each and searching the HTML for `.gpx`, and for Komoot or Plotaroute links (those cannot be downloaded as GPX without an account, log them but skip). Write the result as a short table in `docs/scrapers.md`. Only implement detection for sources that actually link GPX files. If none do today, stop after 4a and leave a note; the base support is still useful for future sources.

### 4c Per scraper detection

For each source with GPX links: in `parse()`, collect `<a>` elements whose `href` ends in `.gpx` (case insensitive, ignore query strings). If the event has one route, attach the first GPX to it. If several routes and several GPX links, match by the distance number in the link text or filename ("30 km", "30km", "_30_"); unmatched links are ignored and logged to the scraper log. Absolute URLs via `WP_Http::make_absolute_url()`.

Test: run `./scrape.sh` twice on local and confirm the second run does not re download (check the scraper log and that the attachment ID is unchanged).

## PR 5 (optional, later): prefill distance from the GPX

In the editor, after a GPX is selected, fetch the file URL, parse the `trkpt` points in the browser and sum haversine distances; if `distance_km` is empty, fill it rounded to one decimal. Small and self contained, nice for organisers, but not needed for the feature to work.

## Decisions and why

- GPX stays a file; we do not store the geometry in post meta. Tracks can have thousands of points and the map only needs them in the browser.
- One code path on the frontend: everything is an attachment ID, whether uploaded or scraped. Scrapers download the file instead of linking to the source, so the map does not break when the source site changes or blocks hotlinking.
- Fields live inside the existing route objects, not as new meta keys, because a GPX belongs to a distance, not to the event.
- Leaflet and leaflet-gpx from cdnjs, consistent with the Event Map block and the CSP already allowing cdnjs.

## Open questions for Petya

1. Map placement: under the description in the main column (planned) or inside the info card sidebar? The main column gives it width; the sidebar is where the route tabs are.
2. Should the GPX be downloadable by anyone, or only logged in users? Planned: anyone.
3. Should the calendar and cards show a small "route map" icon on events that have a GPX? Cheap to add later.
