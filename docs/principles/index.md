# Engineering principles

Short rules for how we build Vandrekalender. Each page is one rule with a real example from this repo. Open only the page the task needs.

| Principle | When to open it |
|---|---|
| [Clean up after every change](cleanup.md) | Replacing or removing anything: a service, function, field, behaviour or name |
| [Normalized source of truth](normalized-source-of-truth.md) | Storing a value that could be computed from data you already have, or copying a helper |
| [Fail loudly at the right layer](fail-loudly.md) | A lookup, file or install could be missing and the code could carry on quietly |
| [Two-way and one-way doors](two-way-doors.md) | Changing URLs, stored meta shapes or production data; deciding how much review a change needs |
| [Examples over prose](examples-over-prose.md) | Writing docs, or a procedure with several steps |
| [Dependabot for every npm package.json](dependabot-for-npm.md) | Adding, moving or removing a `package.json`, or adding a third-party library |
| [Keep agent context lean](lean-agent-context.md) | Editing `CLAUDE.md` or an `index.md`, or deciding where a rule belongs |

How we work: [../contributing/](../contributing/index.md). How the product works: [../index.md](../index.md).
