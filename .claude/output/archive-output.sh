#!/bin/bash

# Script to archive output files
# Moves all files/directories in .claude/output to .claude/output/archived
# except .gitignore and this script itself

# Set variables
SCRIPT_NAME=$(basename "$0")
SOURCE_DIR="/Users/tjanuki/Src/ltag/.claude/output"
ARCHIVE_DIR="$SOURCE_DIR/archived"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Ensure we're in the correct directory
cd "$SOURCE_DIR" || { echo "[$TIMESTAMP] Error: Could not change to source directory"; exit 1; }

# Create archive directory if it doesn't exist
if [ ! -d "$ARCHIVE_DIR" ]; then
  mkdir -p "$ARCHIVE_DIR"
  echo "[$TIMESTAMP] Created archive directory: $ARCHIVE_DIR"
fi

# Find and move files (excluding .gitignore, this script, and the archived directory)
echo "[$TIMESTAMP] Starting archive process"

# Use find to identify files to move, then process them with a loop
find . -mindepth 1 -maxdepth 1 -not -name ".gitignore" -not -name "$SCRIPT_NAME" -not -name "archived" -print0 |
while IFS= read -r -d '' item; do
  filename=$(basename "$item")
  if mv "$item" "$ARCHIVE_DIR/"; then
    echo "[$TIMESTAMP] Moved: $filename"
  else
    echo "[$TIMESTAMP] Failed to move: $filename"
  fi
done

echo "[$TIMESTAMP] Archive process completed"
