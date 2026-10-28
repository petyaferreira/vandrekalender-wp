# 0011: PHP 8.4 everywhere, the version Nordicway runs

Decided 23 October 2026 with Petya ([#147](https://github.com/petyaferreira/vandrekalender-wp/issues/147)).

## Decision

Production and staging on Nordicway run PHP 8.4, chosen in cPanel. Everything that runs our code uses the same version: local Docker (`Dockerfile`, `wordpress:php8.4-apache`), CI and the staging deploy (`shivammathur/setup-php` with `php-version: '8.4'` in `ci.yml` and `deploy-to-nordicway.yml`), and the daily agent's cloud environment. `./validate.sh` fails on any other version (`PHP_VERSION` in its `versions` check). `composer.json` requires `php` `>=8.4` and sets `config.platform.php` to `8.4.1` (PHPUnit 13 needs `>=8.4.1`, so plain `8.4` does not resolve), so Composer and Dependabot resolve for 8.4 whatever PHP they run on.

## Why

- CI used whatever `ubuntu-latest` ships (8.3.6) and the cloud sandbox ships 8.3, while local Docker and Nordicway run 8.4. Dependabot's PHPUnit 13 PR (#135) needs PHP 8.4: it would pass locally and failed in CI. A test that passes on one PHP version says little about another.
- Nordicway recommends 8.4 or 8.5 (notice of 22 October 2026, after the PHP security release), and 8.4 is supported upstream until the end of 2028.

## Do not propose

Leaving CI on the runner's default PHP, or a second PHP version in CI, without changing Nordicway first.

## Revisit when

Nordicway's PHP version is changed in cPanel. Change it here, in the `Dockerfile`, in both workflows, in `PHP_VERSION` in `validate.sh`, in `composer.json` (`require.php`, `config.platform.php`) and in the cloud setup script in the same PR.
