"""
============================================================
VEHISAFE AI SERVICE - DISTRACTION DETECTOR
============================================================
Strategy:
  1. MediaPipe FaceLandmarker (Tasks API) detects face.
  2. Compute head yaw (left-right) from eye-nose geometry.
  3. Compute head pitch (up-down) from eye-nose-chin geometry.
  4. Classify as attentive / distracted / uncertain.

States:
  - attentive   : facing forward, within thresholds
  - distracted  : looking away (left, right, up, or down)
  - uncertain   : no face detected

Temporal note:
  A single frame showing a slight head turn is not "distraction".
  Real distraction is sustained head pose away from road for >3s.
  This detector returns per-frame state; the frontend must track
  duration for true distraction detection.
============================================================
"""

from typing import Any, Dict
import numpy as np
import cv2

from config import Config
from detection.base import BaseDetector
from detection import landmark_utils as lm
from utils.logger import get_logger


class DistractionDetector(BaseDetector):
    NAME = "distraction"

    # ---- Thresholds ----
    YAW_RATIO_HIGH = 1.40    # nose-left-eye > 1.6x nose-right-eye -> looking right
    YAW_RATIO_LOW  = 0.60    # nose-left-eye < 0.6x nose-right-eye -> looking left
    PITCH_RATIO_HIGH = 0.68   # looking down (tuned: 0.705 = down)
    PITCH_RATIO_LOW  = 0.20   # looking up

    def __init__(self) -> None:
        super().__init__()
        self.logger = get_logger("detector.distraction")
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
            self.logger.info("MediaPipe FaceLandmarker (Tasks API) ready for distraction.")

        except Exception as e:
            self.logger.exception("Failed to load MediaPipe for distraction: %s", e)
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

            # ---- Landmarks needed ----
            # Eye outer corners
            left_eye_outer  = pt(263)   # outer corner of left eye
            right_eye_outer = pt(33)    # outer corner of right eye
            # Nose tip
            nose_tip = pt(1)
            # Chin
            chin = pt(152)
            # Eye centers (for pitch)
            left_eye_inner = pt(362)
            right_eye_inner = pt(133)

            # ---- Yaw: nose relative to eye centers ----
            left_eye_center  = ((left_eye_outer[0]  + left_eye_inner[0]) / 2.0,
                                (left_eye_outer[1]  + left_eye_inner[1]) / 2.0)
            right_eye_center = ((right_eye_outer[0] + right_eye_inner[0]) / 2.0,
                                (right_eye_outer[1] + right_eye_inner[1]) / 2.0)

            dist_left  = lm.distance(nose_tip, left_eye_center)
            dist_right = lm.distance(nose_tip, right_eye_center)

            yaw_ratio = dist_left / max(1.0, dist_right)

            # ---- Pitch: nose relative to eyes and chin ----
            eye_mid_y = (left_eye_center[1] + right_eye_center[1]) / 2.0
            nose_y    = nose_tip[1]
            chin_y    = chin[1]

            upper = nose_y - eye_mid_y
            lower = chin_y - nose_y
            pitch_ratio = upper / max(1.0, lower)

            # ---- Classify ----
            states = []
            yaw_dir  = "forward"
            pitch_dir = "forward"

            if yaw_ratio > self.YAW_RATIO_HIGH:
                states.append("looking_right")
                yaw_dir = "right"
            elif yaw_ratio < self.YAW_RATIO_LOW:
                states.append("looking_left")
                yaw_dir = "left"

            if pitch_ratio > self.PITCH_RATIO_HIGH:
                states.append("looking_down")
                pitch_dir = "down"
            elif pitch_ratio < self.PITCH_RATIO_LOW:
                states.append("looking_up")
                pitch_dir = "up"

            if not states:
                status = "attentive"
                # Higher confidence when ratios are close to ideal
                yaw_conf   = 1.0 - abs(yaw_ratio - 1.0) / 0.6
                pitch_conf = 1.0 - abs(pitch_ratio - 0.5) / 0.4
                conf = max(0.3, min(1.0, (yaw_conf + pitch_conf) / 2.0))
            else:
                status = "distracted"
                # Higher confidence when strongly off-center
                yaw_dev   = abs(yaw_ratio - 1.0)
                pitch_dev = abs(pitch_ratio - 0.5)
                conf = min(1.0, max(yaw_dev / 1.5, pitch_dev / 0.8))

            return {
                "status": status,
                "confidence": self._clamp_conf(conf),
                "details": {
                    "yaw_ratio":     round(yaw_ratio, 3),
                    "pitch_ratio":   round(pitch_ratio, 3),
                    "yaw_direction": yaw_dir,
                    "pitch_direction": pitch_dir,
                    "states_triggered": states,
                    "thresholds": {
                        "yaw_high":  self.YAW_RATIO_HIGH,
                        "yaw_low":   self.YAW_RATIO_LOW,
                        "pitch_high": self.PITCH_RATIO_HIGH,
                        "pitch_low":  self.PITCH_RATIO_LOW,
                    },
                },
            }

        except Exception as e:
            self.logger.exception("Distraction detection failed: %s", e)
            return self._uncertain(f"detection_error: {type(e).__name__}")



