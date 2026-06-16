#!/bin/bash
PROJECT_NAME=$(grep '^PROJECT_NAME=' .env | cut -d '=' -f2)

if [ -z "$PROJECT_NAME" ]; then
  echo "❌ PROJECT_NAME not found in .env"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -q "${PROJECT_NAME}-wordpress"; then
  echo "❌ WordPress container is not running. Start it with ./start.sh"
  exit 1
fi

docker compose --env-file .env -p "$PROJECT_NAME" exec wordpress wp "$@"
