"""
============================================================
VEHISAFE AI SERVICE — BASE DETECTOR
============================================================
Defines the contract every detector must follow.

Phases 10-13 will subclass this:
    - seatbelt.py     → SeatbeltDetector
    - phone.py        → PhoneDetector
    - drowsiness.py   → DrowsinessDetector
    - distraction.py  → DistractionDetector
============================================================
"""

from abc import ABC, abstractmethod
from typing import Any, Dict

import numpy as np

from config import Config
from utils.logger import get_logger


class BaseDetector(ABC):
    """
    Every detector takes an RGB numpy image and returns a structured dict.

    Contract:
        result = {
            "status": str,        # short label, e.g. "detected", "not_detected", "uncertain"
            "confidence": float,  # 0.0 – 1.0
            "details": dict,      # optional extra fields (bounding boxes, landmarks, ...)
            "raw": Any,           # optional opaque object for downstream use
        }
    """

    NAME = "base"
    VERSION = Config.SERVICE_VERSION

    def __init__(self) -> None:
        self.logger = get_logger(f"detector.{self.NAME}")
        self.confidence_threshold = Config.CONFIDENCE_THRESHOLD
        self.logger.info("Initialized %s v%s", self.NAME, self.VERSION)

    @abstractmethod
    def detect(self, image: np.ndarray) -> Dict[str, Any]:
        """
        Run detection on an RGB numpy image.
        MUST NOT raise — return an 'uncertain' result on internal errors.
        """
        raise NotImplementedError

    # ------------------------------------------------------------------
    # Shared helpers for subclasses
    # ------------------------------------------------------------------
    def _uncertain(self, reason: str = "uncertain") -> Dict[str, Any]:
        return {
            "status": "uncertain",
            "confidence": 0.0,
            "details": {"reason": reason},
        }

    def _clamp_conf(self, value: float) -> float:
        return max(0.0, min(1.0, float(value)))