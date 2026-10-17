# Examples over prose

Show one example that matches the current code. When a procedure has several steps, make it a script. A page about a procedure keeps three things: when it applies, the command, and what failure looks like.

## Example

Checking a change used to be prose in `CLAUDE.md`: run PHPCS at the root, then `npm run build` in the plugin, and in the theme if it was touched. Now it is one script:

- When: before every PR, and in CI on every push.
- Command: `./validate.sh`
- Failure: `validate: failed checks: phpcs prettier`, exit 1. Each failed check prints its own fix.

The same pattern: `./scrape.sh` instead of the WP-CLI command it wraps, `./wp.sh` instead of the `docker compose exec` line.

## Related

- [Keep agent context lean](lean-agent-context.md)
- [Harness engineering](../contributing/harness-engineering.md): when to make a script or a check instead of a doc
