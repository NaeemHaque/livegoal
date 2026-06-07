#!/usr/bin/env python3
"""
PreToolUse hook — Write/Edit path guard for the socplay Laravel app.

Forces a confirmation prompt when a write would land OUTSIDE the project root
(temp dirs are allowed for scratch files). Writes inside the project defer to
the normal permission flow.

Reads the hook payload from stdin. Fails open on any parse error.
"""

import json
import os
import sys

TEMP_ROOTS = [
    "/tmp/",
    "/private/tmp/",
    "/var/folders/",
    "/private/var/folders/",
]


def main() -> None:
    try:
        payload = json.load(sys.stdin)
    except Exception:
        sys.exit(0)  # fail open

    file_path = payload.get("tool_input", {}).get("file_path", "")
    if not file_path:
        sys.exit(0)

    project = os.environ.get("CLAUDE_PROJECT_DIR") or payload.get("cwd") or ""
    resolved = os.path.realpath(file_path)

    allowed = []
    if project:
        allowed.append(os.path.realpath(project) + os.sep)
    allowed.extend(TEMP_ROOTS)

    for root in allowed:
        if resolved.startswith(root):
            sys.exit(0)  # inside allowed root -> normal flow

    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "ask",
            "permissionDecisionReason": f"write_guard: path outside project root -> {resolved}",
        }
    }))
    sys.exit(0)


if __name__ == "__main__":
    main()
