"""
============================================================
VEHISAFE AI SERVICE - BLACK-SPOT RISK PREDICTION
============================================================
Loads the trained model and predicts risk_score for
(latitude, longitude, road_condition, weather, traffic_density).

Usage (standalone):
    python blackspot/predict.py
============================================================
"""

import sys
from pathlib import Path
from typing import Dict, Any

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import joblib
import numpy as np

from config import Config
from utils.logger import get_logger

# ---- Global model cache ----
_model = None
_encoders = None

RISK_LEVELS = [
    (0.80, "CRITICAL"),
    (0.60, "HIGH"),
    (0.40, "MEDIUM"),
    (0.00, "LOW"),
]


def _load():
    global _model, _encoders
    if _model is not None:
        return
    logger = get_logger("blackspot.predict")
    model_path    = Config.BLACKSPOT_MODEL_PATH
    encoders_path = model_path.parent / "encoders.joblib"

    if not model_path.exists():
        raise FileNotFoundError(
            f"Model not found at {model_path}. "
            f"Run: python blackspot/train.py"
        )

    _model    = joblib.load(model_path)
    _encoders = joblib.load(encoders_path)
    logger.info("Black-spot model loaded: %s", model_path)


def risk_level_from_score(score: float) -> str:
    for threshold, label in RISK_LEVELS:
        if score >= threshold:
            return label
    return "LOW"


def predict_blackspot(
    latitude: float,
    longitude: float,
    road_condition: str = "fair",
    weather: str = "clear",
    traffic_density: str = "medium",
    accident_count: int = 0,
) -> Dict[str, Any]:
    """
    Predict the risk_score for a coordinate + context.

    Returns:
        {
            "latitude": float,
            "longitude": float,
            "risk_score": float,
            "risk_level": str,
            "features_used": {...}
        }
    """
    _load()
    logger = get_logger("blackspot.predict")

    # Encode categoricals (fall back to first class if unknown)
    def enc(col, val):
        le = _encoders[col]
        if val in le.classes_:
            return int(le.transform([val])[0])
        logger.warning("Unknown %s='%s', using default", col, val)
        return int(le.transform([le.classes_[0]])[0])

    features = np.array([[
        float(latitude),
        float(longitude),
        enc("road_condition", road_condition),
        enc("weather", weather),
        enc("traffic_density", traffic_density),
        int(accident_count),
    ]])

    score = float(_model.predict(features)[0])
    score = max(0.0, min(1.0, score))  # clamp to [0, 1]

    return {
        "latitude":         round(float(latitude), 6),
        "longitude":        round(float(longitude), 6),
        "risk_score":       round(score, 3),
        "risk_level":       risk_level_from_score(score),
        "features_used": {
            "road_condition":  road_condition,
            "weather":         weather,
            "traffic_density": traffic_density,
            "accident_count":  accident_count,
        },
    }


if __name__ == "__main__":
    # Standalone smoke test
    logger = get_logger("blackspot.predict")
    logger.info("=== Black-Spot Prediction Smoke Test ===")

    test_cases = [
        {"name": "Kinungi (CRITICAL)",
         "args": dict(latitude=-0.9573, longitude=36.5580,
                     road_condition="poor", weather="rain",
                     traffic_density="high", accident_count=27)},
        {"name": "Ruiru (HIGH)",
         "args": dict(latitude=-1.1460, longitude=36.9600,
                     road_condition="fair", weather="clear",
                     traffic_density="high", accident_count=19)},
        {"name": "Bondo (MEDIUM)",
         "args": dict(latitude=-0.0930, longitude=34.2780,
                     road_condition="good", weather="mixed",
                     traffic_density="medium", accident_count=8)},
        {"name": "Random Nairobi road (LOW)",
         "args": dict(latitude=-1.3000, longitude=36.8000,
                     road_condition="good", weather="clear",
                     traffic_density="low", accident_count=1)},
    ]

    for tc in test_cases:
        result = predict_blackspot(**tc["args"])
        logger.info("%s -> score=%.3f, level=%s",
                    tc["name"], result["risk_score"], result["risk_level"])
