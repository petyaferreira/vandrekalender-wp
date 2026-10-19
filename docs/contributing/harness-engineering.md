# Harness engineering

The repo is set up so an agent can take an issue to a reviewed PR without someone watching each step. Petya steers (priorities, decisions, merges); agents do the implementation. Petya's attention is the scarce resource.

## The loop

1. The issue says what is wrong or wanted ([tasks-and-issues.md](tasks-and-issues.md)).
2. Implement.
3. `./validate.sh`, then test on the local stack ([pull-requests.md](pull-requests.md)).
4. Capture what was learned in the repo: a doc, a check, a script. Chat history and one machine's local agent memory are lost to the next agent and invisible to the review bot.
5. When the same mistake or review comment comes up twice, turn it into the strongest cheap guardrail that works.

## Guardrails, strongest first

1. **A check in `./validate.sh`** — when the mistake is visible in one file without guessing. Put the why and the fix in the failure message. Example: `claude-md-size`.
2. **A test** — when the failure is behaviour and a static check would be guessing. (There are no automated tests yet.)
3. **A script** — when the workflow has several steps: `./validate.sh`, `./scrape.sh`, `./wp.sh`.
4. **The review bot prompt** (`.github/workflows/claude-code-review.yml`) — for judgement calls a script cannot make, such as cleanup leftovers or a choice made without a decision record.
5. **Docs** — describe how things work and point at the check. A rule that lives only in prose is last.

Based on kody's harness engineering guide and OpenAI's "Harness engineering: leveraging Codex in an agent-first world".
