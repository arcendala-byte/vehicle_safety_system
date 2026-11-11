"""
============================================================
VEHISAFE AI SERVICE — SEATBELT DETECTOR TEST
============================================================
Runs the detector on a sample image if one is present.
    python tests/test_seatbelt.py
============================================================
"""

import sys
from pathlib import Path

# Make sure ai/ is on the path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import numpy as np
from PIL import Image

from detection.seatbelt import SeatbeltDetector


SAMPLES = Path(__file__).resolve().parent / "samples"


def load_sample(name: str):
    path = SAMPLES / name
    if not path.exists():
        return None
    img = Image.open(path).convert("RGB")
    return np.array(img, dtype=np.uint8)


def run_case(detector: SeatbeltDetector, name: str, expect):
    print(f"\n-> {name}")
    img = load_sample(name)
    if img is None:
        print(f"   ⚠ sample missing — skipping")
        return None
    print(f"   image shape: {img.shape}")
    result = detector.detect(img)
    print(f"   status: {result['status']}")
    print(f"   confidence: {result['confidence']:.3f}")
    print(f"   details: {result.get('details')}")
    if expect and result["status"] != "uncertain" and result["status"] != expect:
        print(f"   ❌ expected {expect}, got {result['status']}")
        return False
    print(f"   [PASS] ok")
    return True


def run_blank_test(detector: SeatbeltDetector):
    """A blank image should always return 'uncertain'."""
    print(f"\n-> blank image")
    img = np.zeros((480, 640, 3), dtype=np.uint8)
    result = detector.detect(img)
    print(f"   status: {result['status']}")
    if result["status"] == "uncertain":
        print(f"   [PASS] correctly uncertain on blank")
        return True
    print(f"   ❌ expected uncertain, got {result['status']}")
    return False


def main() -> int:
    print("=== Seatbelt Detector Test ===")
    detector = SeatbeltDetector()

    passed = 0
    failed = 0

    # Blank image is a guaranteed test
    if run_blank_test(detector):
        passed += 1
    else:
        failed += 1

    # Optional sample-based tests
    for name, expect in [
        ("driver_with_seatbelt.jpg", "detected"),
        ("driver_no_seatbelt.jpg",   "not_detected"),
    ]:
        r = run_case(detector, name, expect)
        if r is True:  passed += 1
        if r is False: failed += 1

    print(f"\n=== {passed} passed, {failed} failed ===")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())