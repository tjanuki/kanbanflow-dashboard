#!/bin/bash

# Script to create files in .claude/output directory with proper naming convention
# Usage: ./create-output.sh <task_name> <file_name>

# Check if correct number of arguments provided
if [ $# -ne 2 ]; then
    echo "Usage: $0 <task_name> <file_name>"
    echo "Example: $0 user_notifications notification-strategy.md"
    exit 1
fi

# Get parameters
TASK_NAME="$1"
FILE_NAME="$2"

# Add .md extension if not present
if [[ "$FILE_NAME" != *.md ]]; then
    FILE_NAME="${FILE_NAME}.md"
fi

# Extract just the date part (YYYYMMDD) from timestamp
DATE_PART=$(date +"%Y%m%d")

# Look for existing directory with same task name for today
EXISTING_DIR=$(find ".claude/output/${DATE_PART}" -maxdepth 1 -type d -name "*_${TASK_NAME}" 2>/dev/null | head -1)

if [ -n "$EXISTING_DIR" ]; then
    # Use existing directory
    OUTPUT_DIR="$EXISTING_DIR"
    echo "Using existing directory: ${OUTPUT_DIR}"
else
    # Create new timestamped directory
    TIMESTAMP=$(date +"%Y%m%d_%H%M")
    OUTPUT_DIR=".claude/output/${DATE_PART}/${TIMESTAMP}_${TASK_NAME}"
    echo "Creating new directory: ${OUTPUT_DIR}"
fi

# Create the directory structure
mkdir -p "$OUTPUT_DIR"

# Create the file
FILE_PATH="${OUTPUT_DIR}/${FILE_NAME}"
printf "📝 Use UTF-8 for the document. Write document in English." > "$FILE_PATH"

# Output success message
echo "Created file: ${FILE_PATH}"
