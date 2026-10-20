# Tests

Open this when you change PHP code (it may need a test), add a test, or `./validate.sh` fails on `tests`.

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

Code that needs a real database (`upsert_event()`, unpublishing, GPX sideloading) is not covered. Why there is no WordPress test suite, and when to add one: [decision 0005](../decisions/0005-unit-tests-without-wordpress.md).

## When a PR needs a test

A PR that changes PHP logic which can run on the stand-ins (input in, value out: parsing, mappings, building query args) adds or extends a test for it in the same PR. Write the test first or alongside, and break the code once on purpose to see it go red.

Not covered yet, so no test is required: code that needs a database (see above), templates (`render.php`) and front-end JS. The PR description says how those were tested instead ([pull-requests.md](pull-requests.md#before-opening-a-pr)).

Moving the PR to a higher [risk category](pull-requests.md#risk-categories) because it touches `tests/` is not a reason to skip the test: the category only decides who merges. If the test really cannot be written now, file an issue ([tasks-and-issues.md](tasks-and-issues.md)) instead of only mentioning it in the PR.

## Fixtures

Fixtures are saved copies of real responses from the source, kept as fetched (Prettier skips `tests/fixtures/`). The test file says when they were saved.

When a source changes its markup, the scraper is fixed against the new markup, so the fixtures must change too:

1. Save fresh copies (`curl -s -A "Vandrekalender/1.0 (+https://allevandreture.dk)" <url> -o tests/fixtures/<source>/<name>`).
2. Update the expected values in the test **by reading the page**, not by copying what the scraper now outputs, or the test only proves the scraper agrees with itself.
3. Say in the PR that fixtures changed and why.

Never change an expected value just to make a failing test pass. A failing test means either the code broke (fix the code) or the source changed (steps above).

## Adding a test

Tests for code outside the scrapers go in a folder named after the area, e.g. `tests/Event/OrganiserTermTest.php`; load the class under test in `tests/bootstrap.php`.

One test file per scraper in `tests/Scrapers/`, following `DvlScraperTest.php`, with its fixture-reading subclass in `tests/fakes/`. Pick pages that cover what is likely to break: for DVL, one tour in summer time and one in winter time, because the date comes from a UTC time converted to Danish time. Then break the code on purpose once (for DVL: remove the `setTimezone()` call) and check that the test goes red.
