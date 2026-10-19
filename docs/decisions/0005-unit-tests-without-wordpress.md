# 0005: Tests run with plain PHPUnit and stand-ins, not the WordPress test suite

Decided 19 October 2026 with Petya ([#68](https://github.com/petyaferreira/vandrekalender-wp/issues/68)).

## Decision

Tests use PHPUnit without loading WordPress. The few WordPress functions the tested code calls are small stand-ins in `tests/wp-stubs.php`, classes that would reach the network are fakes in `tests/fakes/`, and scrapers read saved copies of their sources from `tests/fixtures/`. The official WordPress test suite (`WP_UnitTestCase`, a test database, usually run in Docker) is not used.

## Why

Tests only protect anything if they run on every change, inside `./validate.sh` and CI ([tests.md](../contributing/tests.md)). Plain PHPUnit runs in about a second with nothing to set up, on the host like the other checks. The WordPress suite needs a database and a WordPress install. That would make `./validate.sh` slower and dependent on Docker, and a check that is slow or fails for setup reasons gets skipped or ignored.

The code most likely to break quietly is scraper parsing (dates, places, distances), and parsing needs very little of WordPress. The first test, for DVL, needs five stand-ins.

## Do not propose

The WordPress test suite, or Docker in `./validate.sh`, to test parsing or other code that the stand-ins can cover. When a test hits "Call to undefined function", add a small stand-in.

## Revisit when

A bug happens in code that needs a real database: `upsert_event()`, drafting removed events, GPX sideloading, REST endpoints, queries. Add the WordPress suite then, for that code only, as a separate command, without removing the fast tests.
