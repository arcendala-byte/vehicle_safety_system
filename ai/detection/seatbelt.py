"""
============================================================
VEHISAFE AI SERVICE - SEATBELT DETECTOR
============================================================
STATUS: PROTOTYPE - Heuristic-based, false positives possible.

Uses YOLO pretrained (COCO) to detect persons, then applies
a heuristic on the driver's torso to estimate seatbelt presence.

KNOWN LIMITATIONS:
  - Tuned on one seatbelt-present sample.
  - False positives on diagonal clothing, shadows, or belts.
  - Production system should replace with a custom-trained
    YOLOv8 model (drop .pt file in ai/models/seatbelt.pt).
============================================================
"""

from typing import Any, Dict, List
import numpy as np
import cv2

from config import Config
from detection.base import BaseDetector
from utils.logger import get_logger


class SeatbeltDetector(BaseDetector):
    NAME = "seatbelt"

    def __init__(self) -> None:
        super().__init__()
        self.logger = get_logger("detector.seatbelt")
        self._model = None
        self._model_path = Config.MODEL_DIR / Config.YOLO_MODEL
        self._model_loaded = False

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
            self.logger.info("Seatbelt model ready.")
        except Exception as e:
            self.logger.exception("Failed to load YOLO model: %s", e)
            self._model = None

    def detect(self, image: np.ndarray) -> Dict[str, Any]:
        if image is None or not isinstance(image, np.ndarray) or image.size == 0:
            return self._uncertain("empty image")

        self._ensure_model()
        if self._model is None:
            return self._uncertain("model not loaded")

        try:
            results = self._model.predict(
                source=image,
                conf=0.25,
                iou=0.45,
                verbose=False,
            )
            persons = self._extract_persons(results)

            if not persons:
                return {
                    "status": "uncertain",
                    "confidence": 0.0,
                    "details": {
                        "reason": "no_person_detected",
                        "persons_found": 0,
                    },
                }

            driver = max(persons, key=lambda p: p["area"])
            score, heuristic_details = self._analyze_torso(image, driver)

            if score >= self.confidence_threshold:
                status = "detected"
            elif score <= (1.0 - self.confidence_threshold):
                status = "not_detected"
            else:
                status = "uncertain"

            return {
                "status": status,
                "confidence": self._clamp_conf(score),
                "details": {
                    **heuristic_details,
                    "person_box": driver["box"],
                    "person_conf": driver["conf"],
                },
            }

        except Exception as e:
            self.logger.exception("Seatbelt detection failed: %s", e)
            return self._uncertain(f"detection_error: {type(e).__name__}: {e}")

    def _extract_persons(self, results) -> List[Dict[str, Any]]:
        persons: List[Dict[str, Any]] = []
        for r in results:
            boxes = getattr(r, "boxes", None)
            if boxes is None:
                continue
            for b in boxes:
                cls_id = int(b.cls[0]) if b.cls is not None else -1
                if cls_id != 0:
                    continue
                xyxy = b.xyxy[0]
                x1 = int(xyxy[0].item()) if hasattr(xyxy[0], 'item') else int(xyxy[0])
                y1 = int(xyxy[1].item()) if hasattr(xyxy[1], 'item') else int(xyxy[1])
                x2 = int(xyxy[2].item()) if hasattr(xyxy[2], 'item') else int(xyxy[2])
                y2 = int(xyxy[3].item()) if hasattr(xyxy[3], 'item') else int(xyxy[3])
                conf = float(b.conf[0].item()) if b.conf is not None else 0.0
                persons.append({
                    "box": [x1, y1, x2, y2],
                    "area": (x2 - x1) * (y2 - y1),
                    "conf": conf,
                })
        return persons

    def _analyze_torso(self, image: np.ndarray, person: Dict[str, Any]):
        h_img, w_img = image.shape[:2]
        x1, y1, x2, y2 = person["box"]

        box_h = y2 - y1
        box_w = x2 - x1

        torso_y1 = y1 + int(box_h * 0.28)
        torso_y2 = y1 + int(box_h * 0.70)
        torso_x1 = x1 + int(box_w * 0.25)
        torso_x2 = x2 - int(box_w * 0.25)

        torso_y1 = max(0, min(h_img - 1, torso_y1))
        torso_y2 = max(0, min(h_img, torso_y2))
        torso_x1 = max(0, min(w_img - 1, torso_x1))
        torso_x2 = max(0, min(w_img, torso_x2))

        if torso_x2 <= torso_x1 or torso_y2 <= torso_y1:
            return 0.0, {"reason": "invalid_torso_box"}

        torso = image[torso_y1:torso_y2, torso_x1:torso_x2]
        if torso.size == 0:
            return 0.0, {"reason": "empty_torso"}

        gray = cv2.cvtColor(torso, cv2.COLOR_RGB2GRAY)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        gray = clahe.apply(gray)

        edges = cv2.Canny(gray, 40, 120)
        edge_density = float(np.count_nonzero(edges)) / float(edges.size)

        _, dark_mask = cv2.threshold(
            gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU
        )
        dark_ratio = float(np.count_nonzero(dark_mask)) / float(dark_mask.size)

        # --- Hough lines (guarded) ---
        diagonal_lines = 0
        long_diagonal_lines = 0
        try:
            lines = cv2.HoughLinesP(
                edges,
                rho=1,
                theta=np.pi / 180,
                threshold=20,
                minLineLength=int(torso.shape[1] * 0.30),
                maxLineGap=int(torso.shape[0] * 0.15),
            )
            if lines is not None:
                # Flatten to list of 4-tuples regardless of input shape
                for ln in lines.reshape(-1, 4):
                    lx1 = int(ln[0])
                    ly1 = int(ln[1])
                    lx2 = int(ln[2])
                    ly2 = int(ln[3])
                    dx = abs(lx2 - lx1)
                    dy = abs(ly2 - ly1)
                    if dx == 0 and dy == 0:
                        continue
                    length = float(np.hypot(dx, dy))
                    slope = dy / max(1.0, dx)
                    if 0.3 <= slope <= 3.0:
                        diagonal_lines += 1
                        if length >= (torso.shape[1] * 0.5):
                            long_diagonal_lines += 1
        except Exception as e:
            self.logger.warning("Hough line detection failed: %s", e)

        score = 0.0
        score += min(1.0, diagonal_lines / 3.0) * 0.50
        score += min(1.0, long_diagonal_lines / 1.0) * 0.20
        score += min(1.0, edge_density * 3.5) * 0.20
        score += min(1.0, dark_ratio * 1.5) * 0.10
        score = max(0.0, min(1.0, score))

        details = {
            "edge_density":        round(edge_density, 4),
            "dark_ratio":          round(dark_ratio, 4),
            "diagonal_lines":      diagonal_lines,
            "long_diagonal_lines": long_diagonal_lines,
            "torso_box":           [int(torso_x1), int(torso_y1),
                                    int(torso_x2), int(torso_y2)],
        }
        return score, details
