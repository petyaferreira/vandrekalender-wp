#!/bin/bash
# Installs the dependency versions the lockfiles name, then rebuilds the theme
# and the plugin. Run it after every `git pull`, and after merging Dependabot
# PRs: merging changes the lockfiles on GitHub, not what is installed here.
#
#   ./update.sh
#
# It never updates to newer versions (that is Dependabot's job, via PRs). It
# only makes this machine match the committed lockfiles:
#   composer install   WordPress plugins and PHPCS, from composer.lock
#   npm ci             root, theme and plugin, from each package-lock.json
#   npm run build      theme public/ and plugin build/, so bundled libraries
#                      such as Leaflet are the new versions on the site too
#
# Stop any `npm run start` watcher first: npm ci replaces node_modules under it.
# Stops at the first step that fails.

set -euo pipefail

THEME_DIR=wp-content/themes/vandrekalender-theme
PLUGIN_DIR=wp-content/plugins/vandrekalender-events

GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

cd "$(git rev-parse --show-toplevel)"

# The builds must run on the Node version in .nvmrc (decision 0012). nvm is a
# shell function, so the script cannot switch for you.
# Compares major versions only, so .nvmrc may say 24, v24 or 24.1.0.
if ! command -v node >/dev/null; then
    echo "Node is not installed. Install nvm, then: nvm install, then ./update.sh again" >&2
    exit 1
fi
want_node=$(tr -d '[:space:]v' <.nvmrc)
want_node=${want_node%%.*}
have_node=$(node -p 'process.versions.node.split(".")[0]')
if [ "$have_node" != "$want_node" ]; then
    echo "Node $have_node, but .nvmrc wants $want_node. Run: nvm use, then ./update.sh again" >&2
    exit 1
fi

step() {
    echo -e "\n${YELLOW}==> $1${NC}"
}

step "composer install"
composer install --no-interaction

step "npm ci (root)"
npm ci

for dir in "$THEME_DIR" "$PLUGIN_DIR"; do
    step "npm ci && npm run build ($dir)"
    (cd "$dir" && npm ci && npm run build)
done

echo -e "\n${GREEN}Up to date with the lockfiles.${NC}"
echo "After plugin updates, open wp-admin once: some plugins update their database tables on the first admin page load."
