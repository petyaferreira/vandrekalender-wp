#!/bin/bash
# The GitHub commands agents need, built only on REST calls (gh api), so they
# work the same locally and in the cloud. In the cloud, gh pr, gh issue and
# gh project fail: Anthropic's GitHub proxy blocks GraphQL
# (docs/decisions/0013-github-rest-only-in-the-cloud.md,
# https://code.claude.com/docs/en/cloud-environments#github-proxy).
#
#   ./gh-rest.sh agent-ready                  open agent-ready issues, oldest first
#   ./gh-rest.sh pr-checks <PR>               name, status and conclusion of each check run on the PR's head
#   ./gh-rest.sh pr-review <PR>               last claude[bot] summary, then the inline comments
#   ./gh-rest.sh pr-create <title> <body-file>  open a PR from the current branch to main
#
# A failed API call stops the script with a non-zero exit. No call here uses
# the word "merge": .claude/settings.json denies `gh api *merge*`.

set -euo pipefail

REPO=petyaferreira/vandrekalender-wp

usage() {
    echo "usage: ./gh-rest.sh agent-ready | pr-checks <PR> | pr-review <PR> | pr-create <title> <body-file>" >&2
    exit 2
}

pr_number() {
    [[ "${1:-}" =~ ^[0-9]+$ ]] || usage
}

# Open issues with agent-ready and without needs-decision, minus those an open
# PR mentions (#<n> in its body, or a branch named <prefix>/<n>-...). Oldest first.
agent_ready() {
    local prs
    prs=$(gh api --paginate "repos/$REPO/pulls?state=open&per_page=100" |
        jq -s '[.[][] | {body: (.body // ""), ref: .head.ref}]')
    gh api --paginate "repos/$REPO/issues?labels=agent-ready&state=open&sort=created&direction=asc&per_page=100" |
        jq -r --argjson prs "$prs" '
            .[]
            | select(has("pull_request") | not)
            | select(any(.labels[]; .name == "needs-decision") | not)
            | .number as $n
            | select(any($prs[]; (.body | test("(^|[^0-9])#\($n)([^0-9]|$)")) or (.ref | test("/\($n)(-|$)"))) | not)
            | "\(.number)\t\(.title)"'
}

pr_checks() {
    local sha
    sha=$(gh api "repos/$REPO/pulls/$1" --jq .head.sha)
    gh api --paginate "repos/$REPO/commits/$sha/check-runs" \
        --jq '.check_runs[] | "\(.name) \(.status) \(.conclusion)"'
}

pr_review() {
    local summary
    summary=$(gh api --paginate "repos/$REPO/issues/$1/comments" |
        jq -rs '[.[][] | select(.user.login == "claude[bot]")] | last | .body // empty')
    echo "== Summary =="
    echo "${summary:-(no claude[bot] comment yet)}"
    echo
    echo "== Inline comments =="
    gh api --paginate "repos/$REPO/pulls/$1/comments" --jq '.[] | "\(.path):\(.line // .original_line) \(.body)"'
}

pr_create() {
    local title="${1:-}" body_file="${2:-}" branch
    [ -n "$title" ] && [ -f "$body_file" ] || usage
    branch=$(git symbolic-ref --short -q HEAD) || { echo "pr-create: detached HEAD, switch to the branch with the change" >&2; exit 1; }
    [ "$branch" != main ] || { echo "pr-create: on main, switch to the branch with the change" >&2; exit 1; }
    gh api -X POST "repos/$REPO/pulls" -f title="$title" -f head="$branch" -f base=main -F body=@"$body_file" \
        --jq '"#\(.number) \(.html_url)"'
}

case "${1:-}" in
    agent-ready) agent_ready ;;
    pr-checks) pr_number "${2:-}" && pr_checks "$2" ;;
    pr-review) pr_number "${2:-}" && pr_review "$2" ;;
    pr-create) pr_create "${2:-}" "${3:-}" ;;
    *) usage ;;
esac
