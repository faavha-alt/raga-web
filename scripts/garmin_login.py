#!/usr/bin/env python3
"""Log in to Garmin Connect and cache an OAuth token, driven by the web UI.

Reads a single JSON object from stdin:
    {"email": "...", "password": "...", "mfa_code": "123456" | null}

Prints exactly one JSON object to stdout and exits 0, regardless of outcome:
    {"status": "ok"}
    {"status": "mfa_required"}
    {"status": "error", "message": "..."}

The password is only ever held in memory for the duration of this process
and is never written to disk or logged. Once login() succeeds, only the
resulting OAuth token is persisted (at ~/.garmin_tokens), so this script
does not need to run again until that token is revoked or expires.
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

import garminconnect

TOKEN_STORE = str(Path.home() / ".garmin_tokens")


class MfaRequired(Exception):
    pass


def main() -> None:
    payload = json.loads(sys.stdin.read())
    email = payload.get("email")
    password = payload.get("password")
    mfa_code = payload.get("mfa_code") or None

    def prompt_mfa() -> str:
        if mfa_code:
            return mfa_code
        raise MfaRequired()

    try:
        client = garminconnect.Garmin(email=email, password=password, prompt_mfa=prompt_mfa)
        client.login(tokenstore=TOKEN_STORE)
        print(json.dumps({"status": "ok"}))
    except MfaRequired:
        print(json.dumps({"status": "mfa_required"}))
    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))


if __name__ == "__main__":
    main()
