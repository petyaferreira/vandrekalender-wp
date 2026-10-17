#!/bin/bash
# The single check that a change is ready: CLAUDE.md size, PHPCS, Prettier and
# both builds.
# CI runs this same script (.github/workflows/ci.yml), so green here is green there.
#
# Read-only for source files: it reports problems and never fixes them. The
# builds write their usual output (theme public/, plugin build/), which git ignores.
# Every check runs even after one fails, and the summary names the failed ones.

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

PROJECT_ROOT="$(git rev-parse --show-toplevel)"
cd "$PROJECT_ROOT"

THEME_DIR=wp-content/themes/vandrekalender-theme
PLUGIN_DIR=wp-content/plugins/vandrekalender-events

FAILED=()

# run <name> <how to fix> <command...>
run() {
    local name="$1" fix="$2"
    shift 2
    echo -e "${YELLOW}--- $name${NC}"
    if "$@"; then
        echo -e "${GREEN}✓ $name${NC}"
    else
        echo -e "${RED}✗ $name — $fix${NC}"
        FAILED+=("$name")
    fi
}

# A missing install is a failure, not a skipped check.
require() {
    if [ ! -e "$1" ]; then
        echo -e "${RED}Missing $1 — run: $2${NC}"
        return 1
    fi
}

phpcs() {
    require ./vendor/bin/phpcs "composer install" && ./vendor/bin/phpcs -q
}

# Same files the pre-commit hook formats; .prettierignore still applies.
prettier_check() {
    require ./node_modules/.bin/prettier "npm install" &&
        git ls-files -z -- '*.js' '*.jsx' '*.scss' '*.css' '*.json' |
        xargs -0 ./node_modules/.bin/prettier --check --log-level warn
}

# webpack output is only shown when the build fails.
build() {
    require "$1/node_modules" "cd $1 && npm install" || return 1
    local log
    log="$(cd "$1" && npm run build --silent 2>&1)" && return 0
    echo "$log"
    return 1
}

# CLAUDE.md is read by every agent on every task, so it stays a map.
MAX_CLAUDE_MD_LINES=25

claude_md_size() {
    local lines
    # awk, not wc -l: wc skips a last line that has no trailing newline.
    lines=$(awk 'END { print NR }' CLAUDE.md)
    echo "CLAUDE.md: $lines of $MAX_CLAUDE_MD_LINES lines"
    [ "$lines" -le "$MAX_CLAUDE_MD_LINES" ]
}

run claude-md-size "CLAUDE.md is a map read on every task: move the detail into a page under docs/ and link it (docs/principles/lean-agent-context.md). Raise MAX_CLAUDE_MD_LINES in validate.sh only on purpose" claude_md_size
run phpcs "fix with: composer run phpcbf, then fix what remains by hand" phpcs
run prettier "fix with: ./node_modules/.bin/prettier --write <file>" prettier_check
run theme-build "see the webpack error above" build "$THEME_DIR"
run plugin-build "see the webpack error above" build "$PLUGIN_DIR"

echo
if [ ${#FAILED[@]} -gt 0 ]; then
    echo -e "${RED}validate: failed checks: ${FAILED[*]}${NC}"
    exit 1
fi

echo -e "${GREEN}validate: all checks passed${NC}"
