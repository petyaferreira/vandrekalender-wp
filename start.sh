#!/bin/bash
PROJECT_NAME=$(grep '^PROJECT_NAME=' .env | cut -d '=' -f2)

if [ -z "$PROJECT_NAME" ]; then
  echo "❌ PROJECT_NAME not found in .env"
  exit 1
fi

# --build makes Compose re-evaluate the Dockerfile on every start. When nothing
# has changed this is ~1s (all layers cached); when the Dockerfile or the base
# image has changed it rebuilds only the affected layers. Without it, plain
# `up` silently reuses the cached image, so Dockerfile edits (e.g. the default
# plugin/theme strip) never take effect until you remember to build by hand.
docker compose --env-file .env -p "$PROJECT_NAME" up --build
