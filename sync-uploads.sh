#!/bin/bash

export $(grep -E 'NORDICWAY_SSH_HOST|NORDICWAY_SSH_PORT|NORDICWAY_SSH_USERNAME|NORDICWAY_DEST_PATH|NORDICWAY_SSH_KEY_PATH' .env | xargs)

if [ -z "$NORDICWAY_SSH_HOST" ] || [ -z "$NORDICWAY_SSH_PORT" ] || [ -z "$NORDICWAY_SSH_USERNAME" ] || [ -z "$NORDICWAY_DEST_PATH" ] || [ -z "$NORDICWAY_SSH_KEY_PATH" ]; then
  echo "❌ Required SSH variables not set in .env"
  exit 1
fi

SSH_KEY_PATH="${NORDICWAY_SSH_KEY_PATH/#\~/$HOME}"

if [ ! -f "$SSH_KEY_PATH" ]; then
  echo "❌ SSH key not found at $SSH_KEY_PATH"
  exit 1
fi

REMOTE="${NORDICWAY_SSH_USERNAME}@${NORDICWAY_SSH_HOST}:${NORDICWAY_DEST_PATH}/uploads/"
LOCAL="wp-content/uploads/"

echo "📥 Syncing uploads from production..."
echo "   Source: $REMOTE"
echo "   Dest:   $LOCAL"

rsync -avz \
  -e "ssh -p $NORDICWAY_SSH_PORT -i $SSH_KEY_PATH" \
  "$REMOTE" \
  "$LOCAL"

if [ $? -eq 0 ]; then
  echo "✅ Uploads synced successfully."
else
  echo "❌ Sync failed. Check your SSH credentials and key permissions."
  exit 1
fi
