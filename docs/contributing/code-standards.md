# Code standards

```bash
./validate.sh         # the one check: CLAUDE.md size, PHPCS, Prettier, theme + plugin build
composer run phpcs    # PHPCS only
composer run phpcbf   # auto-fix PHP
```

`./validate.sh` never changes source files, runs every check even after one fails, and ends with `validate: failed checks: <names>` (exit 1) or `validate: all checks passed`. CI's Validate job runs the same script on every push and pull request, and the staging deploy waits for it.

`claude-md-size` fails when `CLAUDE.md` grows past `MAX_CLAUDE_MD_LINES` in `validate.sh`: see [lean-agent-context.md](../principles/lean-agent-context.md).

WordPress-Extra ruleset. Text domains: `vandrekalender-theme`, `vandrekalender-events`. The pre-commit hook auto-fixes staged PHP (phpcbf) and JS/SCSS/CSS/JSON (Prettier), and blocks the commit if PHP violations remain.
