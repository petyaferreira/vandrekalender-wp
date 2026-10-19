#!/bin/bash
# The only way an agent merges a PR: only low-risk PRs, only when everything is
# green for the commit being merged. Medium and high risk PRs are Petya's.
# See docs/contributing/pull-requests.md (Risk categories) and
# docs/decisions/0006-agents-merge-low-risk-prs.md.
#
#   ./merge-low-risk.sh <PR number>
#
# main has no branch protection (decision 0001), so nothing else stops a red
# merge: every condition here refuses by default, and a check that cannot be
# read counts as failed.

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

PR="$1"
if ! [[ "$PR" =~ ^[0-9]+$ ]]; then
    echo "usage: ./merge-low-risk.sh <PR number>" >&2
    exit 2
fi

cd "$(git rev-parse --show-toplevel)" || exit 1

refuse() {
    echo -e "${RED}Not merging #$PR: $1${NC}" >&2
    exit 1
}

pr=$(gh pr view "$PR" --json state,isDraft,baseRefName,headRefOid \
    --jq '[.state, (.isDraft | tostring), .baseRefName, .headRefOid] | join(" ")') ||
    refuse "could not read the PR"
read -r state draft base sha <<<"$pr"

[ "$state" = OPEN ] || refuse "it is $state, not open"
[ "$draft" = false ] || refuse "it is a draft"
[ "$base" = main ] || refuse "it targets $base, not main"

risk=$(./pr-risk.sh "$PR") || refuse "could not work out its risk category"
[ "$risk" = low ] || refuse "it is $risk risk (files above). Petya merges it"

# conclusion <check name> → the conclusion of the latest run of that check on
# the head commit, or nothing.
conclusion() {
    gh api "repos/{owner}/{repo}/commits/$sha/check-runs?check_name=$1" \
        --jq '.check_runs | sort_by(.started_at) | last | .conclusion // empty'
}

[ "$(conclusion Validate)" = success ] || refuse "Validate has not passed on the head commit ${sha:0:7}"
[ "$(conclusion claude-review)" = success ] || refuse "the review has not passed on the head commit ${sha:0:7}"

# The review check passes only when a summary was posted. Read the newest
# summary posted after that run started, so a verdict on an older commit
# never counts.
review_started=$(gh api "repos/{owner}/{repo}/commits/$sha/check-runs?check_name=claude-review" \
    --jq '.check_runs | sort_by(.started_at) | last | .started_at // empty')
[ -n "$review_started" ] || refuse "could not find the review run for ${sha:0:7}"

verdict=$(gh api --paginate "repos/{owner}/{repo}/issues/$PR/comments" \
    --jq ".[] | select(.user.login == \"claude[bot]\" and .created_at >= \"$review_started\") | .body" |
    tr -d '\r' | grep -E '^[`* ]*Verdict:' | tail -n 1 | tr -d '`*' | sed 's/^ *//; s/ *$//')
[ "$verdict" = "Verdict: no findings" ] ||
    refuse "the review of ${sha:0:7} does not end with 'Verdict: no findings' (found: '${verdict:-none}')"

# --match-head-commit: if anything was pushed after these checks, GitHub refuses.
gh pr merge "$PR" --merge --match-head-commit "$sha" || refuse "GitHub refused the merge"

# So Petya can see what was merged without her:
#   gh pr list --state merged --label auto-merged
gh label create auto-merged --color C2D9B0 --description "Low-risk PR merged by an agent (merge-low-risk.sh)" 2>/dev/null
gh pr edit "$PR" --add-label auto-merged >/dev/null ||
    echo -e "${RED}Merged, but could not add the auto-merged label to #$PR: add it by hand${NC}" >&2

echo -e "${GREEN}Merged #$PR (low risk, Validate and review green on ${sha:0:7})${NC}"
