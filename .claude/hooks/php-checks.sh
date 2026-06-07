#!/usr/bin/env bash
#
# PostToolUse (Write|Edit) — PHP checks on the edited file.
#   1. php -l syntax check     -> BLOCKING (exit 2 surfaces stderr to Claude)
#   2. Pint style check        -> informational (pre-commit + CI enforce)
#
# Reads the hook payload from stdin (no CLAUDE_TOOL_INPUT env var exists).
set -uo pipefail

payload=$(cat)
fp=$(printf '%s' "$payload" | python3 -c "import sys,json; print(json.load(sys.stdin).get('tool_input',{}).get('file_path',''))" 2>/dev/null)

case "$fp" in
    *.php) ;;
    *) exit 0 ;;
esac
[ -f "$fp" ] || exit 0
case "$fp" in
    */vendor/*) exit 0 ;;
esac

cd "${CLAUDE_PROJECT_DIR:-.}" 2>/dev/null || true

# 1) Syntax — blocking
if ! lint=$(php -l "$fp" 2>&1); then
    {
        echo "PHP syntax error in $fp:"
        echo "$lint"
    } >&2
    exit 2
fi

# 2) Pint style — informational only
if [ -x vendor/bin/pint ] && ! vendor/bin/pint --test "$fp" >/dev/null 2>&1; then
    python3 - "$fp" <<'PY'
import json, sys
fp = sys.argv[1]
print(json.dumps({"hookSpecificOutput": {
    "hookEventName": "PostToolUse",
    "additionalContext": f"Pint style issues in {fp} — run: vendor/bin/pint {fp}",
}}))
PY
fi

exit 0
