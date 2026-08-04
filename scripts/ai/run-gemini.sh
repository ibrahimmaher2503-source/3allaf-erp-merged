#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"

if [[ -z "$ROOT" ]]; then
    echo "Error: run this command inside a Git repository." >&2
    exit 1
fi

cd "$ROOT"

TASK_FILE="${1:-.ai/CURRENT_TASK.md}"
MODEL="${AGY_MODEL:-gemini-3.1-pro}"
EFFORT="${AGY_EFFORT:-high}"

if [[ ! -f "$TASK_FILE" ]]; then
    echo "Error: task file not found: $TASK_FILE" >&2
    exit 1
fi

if ! command -v agy >/dev/null 2>&1; then
    echo "Error: agy is not installed or not available in PATH." >&2
    exit 1
fi

mkdir -p .ai/logs

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
LOG_FILE=".ai/logs/agy-${TIMESTAMP}.json"

PROMPT="$(cat "$TASK_FILE")"

agy -p "$PROMPT" \
    --model "$MODEL" \
    --effort "$EFFORT" \
    --output-format json \
    --dangerously-skip-permissions \
    > "$LOG_FILE"

echo "Gemini finished."
echo "Log: $LOG_FILE"
echo
echo "Changed files:"
git status --short
echo
echo "Diff summary:"
git diff --stat
