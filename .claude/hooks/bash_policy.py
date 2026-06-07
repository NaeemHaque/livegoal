#!/usr/bin/env python3
"""
PreToolUse hook — Bash command policy gate for the socplay Laravel app.

Adds a safety layer on top of normal permissions:
  - deny  : blocked outright (truly destructive, irreversible)
  - ask   : forces a confirmation prompt (sensitive)
  - (default): no decision emitted -> normal permission flow applies

Reads the hook payload from stdin (Claude Code does NOT set CLAUDE_TOOL_INPUT).
Fails open: any parse error exits 0 so the agent is never hard-blocked by a bug.
"""

import json
import re
import sys

# Block immediately — no recovery possible.
PATTERNS_DENY = [
    r"\brm\s+-rf\s+/\s*$",                              # rm -rf filesystem root
    r"\brm\s+-rf\s+/[^/\s]+\s*$",                       # rm -rf /toplevel
    r"\bgit\s+push\b.*--force\b.*\b(main|master)\b",    # force push to main/master
    r"\bgit\s+push\b.*\b(main|master)\b.*--force\b",
    r"\bDROP\s+DATABASE\b",                             # drop entire database
]

# Require explicit user confirmation.
PATTERNS_ASK = [
    # Git — public-facing or destructive
    r"\bgit\s+push\b",
    r"\bgit\s+reset\s+--hard\b",
    r"\bgit\s+clean\s+-[a-z]*f",
    r"\bgit\s+branch\s+-D\b",
    r"\bgit\s+checkout\s+--\s",        # discard file changes (not branch switch)
    r"\bgit\s+restore\b",
    r"\bgit\s+rebase\b",
    r"\bgit\s+stash\s+drop\b",

    # File deletions
    r"\brm\s+-rf?\b",
    r"\brm\s+.*\.(php|vue|ts|js|json|neon)\b",
    r"\btruncate\b",

    # Dependency / lockfile changes
    r"\bnpm\s+(install|i|ci)\b",
    r"\bnpm\s+publish\b",
    r"\bcomposer\s+(update|require|remove)\b",

    # Laravel — destructive DB / app state
    r"\bartisan\s+migrate:(fresh|refresh|reset)\b",
    r"\bartisan\s+db:wipe\b",
    r"\bartisan\s+migrate\b.*--force\b",

    # Privilege escalation
    r"\bsudo\b",
    r"\bchmod\s+777\b",
    r"\bchown\b",

    # Remote code execution risk
    r"\bcurl\b.*\|\s*(ba)?sh\b",
    r"\bwget\b.*\|\s*(ba)?sh\b",

    # Process management
    r"\bkill\s+-9\b",
    r"\bpkill\b",

    # System file writes
    r"\b>\s*/etc/\b",
]


def emit(decision: str, reason: str) -> None:
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": decision,
            "permissionDecisionReason": f"bash_policy: {reason}",
        }
    }))
    sys.exit(0)


def main() -> None:
    try:
        payload = json.load(sys.stdin)
    except Exception:
        sys.exit(0)  # fail open

    command = payload.get("tool_input", {}).get("command", "")
    if not command:
        sys.exit(0)

    for pattern in PATTERNS_DENY:
        if re.search(pattern, command, re.IGNORECASE):
            emit("deny", f"destructive command blocked (matched /{pattern}/)")

    for pattern in PATTERNS_ASK:
        if re.search(pattern, command, re.IGNORECASE):
            emit("ask", f"sensitive command — confirm (matched /{pattern}/)")

    sys.exit(0)  # default: defer to normal permission flow


if __name__ == "__main__":
    main()
