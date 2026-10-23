# 0012: Node 24 everywhere, set once in `.nvmrc`

Decided 23 October 2026 with Petya ([#150](https://github.com/petyaferreira/vandrekalender-wp/issues/150)).

## Decision

The theme and plugin build on Node 24 everywhere. The version is set once, in `.nvmrc` at the repo root. CI and the staging deploy read it (`node-version-file: .nvmrc` in `ci.yml` and `deploy-to-nordicway.yml`), locally `nvm use` reads it, and the daily agent's cloud environment installs the same major version in its setup script.

## Why

- `@wordpress/scripts` 36 (Dependabot #137, #138) needs Node `^22.22.2 || ^24.15.0 || >=26`. CI ran Node 20, Petya's Mac 24.10 and the cloud sandbox 22, so the same build ran on three Node versions, two of them unsupported. npm only warns about an unsupported engine (`EBADENGINE`), so CI stayed green.
- Node 24 is the current LTS, supported until April 2028. Node 22 ends in April 2027.
- One file instead of a number in each workflow: the PHP version had the same problem (decision 0011).

## Do not propose

A Node version written into a workflow instead of read from `.nvmrc`, or a second Node version in CI.

## Revisit when

Node 24 nears its end of life (April 2028), or a build tool needs a newer Node. Change `.nvmrc` and the cloud setup script in the same PR.
