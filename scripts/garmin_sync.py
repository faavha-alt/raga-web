#!/usr/bin/env python3
"""Pull health data from Garmin Connect and print it as JSON to stdout.

Usage:
    python3 garmin_sync.py --days 3            # last 3 days, print JSON to stdout
    python3 garmin_sync.py --days 3 --out f.json

Window:
    The window always ends `--offset` days before today and spans `--days` days,
    i.e. [today - offset - days + 1 .. today - offset]. `--offset 0` (default) is
    the ordinary recent sync; a larger offset shifts the whole window further
    into the past. Backfill calls this script repeatedly with chunk-sized
    --days and an increasing --offset instead of one huge --days run.

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

DEFAULT_TOKEN_STORE = str(Path.home() / ".garmin_tokens")


def login(token_store: str | None = None) -> garminconnect.Garmin:
    token_store = token_store or os.environ.get("GARMIN_TOKEN_STORE") or DEFAULT_TOKEN_STORE
    token_dir = Path(token_store)
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
        client.login(tokenstore=str(token_dir))
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

    token_dir.mkdir(parents=True, exist_ok=True)
    client = garminconnect.Garmin(email=email, password=password, prompt_mfa=prompt_mfa)
    client.login(tokenstore=str(token_dir))
    return client


def safe(fn, *args, label: str = "", **kwargs):
    try:
        return fn(*args, **kwargs)
    except Exception as e:
        logger.warning("Failed to fetch %s: %s", label or fn.__name__, e)
        return None


MAX_ACTIVITY_DETAILS = 15  # cap per-activity time-series calls per run


def collect(client: garminconnect.Garmin, days: int, offset: int = 0) -> dict:
    today = date.today()
    daily: list[dict] = []

    # Jendela mundur `offset` hari, lalu ambil `days` hari ke belakang dari situ.
    for i in range(days):
        d = today - timedelta(days=offset + i)
        cdate = d.isoformat()

        entry = {
            "date": cdate,
            "stats": safe(client.get_stats, cdate, label=f"stats {cdate}"),
            "sleep": safe(client.get_sleep_data, cdate, label=f"sleep {cdate}"),
            "hrv": safe(client.get_hrv_data, cdate, label=f"hrv {cdate}"),
            "heart_rate": safe(client.get_heart_rates, cdate, label=f"heart_rate {cdate}"),
            "stress": safe(client.get_stress_data, cdate, label=f"stress {cdate}"),
            "spo2": safe(client.get_spo2_data, cdate, label=f"spo2 {cdate}"),
            "max_metrics": safe(client.get_max_metrics, cdate, label=f"max_metrics {cdate}"),
            "respiration": safe(client.get_respiration_data, cdate, label=f"respiration {cdate}"),
            "training_readiness": safe(
                client.get_training_readiness, cdate, label=f"training_readiness {cdate}"
            ),
        }
        daily.append(entry)

    start = (today - timedelta(days=offset + days - 1)).isoformat()
    end = (today - timedelta(days=offset)).isoformat()

    body_composition = safe(
        client.get_body_composition, start, end, label="body_composition"
    )
    body_battery = safe(client.get_body_battery, start, end, label="body_battery")
    activities = safe(
        client.get_activities_by_date, start, end, label="activities"
    ) or []
    personal_records = safe(client.get_personal_record, label="personal_records")

    for activity in activities[:MAX_ACTIVITY_DETAILS]:
        activity_id = activity.get("activityId")
        if activity_id is None:
            continue
        activity["details"] = safe(
            client.get_activity_details, str(activity_id), label=f"activity_details {activity_id}"
        )
        activity["laps"] = safe(
            client.get_activity_splits, str(activity_id), label=f"activity_splits {activity_id}"
        )

    return {
        "generated_at": date.today().isoformat(),
        "range": {"start": start, "end": end},
        "daily": daily,
        "body_composition": body_composition,
        "body_battery": body_battery,
        "activities": activities,
        "personal_records": personal_records,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--days", type=int, default=3, help="How many days back to sync (default 3)")
    parser.add_argument("--offset", type=int, default=0, help="Shift the whole window N days into the past (default 0)")
    parser.add_argument("--out", type=str, default=None, help="Write JSON to this file instead of stdout")
    parser.add_argument("--token-store", type=str, default=None, help="Path to directory where OAuth token is stored")
    args = parser.parse_args()

    client = login(token_store=args.token_store)
    payload = collect(client, args.days, args.offset)

    output = json.dumps(payload, default=str)
    if args.out:
        Path(args.out).write_text(output)
        logger.info("Wrote %s", args.out)
    else:
        print(output)


if __name__ == "__main__":
    main()
