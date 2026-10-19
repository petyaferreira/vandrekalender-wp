# Pull requests

Every PR starts from an issue ([tasks-and-issues.md](tasks-and-issues.md)) and goes through the two sections below. Features split into steps in a plan file also follow [Planned features](#planned-features).

## Before opening a PR

- `./validate.sh` must pass (it runs on the host, not in Docker). See [code-standards.md](code-standards.md).
- Test the change on the local Docker stack (as the plan describes, for planned features). Use `./wp.sh` for WP-CLI and `curl` against `http://localhost:8080` for pages and REST. Say clearly in the PR description which tests you ran and which ones you could not run (for example anything that needs clicking in the browser).
- Frontend changes are verified with a screenshot of each state plus a computed-style probe of a known-good element (`getComputedStyle`, `getBoundingClientRect()`), never with element counts alone. While fixing the event map (July 2026), tile and marker counts looked healthy through four wrong fixes while the map was visibly broken.
- Update the docs the change affects (for a planned feature, the docs the plan names, for example `docs/data-model.md`).
- If the change chose one approach over a real alternative that someone could propose again (a tool, a library, a way of testing or deploying), add a decision record in the same PR ([decisions/](../decisions/index.md)) and name it in the PR description. Do not wait to be asked: in #68 the choice of plain PHPUnit over the WordPress test suite was only recorded (0005) after Petya asked.

## Review loop

- Opening or updating a PR starts the automatic review (`.github/workflows/claude-code-review.yml`). Wait for it with `gh pr checks <number> --watch`.
- Read the review: the summary with `gh pr view <number> --comments` and the inline comments with `gh api repos/petyaferreira/vandrekalender-wp/pulls/<number>/comments`.
- A PR that changes `.github/workflows/claude-code-review.yml` is **not reviewed**: the action refuses to run when its own workflow differs from `main`. The `claude-review` check is then red with "this PR is NOT reviewed" (it fails whenever a run posts no summary comment). Keep such changes in their own small PR and ask Petya to review it by hand; Petya merges it despite the red check.
- Treat review comments as suggestions, not orders. Fix the ones that are correct. If you disagree, reply on the comment with the reason and do not change the code.
- Push the fixes to the same branch, then check the review again.
- Stop after 3 review rounds per PR and ask Petya what to do.
- If an earlier PR in the stack changes after later PRs exist, tell Petya and rebase the later branches on top of it.

## Planned features

Used for features that are split into small steps in a plan file, for example `docs/history/route-gpx-plan.md`. Do one PR (one step) at a time.

- Branch names: `feature/<feature>-<step number>-<short name>`, for example `feature/gpx-1-uploads`.
- Step 1 branches from `main` and its PR targets `main`.
- Every later step branches from the previous step's branch, and its PR targets that branch (`gh pr create --base <previous branch>`). This is a stacked PR, so each PR only shows its own changes.
- Only do the step you were asked to do. Do not start the next step.
