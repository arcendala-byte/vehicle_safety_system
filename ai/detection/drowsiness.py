"""
============================================================
VEHISAFE AI SERVICE - DROWSINESS DETECTOR
============================================================
Uses MediaPipe Tasks API (FaceLandmarker) to compute EAR
(eye openness) and MAR (mouth openness). Returns per-frame
state:
  - alert
  - eyes_closed
  - yawning
  - head_tilt
  - uncertain (no face)

NOTE: MediaPipe 0.10.31+ removed the legacy mp.solutions API.
We use the Tasks API (mp.tasks.vision.FaceLandmarker) which
requires a .task model file.
============================================================
"""

from typing import Any, Dict
import numpy as np
import cv2

from config import Config
from detection.base import BaseDetector
from detection import landmark_utils as lm
from utils.logger import get_logger


class DrowsinessDetector(BaseDetector):
    NAME = "drowsiness"

    EAR_CLOSED_THRESHOLD = 0.19
    MAR_YAWN_THRESHOLD   = 0.55
    HEAD_TILT_THRESHOLD  = 25.0

    def __init__(self) -> None:
        super().__init__()
        self.logger = get_logger("detector.drowsiness")
        self._landmarker = None
        self._mp = None
        self._model_path = Config.MODEL_DIR / "face_landmarker.task"

    def _ensure_model(self) -> None:
        if self._landmarker is not None:
            return

        try:
            import mediapipe as mp
            from mediapipe.tasks import python as mp_python
            from mediapipe.tasks.python import vision

            self._mp = mp

            if not self._model_path.exists():
                self.logger.error("Face landmarker model not found: %s", self._model_path)
                return

            base_options = mp_python.BaseOptions(model_asset_path=str(self._model_path))
            options = vision.FaceLandmarkerOptions(
                base_options=base_options,
                running_mode=vision.RunningMode.IMAGE,
                num_faces=1,
                min_face_detection_confidence=0.5,
            )
            self._landmarker = vision.FaceLandmarker.create_from_options(options)
            self.logger.info("MediaPipe FaceLandmarker (Tasks API) ready.")

        except Exception as e:
            self.logger.exception("Failed to load MediaPipe Tasks: %s", e)
            self._landmarker = None

    def detect(self, image: np.ndarray) -> Dict[str, Any]:
        if image is None or not isinstance(image, np.ndarray) or image.size == 0:
            return self._uncertain("empty image")

        self._ensure_model()
        if self._landmarker is None:
            return self._uncertain("mediapipe not available")

        try:
            rgb = cv2.cvtColor(image, cv2.COLOR_RGB2BGR)
            mp_image = self._mp.Image(
                image_format=self._mp.ImageFormat.SRGB,
                data=rgb,
            )
            result = self._landmarker.detect(mp_image)

            if not result.face_landmarks:
                return {
                    "status": "uncertain",
                    "confidence": 0.0,
                    "details": {"reason": "no_face_detected"},
                }

            face = result.face_landmarks[0]
            h, w = image.shape[:2]

            def pt(idx: int):
                p = face[idx]
                return (p.x * w, p.y * h)

            left_eye_pts  = [pt(i) for i in lm.LEFT_EYE_INDICES]
            right_eye_pts = [pt(i) for i in lm.RIGHT_EYE_INDICES]
            mouth_pts     = [pt(i) for i in lm.MOUTH_INDICES]

            ear_left  = lm.eye_aspect_ratio(left_eye_pts)
            ear_right = lm.eye_aspect_ratio(right_eye_pts)
            ear_avg   = (ear_left + ear_right) / 2.0

            mar = lm.mouth_aspect_ratio(mouth_pts)
            tilt = lm.head_tilt_degrees(
                pt(lm.NOSE_TIP_INDEX),
                pt(lm.LEFT_CHEEK_INDEX),
                pt(lm.RIGHT_CHEEK_INDEX),
            )

            states_triggered = []
            if ear_left < self.EAR_CLOSED_THRESHOLD and ear_right < self.EAR_CLOSED_THRESHOLD:
                states_triggered.append("eyes_closed")
            if mar > self.MAR_YAWN_THRESHOLD:
                states_triggered.append("yawning")
            if abs(tilt) > self.HEAD_TILT_THRESHOLD:
                states_triggered.append("head_tilt")

            if not states_triggered:
                status = "alert"
                conf = min(1.0, max(0.0, (ear_avg - self.EAR_CLOSED_THRESHOLD) / 0.15))
            else:
                status = states_triggered[0]
                if "eyes_closed" in states_triggered:
                    conf = min(1.0, max(0.0, (self.EAR_CLOSED_THRESHOLD - ear_avg) / 0.15))
                elif "yawning" in states_triggered:
                    conf = min(1.0, max(0.0, (mar - self.MAR_YAWN_THRESHOLD) / 0.30))
                else:
                    conf = min(1.0, abs(tilt) / 90.0)

            return {
                "status": status,
                "confidence": self._clamp_conf(conf),
                "details": {
                    "ear_left":  round(ear_left,  4),
                    "ear_right": round(ear_right, 4),
                    "ear_avg":   round(ear_avg,   4),
                    "mar":       round(mar,       4),
                    "head_tilt_deg": round(tilt,  2),
                    "thresholds": {
                        "ear_closed": self.EAR_CLOSED_THRESHOLD,
                        "mar_yawn":   self.MAR_YAWN_THRESHOLD,
                        "head_tilt":  self.HEAD_TILT_THRESHOLD,
                    },
                    "triggered": states_triggered,
                },
            }

        except Exception as e:
            self.logger.exception("Drowsiness detection failed: %s", e)
            return self._uncertain(f"detection_error: {type(e).__name__}")