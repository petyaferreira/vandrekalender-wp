#!/bin/bash
# The single check that a change is ready: PHP and Node versions, CLAUDE.md size, Dependabot coverage, the daily agent's REST-only steps, PHPCS, Prettier,
# unit tests, the PR risk rules and both builds.
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

# No WordPress, database or network: see docs/contributing/tests.md.
phpunit() {
    require ./vendor/bin/phpunit "composer install" && ./vendor/bin/phpunit
}

# webpack output is only shown when the build fails.
build() {
    require "$1/node_modules" "cd $1 && npm install" || return 1
    local log
    log="$(cd "$1" && npm run build --silent 2>&1)" && return 0
    echo "$log"
    return 1
}

# The PHP and Node every environment runs (decisions 0011 and 0012). npm only
# warns about an unsupported Node, so without this a wrong version stays green.
PHP_VERSION=8.4

runtime_versions() {
    local php node want_node ok=0
    php=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
    node=$(node -p 'process.versions.node.split(".")[0]')
    want_node=$(tr -d '[:space:]v' <.nvmrc)
    echo "PHP $php (want $PHP_VERSION), Node $node (want $want_node, from .nvmrc)"
    [ "$php" = "$PHP_VERSION" ] || ok=1
    [ "$node" = "$want_node" ] || ok=1
    return $ok
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

# Every committed package.json and composer.json needs a Dependabot entry,
# or its dependencies are never updated (docs/principles/dependabot-for-dependencies.md).
dependabot_coverage() {
    local config=.github/dependabot.yml entries manifest dir eco missing=0
    if [ ! -f "$config" ]; then
        echo "Missing $config"
        return 1
    fi
    # One "<ecosystem> <directory>" line per entry.
    entries=$(awk '/package-ecosystem:/ { eco = $NF } /directory:/ { d = $NF; gsub(/["\047]/, "", d); print eco " " d }' "$config")
    while IFS= read -r manifest; do
        dir="/$(dirname "$manifest")"
        [ "$dir" = "/." ] && dir=/
        case "$manifest" in
            *package.json) eco=npm ;;
            *) eco=composer ;;
        esac
        if ! grep -qxF "$eco $dir" <<<"$entries"; then
            echo "$manifest has no entry in $config (package-ecosystem: $eco, directory: $dir)"
            missing=1
        fi
    done < <(git ls-files -- 'package.json' '*/package.json' 'composer.json' '*/composer.json')
    return $missing
}

# The daily agent runs in the cloud, where GraphQL is blocked (decision 0013): its
# steps must not tell it to run gh pr, gh issue or gh project. Only the part
# before "For Petya" is checked; that section is for her own machine.
daily_agent_rest_only() {
    local hits
    hits=$(awk '/^## For Petya/ { exit } /gh (pr|issue|project) / { print FILENAME ":" FNR ": " $0 }' docs/contributing/daily-agent.md)
    [ -z "$hits" ] || { echo "$hits"; return 1; }
}

run versions "switch to the version named above: nvm use for Node; for PHP see docs/decisions/0011-php-84-everywhere.md. To change the version, change it everywhere (decisions 0011 and 0012)" runtime_versions
run claude-md-size "CLAUDE.md is a map read on every task: move the detail into a page under docs/ and link it (docs/principles/lean-agent-context.md). Raise MAX_CLAUDE_MD_LINES in validate.sh only on purpose" claude_md_size
run dependabot "add the missing entry to .github/dependabot.yml (docs/principles/dependabot-for-dependencies.md)" dependabot_coverage
run daily-agent-rest "use ./gh-rest.sh or a gh api REST call in docs/contributing/daily-agent.md: GraphQL is blocked in the cloud (docs/decisions/0013-github-rest-only-in-the-cloud.md)" daily_agent_rest_only
run phpcs "fix with: composer run phpcbf, then fix what remains by hand" phpcs
run prettier "fix with: ./node_modules/.bin/prettier --write <file>" prettier_check
run pr-risk "a path is in the wrong risk category: fix classify() in pr-risk.sh, or the expected category in its self-test if the rule changed on purpose (docs/decisions/0006-agents-merge-low-risk-prs.md)" ./pr-risk.sh --self-test
run tests "a test shows expected vs actual above: fix the code, or if the source site changed, save fresh fixtures and update the expected values on purpose (docs/contributing/tests.md)" phpunit
run theme-build "see the webpack error above" build "$THEME_DIR"
run plugin-build "see the webpack error above" build "$PLUGIN_DIR"

echo
if [ ${#FAILED[@]} -gt 0 ]; then
    echo -e "${RED}validate: failed checks: ${FAILED[*]}${NC}"
    exit 1
fi

echo -e "${GREEN}validate: all checks passed${NC}"
