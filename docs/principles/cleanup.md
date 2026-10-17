# Clean up after every change

Applies to every code change and every PR, not only planned features. When you replace or remove something (a service, an API, a function, a field, a behaviour), remove everything that belonged to the old version in the same change:

- Dead code: unused constants, functions, imports, CSS, npm/Composer packages, options and meta that nothing reads any more.
- Names that describe the old behaviour (rename `reverseRef` once there is no reverse lookup).
- Code comments and docblocks that describe the old behaviour or mention the old service.
- Docs in `docs/` and `CLAUDE.md` that describe the old behaviour.

Before calling a change done, grep the repo for the old names (service name, URLs, constants, function names) and go through every hit. If a leftover belongs to a part that a later PR in the plan replaces, leave it and say so in the PR description, naming that PR. Never leave commented-out old code "just in case"; git history keeps it.

## Example

When CI's `Build` job became `Validate` (#55), the same PR updated every place that described the old job: the comment in `ci.yml`, `docs/deployment.md`, `CLAUDE.md`, README and the `setup-hooks.sh` help text. The review then confirmed that no reference to the old job remained.

## Enforcement

The review bot (`.github/workflows/claude-code-review.yml`) checks every PR for leftovers and flags leftovers, unless the PR description names the later PR that handles them. Leftovers that need a later deploy before they can go become a GitHub issue in the same change.

## Related

- [Two-way and one-way doors](two-way-doors.md)
