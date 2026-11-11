"""
============================================================
VEHISAFE AI SERVICE — STRUCTURED LOGGING
============================================================
Provides a single configured logger for the whole service.
============================================================
"""

import logging
import sys
from logging.handlers import RotatingFileHandler

from config import Config


def get_logger(name: str = "vehisafe") -> logging.Logger:
    """
    Return a singleton logger with:
      - Console output (colorized level if colorlog available)
      - Rotating file output
    """
    logger = logging.getLogger(name)
    if logger.handlers:
        return logger

    level = getattr(logging, Config.LOG_LEVEL.upper(), logging.INFO)
    logger.setLevel(level)
    logger.propagate = False

    fmt = "%(asctime)s [%(levelname)s] %(name)s: %(message)s"
    datefmt = "%Y-%m-%d %H:%M:%S"

    # ---- Console handler ----
    try:
        import colorlog
        console_fmt = colorlog.ColoredFormatter(
            "%(log_color)s%(asctime)s [%(levelname)s] %(name)s: %(message)s",
            datefmt=datefmt,
            log_colors={
                "DEBUG":    "cyan",
                "INFO":     "green",
                "WARNING":  "yellow",
                "ERROR":    "red",
                "CRITICAL": "bold_red",
            },
        )
        console = logging.StreamHandler(sys.stdout)
        console.setFormatter(console_fmt)
    except ImportError:
        console = logging.StreamHandler(sys.stdout)
        console.setFormatter(logging.Formatter(fmt, datefmt=datefmt))

    logger.addHandler(console)

    # ---- File handler ----
    try:
        Config.LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
        file_handler = RotatingFileHandler(
            Config.LOG_FILE,
            maxBytes=2 * 1024 * 1024,  # 2 MB
            backupCount=3,
            encoding="utf-8",
        )
        file_handler.setFormatter(logging.Formatter(fmt, datefmt=datefmt))
        logger.addHandler(file_handler)
    except Exception as e:
        logger.warning("Could not attach file logger: %s", e)

    return logger