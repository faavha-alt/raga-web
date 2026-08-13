#!/usr/bin/env python3
"""Pull health data from Garmin Connect and print it as JSON to stdout.

Usage:
    python3 garmin_sync.py --days 3            # last 3 days, print JSON to stdout
    python3 garmin_sync.py --days 3 --out f.json

Auth:
    First run needs GARMIN_EMAIL / GARMIN_PASSWORD env vars (or interactive
    prompt) so it can log in and cache an OAuth token at ~/.garmin_tokens.
    Every run after that reuses the cached token — no password needed, and
    the credentials are only ever provided by you, on the server, not sent
    anywhere else.

This script only talks to Garmin and prints data. It knows nothing about
the RAGA database — `php artisan garmin:import` (in this same repo) reads
the JSON this prints and writes it into the app's tables.
"""
from __future__ import annotations

import argparse
import getpass
import json
import logging
import os
import sys
from datetime import date, timedelta
from pathlib import Path

import garminconnect

logging.basicConfig(level=logging.WARNING, format="%(levelname)s %(message)s")
logger = logging.getLogger("garmin_sync")

TOKEN_STORE = str(Path.home() / ".garmin_tokens")


def login() -> garminconnect.Garmin:
    email = os.environ.get("GARMIN_EMAIL")
    password = os.environ.get("GARMIN_PASSWORD")

    def prompt_mfa() -> str:
        return input("Garmin MFA code: ").strip()

    client = garminconnect.Garmin(
        email=email,
        password=password,
        prompt_mfa=prompt_mfa,
    )

    try:
        client.login(tokenstore=TOKEN_STORE)
        return client
    except FileNotFoundError:
        # No cached token yet — need credentials for a first-time login.
        pass
    except Exception as e:
        logger.warning("Cached login failed (%s), falling back to fresh login", e)

    if not email:
        email = input("Garmin email: ").strip()
    if not password:
        password = getpass.getpass("Garmin password: ")

    client = garminconnect.Garmin(email=email, password=password, prompt_mfa=prompt_mfa)
    client.login(tokenstore=TOKEN_STORE)
    return client


def safe(fn, *args, label: str = "", **kwargs):
    try:
        return fn(*args, **kwargs)
    except Exception as e:
        logger.warning("Failed to fetch %s: %s", label or fn.__name__, e)
        return None


def collect(client: garminconnect.Garmin, days: int) -> dict:
    today = date.today()
    daily: list[dict] = []

    for offset in range(days):
        d = today - timedelta(days=offset)
        cdate = d.isoformat()

        entry = {
            "date": cdate,
            "stats": safe(client.get_stats, cdate, label=f"stats {cdate}"),
            "sleep": safe(client.get_sleep_data, cdate, label=f"sleep {cdate}"),
            "hrv": safe(client.get_hrv_data, cdate, label=f"hrv {cdate}"),
            "heart_rate": safe(client.get_heart_rates, cdate, label=f"heart_rate {cdate}"),
            "stress": safe(client.get_stress_data, cdate, label=f"stress {cdate}"),
            "spo2": safe(client.get_spo2_data, cdate, label=f"spo2 {cdate}"),
        }
        daily.append(entry)

    start = (today - timedelta(days=days - 1)).isoformat()
    end = today.isoformat()

    body_composition = safe(
        client.get_body_composition, start, end, label="body_composition"
    )
    body_battery = safe(client.get_body_battery, start, end, label="body_battery")
    activities = safe(
        client.get_activities_by_date, start, end, label="activities"
    )

    return {
        "generated_at": date.today().isoformat(),
        "range": {"start": start, "end": end},
        "daily": daily,
        "body_composition": body_composition,
        "body_battery": body_battery,
        "activities": activities or [],
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--days", type=int, default=3, help="How many days back to sync (default 3)")
    parser.add_argument("--out", type=str, default=None, help="Write JSON to this file instead of stdout")
    args = parser.parse_args()

    client = login()
    payload = collect(client, args.days)

    output = json.dumps(payload, default=str)
    if args.out:
        Path(args.out).write_text(output)
        logger.info("Wrote %s", args.out)
    else:
        print(output)


if __name__ == "__main__":
    main()
