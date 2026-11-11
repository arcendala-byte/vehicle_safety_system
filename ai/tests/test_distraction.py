"""
============================================================
VEHISAFE AI SERVICE - DISTRACTION DETECTOR TEST
============================================================
    python tests/test_distraction.py
============================================================
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import numpy as np
from PIL import Image

from detection.distraction import DistractionDetector


SAMPLES = Path(__file__).resolve().parent / "samples"


def load_sample(name: str):
    path = SAMPLES / name
    if not path.exists():
        return None
    img = Image.open(path).convert("RGB")
    return np.array(img, dtype=np.uint8)


def run_case(detector, name, expect):
    print(f"\n-> {name}")
    img = load_sample(name)
    if img is None:
        print(f"   [skip] sample missing")
        return None
    print(f"   image shape: {img.shape}")
    result = detector.detect(img)
    print(f"   status: {result['status']}")
    print(f"   confidence: {result['confidence']:.3f}")
    details = result.get("details", {})
    print(f"   yaw: {details.get('yaw_ratio')}, pitch: {details.get('pitch_ratio')}")
    print(f"   states: {details.get('states_triggered')}")
    if expect:
        if result["status"] not in (expect, "uncertain"):
            print(f"   FAIL: expected {expect}, got {result['status']}")
            return False
    print(f"   OK")
    return True


def run_blank_test(detector):
    print(f"\n-> blank image")
    img = np.zeros((480, 640, 3), dtype=np.uint8)
    result = detector.detect(img)
    print(f"   status: {result['status']}")
    if result["status"] == "uncertain":
        print(f"   OK correctly uncertain (no face)")
        return True
    print(f"   FAIL: expected uncertain, got {result['status']}")
    return False


def main():
    print("=== Distraction Detector Test ===")
    detector = DistractionDetector()

    passed = 0
    failed = 0

    if run_blank_test(detector):
        passed += 1
    else:
        failed += 1

    for name, expect in [
        ("driver_alert.jpg",          "attentive"),
        ("driver_looking_left.jpg",   "distracted"),
        ("driver_looking_down.jpg",   "distracted"),
    ]:
        r = run_case(detector, name, expect)
        if r is True:  passed += 1
        if r is False: failed += 1

    print(f"\n=== {passed} passed, {failed} failed ===")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
