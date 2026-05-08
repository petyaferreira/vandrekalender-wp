#!/bin/bash
export $(grep -E 'PROJECT_NAME|WORDPRESS_DB_' .env | xargs)

if [ -z "$PROJECT_NAME" ] || [ -z "$WORDPRESS_DB_NAME" ] || [ -z "$WORDPRESS_DB_USER" ] || [ -z "$WORDPRESS_DB_PASSWORD" ]; then
  echo "❌ Required environment variables not set in .env"
  exit 1
fi

CONTAINER_ID=$(docker compose --env-file .env -p "$PROJECT_NAME" ps -q db)

if [ -z "$CONTAINER_ID" ]; then
  echo "❌ Could not find a running 'db' container for project '$PROJECT_NAME'"
  exit 1
fi

TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
OUTPUT_FILE="./${WORDPRESS_DB_NAME}_${TIMESTAMP}.sql"

echo "📤 Exporting database '$WORDPRESS_DB_NAME' to $OUTPUT_FILE..."

docker compose --env-file .env -p "$PROJECT_NAME" exec db \
  sh -c "mysqldump -u$WORDPRESS_DB_USER -p$WORDPRESS_DB_PASSWORD $WORDPRESS_DB_NAME" > "$OUTPUT_FILE"

if [ $? -ne 0 ]; then
  echo "❌ Export failed. Please check your credentials or running containers."
  exit 1
fi

echo "✅ Export complete: $OUTPUT_FILE"
