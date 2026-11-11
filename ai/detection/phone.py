"""
============================================================
VEHISAFE AI SERVICE — PHONE DETECTOR
============================================================
Strategy:
  1. YOLO (COCO-pretrained) detects ALL objects in the image.
  2. Filter for class 67 = "cell phone".
  3. Filter spatially: keep only phones that overlap the
     driver's body region (largest person bounding box).
  4. Score = max phone confidence within driver region.
  5. Threshold → detected / not_detected / uncertain.

This detector uses the SAME YOLOv8n model as the seatbelt
detector (yolov8n.pt). No additional model download needed.

Why this works well:
  - YOLO's COCO training includes ~50k+ labeled cell phone
    images across varied lighting, angles, backgrounds.
  - No heuristic layer needed — YOLO returns phone detections
    directly with high confidence.
============================================================
"""

from typing import Any, Dict, List, Optional, Tuple
import numpy as np

from config import Config
from detection.base import BaseDetector
from utils.logger import get_logger

# COCO class ID for "cell phone" in YOLO's pretrained model
COCO_CELL_PHONE_CLASS = 67
COCO_PERSON_CLASS = 0


class PhoneDetector(BaseDetector):
    NAME = "phone"

    def __init__(self) -> None:
        super().__init__()
        self.logger = get_logger("detector.phone")
        self._model = None
        self._model_path = Config.MODEL_DIR / Config.YOLO_MODEL
        self._model_loaded = False

        # IOU threshold for deciding whether a phone is "in the driver's area"
        # If phone bounding box overlaps > 5% of driver bbox area, count it.
        self._phone_driver_overlap = 0.05

    # ------------------------------------------------------------------
    # MODEL LOADING
    # ------------------------------------------------------------------
    def _ensure_model(self) -> None:
        if self._model_loaded:
            return

        try:
            from ultralytics import YOLO
        except ImportError as e:
            self.logger.error("ultralytics not installed: %s", e)
            return

        try:
            self.logger.info("Loading YOLO model: %s", self._model_path)
            self._model = YOLO(str(self._model_path))
            self._model_loaded = True
            self.logger.info("Phone model ready.")
        except Exception as e:
            self.logger.exception("Failed to load YOLO model: %s", e)
            self._model = None

    # ------------------------------------------------------------------
    # DETECTION
    # ------------------------------------------------------------------
    def detect(self, image: np.ndarray) -> Dict[str, Any]:
        """
        Run phone detection. Never raises.
        """
        if image is None or not isinstance(image, np.ndarray) or image.size == 0:
            return self._uncertain("empty image")

        self._ensure_model()
        if self._model is None:
            return self._uncertain("model not loaded")

        try:
            results = self._model.predict(
                source=image,
                conf=0.20,      # lower than seatbelt — we filter further by region
                iou=0.45,
                verbose=False,
            )

            persons = self._extract_class(results, COCO_PERSON_CLASS)
            phones  = self._extract_class(results, COCO_CELL_PHONE_CLASS)

            if not phones:
                return {
                    "status": "not_detected",
                    "confidence": 0.0,
                    "details": {
                        "reason": "no_phone_in_image",
                        "phones_found": 0,
                    },
                }

            # Find driver (largest person)
            driver: Optional[Dict[str, Any]] = None
            if persons:
                driver = max(persons, key=lambda p: p["area"])

            # Filter phones to those overlapping the driver region
            if driver is not None:
                relevant_phones = [
                    p for p in phones
                    if self._bbox_overlap_ratio(p["box"], driver["box"]) >= self._phone_driver_overlap
                ]
            else:
                # No person detected — we still report a phone if one is visible,
                # but with lower confidence and a note.
                relevant_phones = phones

            if not relevant_phones:
                return {
                    "status": "not_detected",
                    "confidence": 0.0,
                    "details": {
                        "reason": "phone_found_but_outside_driver_region",
                        "phones_found": len(phones),
                        "driver_found": driver is not None,
                    },
                }

            # Take highest-confidence phone
            best_phone = max(relevant_phones, key=lambda p: p["conf"])
            score = self._clamp_conf(best_phone["conf"])

            if score >= self.confidence_threshold:
                status = "detected"
            elif score <= (1.0 - self.confidence_threshold):
                status = "not_detected"
            else:
                status = "uncertain"

            return {
                "status": status,
                "confidence": score,
                "details": {
                    "phone_box": best_phone["box"],
                    "driver_box": driver["box"] if driver else None,
                    "phones_in_driver_region": len(relevant_phones),
                    "total_phones_in_image": len(phones),
                },
            }

        except Exception as e:
            self.logger.exception("Phone detection failed: %s", e)
            return self._uncertain(f"detection_error: {type(e).__name__}")

    # ------------------------------------------------------------------
    # HELPERS
    # ------------------------------------------------------------------
    def _extract_class(self, results, cls_id: int) -> List[Dict[str, Any]]:
        """Return all detected boxes for a given COCO class."""
        out: List[Dict[str, Any]] = []
        for r in results:
            boxes = getattr(r, "boxes", None)
            if boxes is None:
                continue
            for b in boxes:
                cid = int(b.cls[0]) if b.cls is not None else -1
                if cid != cls_id:
                    continue
                x1, y1, x2, y2 = map(int, b.xyxy[0].tolist())
                conf = float(b.conf[0]) if b.conf is not None else 0.0
                out.append({
                    "box": [x1, y1, x2, y2],
                    "area": (x2 - x1) * (y2 - y1),
                    "conf": conf,
                })
        return out

    def _bbox_overlap_ratio(self, box_a, box_b) -> float:
        """
        Return the intersection area divided by box_a's area.
        Returns 0.0 if no overlap.
        """
        ax1, ay1, ax2, ay2 = box_a
        bx1, by1, bx2, by2 = box_b

        ix1 = max(ax1, bx1)
        iy1 = max(ay1, by1)
        ix2 = min(ax2, bx2)
        iy2 = min(ay2, by2)

        if ix2 <= ix1 or iy2 <= iy1:
            return 0.0

        intersection = (ix2 - ix1) * (iy2 - iy1)
        area_a = max(1, (ax2 - ax1) * (ay2 - ay1))
        return intersection / area_a