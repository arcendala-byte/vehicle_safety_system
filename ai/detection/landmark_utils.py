"""
============================================================
VEHISAFE AI SERVICE - FACIAL LANDMARK UTILITIES
============================================================
Helper functions to compute Eye Aspect Ratio (EAR) and
Mouth Aspect Ratio (MAR) from MediaPipe Face Mesh landmarks.
============================================================
"""

import math
from typing import List, Sequence


# ---- MediaPipe Face Mesh index groups ----

LEFT_EYE_INDICES  = [362, 385, 387, 263, 373, 380]
RIGHT_EYE_INDICES = [33,  160, 158, 133, 153, 144]

MOUTH_INDICES = [61, 291, 39, 181, 0, 17, 269, 405]

NOSE_TIP_INDEX = 1

LEFT_CHEEK_INDEX  = 234
RIGHT_CHEEK_INDEX = 454


def distance(p1: Sequence[float], p2: Sequence[float]) -> float:
    """Euclidean distance between 2D points."""
    return math.hypot(p1[0] - p2[0], p1[1] - p2[1])


def eye_aspect_ratio(eye_points: List[Sequence[float]]) -> float:
    """
    Compute Eye Aspect Ratio (EAR).
    Lower EAR = eyes more closed.
    """
    if len(eye_points) != 6:
        return 0.0

    p1, p2, p3, p4, p5, p6 = eye_points
    v1 = distance(p2, p6)
    v2 = distance(p3, p5)
    h  = distance(p1, p4)
    if h == 0:
        return 0.0
    return (v1 + v2) / (2.0 * h)


def mouth_aspect_ratio(mouth_points: List[Sequence[float]]) -> float:
    """
    Compute Mouth Aspect Ratio (MAR).
    Higher MAR = mouth more open (yawning).
    """
    if len(mouth_points) < 4:
        return 0.0

    left   = mouth_points[0]
    right  = mouth_points[1]
    upper1 = mouth_points[2]
    lower1 = mouth_points[3]

    v = distance(upper1, lower1)
    h = distance(left, right)
    if h == 0:
        return 0.0
    return v / h


def head_tilt_degrees(nose_tip: Sequence[float],
                      left_cheek: Sequence[float],
                      right_cheek: Sequence[float]) -> float:
    """
    Estimate head tilt in degrees (roll).
    0 = level, > 20 or < -20 = drooping head.
    """
    dx = right_cheek[0] - left_cheek[0]
    dy = right_cheek[1] - left_cheek[1]
    if dx == 0 and dy == 0:
        return 0.0
    return math.degrees(math.atan2(dy, dx))
