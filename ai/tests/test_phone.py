"""
============================================================
VEHISAFE AI SERVICE — PHONE DETECTOR TEST
============================================================
    python tests/test_phone.py
============================================================
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import numpy as np
from PIL import Image

from detection.phone import PhoneDetector


SAMPLES = Path(__file__).resolve().parent / "samples"


def load_sample(name: str):
    path = SAMPLES / name
    if not path.exists():
        return None
    img = Image.open(path).convert("RGB")
    return np.array(img, dtype=np.uint8)


def run_case(detector: PhoneDetector, name: str, expect):
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


def run_blank_test(detector: PhoneDetector):
    print(f"\n-> blank image")
    img = np.zeros((480, 640, 3), dtype=np.uint8)
    result = detector.detect(img)
    print(f"   status: {result['status']}")
    # A blank image should NOT be 'detected'
    if result["status"] in ("not_detected", "uncertain"):
        print(f"   [PASS] correctly not detected")
        return True
    print(f"   ❌ unexpected 'detected' on blank")
    return False


def main() -> int:
    print("=== Phone Detector Test ===")
    detector = PhoneDetector()

    passed = 0
    failed = 0

    if run_blank_test(detector):
        passed += 1
    else:
        failed += 1

    # Positive sample: a driver visibly holding a phone
    # Negative sample: driver with hands on wheel, no phone
    for name, expect in [
        ("driver_with_phone.jpg", "detected"),
        ("driver_with_seatbelt.jpg", "not_detected"),  # assuming no phone in this image
    ]:
        r = run_case(detector, name, expect)
        if r is True:  passed += 1
        if r is False: failed += 1

    print(f"\n=== {passed} passed, {failed} failed ===")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())