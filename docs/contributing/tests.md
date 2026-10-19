# Tests

Open this when you change code that has tests, add a test, or `./validate.sh` fails on `tests`.

```bash
./vendor/bin/phpunit                          # all tests, about a second
./vendor/bin/phpunit --filter test_winter     # one test by name
```

`./validate.sh` runs them, so CI does too, and the staging deploy waits for them.

## What the tests are for

They check that **our code** still does what it did: a scraper given a saved copy of its source's page gives the same events, dates and places. Before them, the only check was opening local and staging by hand, and a change that moved every DVL walk by two hours passed `./validate.sh`.

They do not check that the source site still works. That is the job of the scraper alerts, which email when a source breaks in production ([scrapers.md → Alerts](../scrapers.md#alerts-when-a-source-breaks)).

## How they run

PHPUnit, without WordPress, a database, Docker or the network:

- `tests/bootstrap.php` loads the plugin files under test directly.
- `tests/wp-stubs.php` has small stand-ins for the WordPress functions that code calls (`wp_timezone()` is `Europe/Copenhagen`). A test that reaches an unstubbed function fails with "Call to undefined function": add a stand-in that does what WordPress does for that input, nothing more.
- `tests/fakes/` replaces classes: a `Vandrekalender_Geocoder` that returns a fixed municipality without calling Datafordeler, and per scraper a subclass whose `remote_get()` returns files from `tests/fixtures/<source>/` instead of fetching.
- Test code follows the same PHPCS rules as the plugin (`phpcs.xml` includes `tests/`). The few exceptions are `phpcs:ignore` comments in the file with the reason, for example test files named like their class (`DvlScraperTest.php`) as PHPUnit requires. Not `exclude-pattern` in `phpcs.xml`: the pre-commit hook checks a copy in a temporary folder, where path patterns do not match.

Code that needs a real database (`upsert_event()`, unpublishing, GPX sideloading) is not covered. That would need the WordPress test suite in Docker; add it only when a bug there makes it worth it.

## Fixtures

Fixtures are saved copies of real responses from the source, kept as fetched (Prettier skips `tests/fixtures/`). The test file says when they were saved.

When a source changes its markup, the scraper is fixed against the new markup, so the fixtures must change too:

1. Save fresh copies (`curl -s -A "Vandrekalender/1.0 (+https://allevandreture.dk)" <url> -o tests/fixtures/<source>/<name>`).
2. Update the expected values in the test **by reading the page**, not by copying what the scraper now outputs, or the test only proves the scraper agrees with itself.
3. Say in the PR that fixtures changed and why.

Never change an expected value just to make a failing test pass. A failing test means either the code broke (fix the code) or the source changed (steps above).

## Adding a test

One test file per scraper in `tests/Scrapers/`, following `DvlScraperTest.php`, with its fixture-reading subclass in `tests/fakes/`. Pick pages that cover what is likely to break: for DVL, one tour in summer time and one in winter time, because the date comes from a UTC time converted to Danish time. Then break the code on purpose once (for DVL: remove the `setTimezone()` call) and check that the test goes red.
