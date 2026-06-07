#!/usr/bin/env bash
#
# PostToolUse (Write|Edit) — Prettier check on edited frontend files.
# Informational only (npm run format / CI enforce). Skips Wayfinder-generated code.
#
# Reads the hook payload from stdin.
set -uo pipefail

payload=$(cat)
fp=$(printf '%s' "$payload" | python3 -c "import sys,json; print(json.load(sys.stdin).get('tool_input',{}).get('file_path',''))" 2>/dev/null)

case "$fp" in
    *.ts|*.js|*.vue|*.css) ;;
    *) exit 0 ;;
esac
[ -f "$fp" ] || exit 0

# Only resources/, and never the generated Wayfinder output.
case "$fp" in
    *resources/js/actions/*|*resources/js/routes/*|*resources/js/wayfinder/*) exit 0 ;;
    *resources/*) ;;
    *) exit 0 ;;
esac

cd "${CLAUDE_PROJECT_DIR:-.}" 2>/dev/null || true
[ -x node_modules/.bin/prettier ] || exit 0

if ! node_modules/.bin/prettier --check "$fp" >/dev/null 2>&1; then
    python3 - "$fp" <<'PY'
import json, sys
fp = sys.argv[1]
print(json.dumps({"hookSpecificOutput": {
    "hookEventName": "PostToolUse",
    "additionalContext": f"Prettier formatting differs in {fp} — run: npm run format",
}}))
PY
fi

exit 0
