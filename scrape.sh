#!/bin/bash
# Manually run the event scraping pipeline in the local Docker stack.
# Results are recorded to the Scraper Log (Events → Scraper Log in wp-admin).
exec "$(dirname "$0")/wp.sh" vandrekalender scrape "$@"
