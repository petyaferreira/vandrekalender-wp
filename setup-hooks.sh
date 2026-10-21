#!/bin/bash

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

echo -e "${YELLOW}Setting up Git hooks...${NC}"

if ! PROJECT_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"; then
    echo -e "${RED}Error: Not in a Git repository${NC}"
    exit 1
fi
cd "$PROJECT_ROOT"

# Hooks live in the git dir every worktree shares (`.git` of the main
# checkout). In a worktree `.git` is a file pointing there, not a folder.
HOOKS_DIR="$(git rev-parse --git-common-dir)/hooks"

if [ ! -f "./vendor/bin/phpcs" ]; then
    echo -e "${YELLOW}Installing Composer dependencies...${NC}"
    composer install
    if [ $? -ne 0 ]; then
        echo -e "${RED}Error: Failed to install Composer dependencies${NC}"
        exit 1
    fi
fi

mkdir -p "$HOOKS_DIR"

if [ -f ".githooks/pre-commit" ]; then
    cp .githooks/pre-commit "$HOOKS_DIR/pre-commit"
    chmod +x "$HOOKS_DIR/pre-commit"
    echo -e "${GREEN}✓ Pre-commit hook installed${NC}"
else
    echo -e "${RED}Error: .githooks/pre-commit not found${NC}"
    exit 1
fi

echo -e "${GREEN}"
echo "=================================================="
echo "Git hooks setup complete!"
echo "  ./validate.sh         — run every check (same as CI)"
echo "  composer run phpcs    — check standards"
echo "  composer run phpcbf   — auto-fix"
echo "  npx prettier --write <file> — format JS/SCSS"
echo "=================================================="
echo -e "${NC}"
