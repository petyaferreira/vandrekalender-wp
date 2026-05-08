#!/bin/bash

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

echo -e "${YELLOW}Setting up Git hooks...${NC}"

PROJECT_ROOT="$(git rev-parse --show-toplevel)"
cd "$PROJECT_ROOT"

if [ ! -d ".git" ]; then
    echo -e "${RED}Error: Not in a Git repository${NC}"
    exit 1
fi

if [ ! -f "./vendor/bin/phpcs" ]; then
    echo -e "${YELLOW}Installing Composer dependencies...${NC}"
    composer install
    if [ $? -ne 0 ]; then
        echo -e "${RED}Error: Failed to install Composer dependencies${NC}"
        exit 1
    fi
fi

mkdir -p .git/hooks

if [ -f ".githooks/pre-commit" ]; then
    cp .githooks/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
    echo -e "${GREEN}✓ Pre-commit hook installed${NC}"
else
    echo -e "${RED}Error: .githooks/pre-commit not found${NC}"
    exit 1
fi

echo -e "${GREEN}"
echo "=================================================="
echo "Git hooks setup complete!"
echo "  composer run phpcs    — check standards"
echo "  composer run phpcbf   — auto-fix"
echo "=================================================="
echo -e "${NC}"
