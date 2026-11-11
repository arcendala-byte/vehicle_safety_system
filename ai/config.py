"""
============================================================
VEHISAFE AI SERVICE — CONFIGURATION
============================================================
Loads settings from environment (.env) with sensible defaults.
============================================================
"""

import os
from pathlib import Path
from dotenv import load_dotenv

# ---- Load .env if present ----
BASE_DIR = Path(__file__).resolve().parent
ENV_FILE = BASE_DIR / ".env"
if ENV_FILE.exists():
    load_dotenv(ENV_FILE)


def _env(key: str, default: str = "") -> str:
    return os.getenv(key, default)


def _env_int(key: str, default: int) -> int:
    try:
        return int(os.getenv(key, str(default)))
    except (TypeError, ValueError):
        return default


def _env_float(key: str, default: float) -> float:
    try:
        return float(os.getenv(key, str(default)))
    except (TypeError, ValueError):
        return default


def _env_bool(key: str, default: bool = False) -> bool:
    val = os.getenv(key, str(default)).strip().lower()
    return val in ("1", "true", "yes", "on")


class Config:
    # ---- Server ----
    HOST  = _env("AI_HOST", "127.0.0.1")
    PORT  = _env_int("AI_PORT", 5000)
    DEBUG = _env_bool("AI_DEBUG", False)

    # ---- Security ----
    API_KEY = _env("AI_API_KEY", "change-me-vehisafe-dev-key-2025")

    # ---- Logging ----
    LOG_LEVEL = _env("AI_LOG_LEVEL", "INFO")
    LOG_FILE  = BASE_DIR / _env("AI_LOG_FILE", "logs/ai_service.log")

    # ---- Paths ----
    BASE_DIR     = BASE_DIR
    MODEL_DIR    = BASE_DIR / _env("AI_MODEL_DIR", "models")
    YOLO_MODEL   = _env("AI_YOLO_MODEL", "yolov8n.pt")
    BLACKSPOT_MODEL_PATH = BASE_DIR / _env(
        "AI_BLACKSPOT_MODEL", "blackspot/model/blackspot_model.joblib"
    )

    # ---- Detection thresholds ----
    CONFIDENCE_THRESHOLD = _env_float("AI_CONFIDENCE_THRESHOLD", 0.55)
    DROWSINESS_SECONDS   = _env_float("AI_DROWSINESS_SECONDS", 2.0)
    DISTRACTION_SECONDS  = _env_float("AI_DISTRACTION_SECONDS", 3.0)

    # ---- Metadata ----
    SERVICE_NAME    = "vehisafe-ai"
    SERVICE_VERSION = "0.1.0"

    @classmethod
    def ensure_dirs(cls) -> None:
        """Create logs/ and models/ dirs if missing."""
        cls.LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
        cls.MODEL_DIR.mkdir(parents=True, exist_ok=True)
        (BASE_DIR / "blackspot" / "model").mkdir(parents=True, exist_ok=True)
        (BASE_DIR / "blackspot" / "datasets").mkdir(parents=True, exist_ok=True)