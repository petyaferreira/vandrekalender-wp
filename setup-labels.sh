#!/bin/bash
# Creates or updates the repo's labels. Safe to run again (--force updates).
# Uses `gh label` only (REST), so it also works in the cloud.
# See docs/contributing/project-setup.md.
#
#   ./setup-labels.sh

set -euo pipefail

label() {
    gh label create "$1" --color "$2" --description "$3" --force
}

label bug "d73a4a" "Something isn't working"
label documentation "0075ca" "Improvements or additions to documentation"
label enhancement "a2eeef" "New feature or request"
label scrapers "1d76db" "Event scrapers and the nightly run"
label admin "fbca04" "wp-admin screens"
label frontend "5319e7" "Theme, blocks, public pages"
label needs-decision "d93f0b" "Petya needs to decide before work starts"
label v2 "c5def5" "Later, after v1"
label agent-ready "0E8A16" "Clear and small enough for the daily agent to work alone"
label auto-merged "C2D9B0" "Low-risk PR merged by an agent (merge-low-risk.sh)"
