# 0013: In the cloud, agents use the GitHub REST API only

Decided 23 October 2026 ([#142](https://github.com/petyaferreira/vandrekalender-wp/issues/142)).

## Decision

In a cloud session, agents talk to GitHub through the REST API only: `gh api repos/petyaferreira/vandrekalender-wp/...`, and REST-based subcommands such as `gh workflow run` and `gh run list`. They do not use `gh pr`, `gh issue` or `gh project`, which are built on GraphQL.

## Why

Every GitHub request from an Anthropic-hosted cloud session goes through Anthropic's GitHub proxy. The proxy rejects GitHub's GraphQL endpoint with a 403 ("GitHub GraphQL is not available from Claude Code sessions"), whatever credentials are used, including a `GH_TOKEN` set on the environment. `gh pr`, `gh issue` and `gh project` get the same 403, and Projects v2 cannot be reached at all. REST works. Source: [Configure cloud environments → GitHub proxy](https://code.claude.com/docs/en/cloud-environments#github-proxy).

Agents kept rediscovering this (#103, #105, #139), and some then proposed a token or an environment setting as the fix.

## Do not propose

A `GH_TOKEN`, an environment network setting, or a self-hosted environment to get GraphQL back.

## Revisit when

The docs page above no longer says GraphQL is blocked.
