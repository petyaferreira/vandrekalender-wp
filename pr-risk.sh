#!/bin/bash
# Risk category of a pull request: low, medium or high, from the files it
# changes. A script decides it, not the agent that wrote the PR, so an agent
# cannot talk its own PR down to "low". See docs/contributing/pull-requests.md
# (Risk categories) and docs/decisions/0006-agents-merge-low-risk-prs.md.
#
#   ./pr-risk.sh <PR number>   category of a PR (prints the files that are not low)
#   ./pr-risk.sh --self-test   check the rules below against known paths (run by validate.sh)
#
# A PR takes the highest category of its files. A file no rule names is high,
# so a new kind of file is never merged without Petya.

# classify <path> → low | medium | high
# Order matters: a case pattern's * also matches "/", so the specific folders
# come before the general docs/* rule.
classify() {
    case "$1" in
        # Rules agents follow, and the decisions behind them.
        CLAUDE.md | docs/contributing/* | docs/principles/* | docs/decisions/*) echo high ;;
        # Plans tell agents what to build.
        docs/plans/*) echo medium ;;
        # Docs that describe the product.
        README.md | docs/*) echo low ;;
        wp-content/themes/vandrekalender-theme/* | wp-content/plugins/vandrekalender-events/*) echo medium ;;
        # Everything else, including the guardrails: validate.sh, composer.*,
        # package*.json, .github/, .githooks/, .claude/, phpcs.xml, tests/.
        *) echo high ;;
    esac
}

rank() {
    case "$1" in
        low) echo 1 ;;
        medium) echo 2 ;;
        *) echo 3 ;;
    esac
}

# Reads paths on stdin, prints the files above low, then the category last.
category_of_files() {
    local worst=low path category
    while IFS= read -r path; do
        [ -z "$path" ] && continue
        category=$(classify "$path")
        [ "$category" != low ] && echo "  $category: $path" >&2
        [ "$(rank "$category")" -gt "$(rank "$worst")" ] && worst=$category
    done
    echo "$worst"
}

self_test() {
    local failed=0 path expected actual
    while read -r expected path; do
        actual=$(classify "$path")
        if [ "$actual" != "$expected" ]; then
            echo "pr-risk: $path is $actual, expected $expected"
            failed=1
        fi
    done <<'EOF'
low README.md
low docs/index.md
low docs/scrapers.md
low docs/business/pitch.md
low docs/history/route-gpx-plan.md
low docs/design/mockup.png
medium docs/plans/new-feature-plan.md
medium wp-content/plugins/vandrekalender-events/includes/scrapers/class-scraper-dvl.php
medium wp-content/themes/vandrekalender-theme/theme.json
high CLAUDE.md
high docs/contributing/pull-requests.md
high docs/principles/cleanup.md
high docs/decisions/0001-no-branch-protection.md
high validate.sh
high pr-risk.sh
high merge-low-risk.sh
high composer.json
high composer.lock
high package.json
high package-lock.json
high phpcs.xml
high phpunit.xml.dist
high .github/workflows/ci.yml
high .githooks/pre-commit
high .claude/settings.json
high tests/Scrapers/DvlScraperTest.php
high wp-content/plugins/other-plugin/plugin.php
high something-new.sh
EOF
    # Highest category wins.
    actual=$(printf 'docs/index.md\nwp-content/themes/vandrekalender-theme/theme.json\n' | category_of_files 2>/dev/null)
    [ "$actual" = medium ] || { echo "pr-risk: docs + theme should be medium, got $actual"; failed=1; }
    actual=$(printf 'docs/index.md\nvalidate.sh\n' | category_of_files 2>/dev/null)
    [ "$actual" = high ] || { echo "pr-risk: docs + validate.sh should be high, got $actual"; failed=1; }

    [ "$failed" -eq 0 ] && echo "pr-risk: all rules match"
    return "$failed"
}

if [ "$1" = --self-test ]; then
    self_test
    exit $?
fi

if ! [[ "$1" =~ ^[0-9]+$ ]]; then
    echo "usage: ./pr-risk.sh <PR number> | --self-test" >&2
    exit 2
fi

# The API, not `gh pr diff --name-only`: a rename also lists the old path, so
# moving validate.sh into docs/ still counts as touching validate.sh.
files=$(gh api --paginate "repos/{owner}/{repo}/pulls/$1/files" --jq '.[] | .filename, (.previous_filename // empty)') || {
    echo "pr-risk: could not list the files of PR #$1" >&2
    exit 1
}
if [ -z "$files" ]; then
    echo "pr-risk: PR #$1 changes no files" >&2
    exit 1
fi

echo "$files" | category_of_files
