"""
============================================================
VEHISAFE AI SERVICE — SMOKE TEST
============================================================
Runs against a locally running service on 127.0.0.1:5000.
    python tests/test_health.py
============================================================
"""

import sys
import requests
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from config import Config

BASE = f"http://{Config.HOST}:{Config.PORT}"


def main() -> int:
    print(f"→ Testing {BASE}/api/health")
    try:
        r = requests.get(f"{BASE}/api/health", timeout=5)
    except requests.RequestException as e:
        print(f"❌ Could not reach AI service: {e}")
        return 1

    if r.status_code != 200:
        print(f"❌ Health returned {r.status_code}: {r.text}")
        return 1

    data = r.json()
    if not data.get("success"):
        print(f"❌ Health returned success=false: {data}")
        return 1

    print(f"✅ Health OK: service={data['service']} v{data['version']}")

    # ---- Auth check: missing API key on a detection endpoint ----
    print(f"\n→ Testing unauthenticated call to /api/detect/seatbelt")
    r = requests.post(f"{BASE}/api/detect/seatbelt", json={}, timeout=5)
    if r.status_code != 401:
        print(f"❌ Expected 401 without API key, got {r.status_code}")
        return 1
    print("✅ Auth required (401 without key)")

    # ---- Auth check: with API key but bad body ----
    print(f"\n→ Testing authenticated call with missing body")
    headers = {"X-API-Key": Config.API_KEY}
    r = requests.post(f"{BASE}/api/detect/seatbelt", json={}, headers=headers, timeout=5)
    if r.status_code != 400:
        print(f"❌ Expected 400 for bad body, got {r.status_code}: {r.text}")
        return 1
    print("✅ Bad body rejected (400)")

    # ---- Valid stub call ----
    print(f"\n→ Testing authenticated stub call")
    payload = {
        "driver_id": 1,
        "vehicle_id": 1,
        "trip_id": 1,
        "image_base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
    }
    r = requests.post(f"{BASE}/api/detect/seatbelt", json=payload, headers=headers, timeout=5)
    if r.status_code != 200:
        print(f"❌ Stub call failed: {r.status_code}: {r.text}")
        return 1

    body = r.json()
    if not body.get("success"):
        print(f"❌ Stub returned success=false: {body}")
        return 1

    print(f"✅ Stub detection OK: {body['detections'][0]['type']} → {body['detections'][0]['status']}")

    print("\n🎉 All smoke tests passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())