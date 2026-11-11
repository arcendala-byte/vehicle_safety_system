"""
============================================================
VEHISAFE AI - COMBINED TEST RUNNER
============================================================
Runs all detector tests and prints a summary.
    python tests/test_all.py
============================================================
"""

import subprocess
import sys
from pathlib import Path

TESTS = [
    ("Seatbelt",     "test_seatbelt.py"),
    ("Phone",        "test_phone.py"),
    ("Drowsiness",   "test_drowsiness.py"),
    ("Distraction",  "test_distraction.py"),
]


def main():
    here = Path(__file__).resolve().parent
    results = []

    print("=" * 60)
    print("VEHISAFE AI - FULL TEST SUITE")
    print("=" * 60)

    for name, script in TESTS:
        print()
        print(f">>> Running {name} tests...")
        print("-" * 60)
        try:
            result = subprocess.run(
                [sys.executable, str(here / script)],
                capture_output=True,
                text=True,
                timeout=180,
                encoding="utf-8",
                errors="replace",
            )
            if result.stdout:
                print(result.stdout)
            if result.stderr:
                print(result.stderr)
            passed = result.returncode == 0
            results.append((name, passed))
        except subprocess.TimeoutExpired:
            print(f"TIMEOUT: {name}")
            results.append((name, False))
        except Exception as e:
            print(f"ERROR: {e}")
            results.append((name, False))

    print()
    print("=" * 60)
    print("SUMMARY")
    print("=" * 60)
    passed = sum(1 for _, ok in results if ok)
    total = len(results)
    for name, ok in results:
        icon = "[PASS]" if ok else "[FAIL]"
        print(f"  {icon} {name}")
    print()
    print(f"Total: {passed}/{total} test suites passed")

    return 0 if passed == total else 1


if __name__ == "__main__":
    sys.exit(main())
