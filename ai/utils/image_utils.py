"""
============================================================
VEHISAFE AI SERVICE — IMAGE UTILITIES
============================================================
Convert between base64 / bytes / PIL / numpy.
Used by all detection modules.
============================================================
"""

import base64
import io
import re
from typing import Optional, Tuple

import numpy as np
from PIL import Image


# Regex to strip data-URI prefix
_DATA_URI_RE = re.compile(r"^data:image\/[a-zA-Z0-9.+-]+;base64,", re.IGNORECASE)


def base64_to_bytes(b64_str: str) -> bytes:
    """
    Accept a base64 string, with or without a data-URI prefix.
    Raises ValueError if decoding fails.
    """
    if not b64_str:
        raise ValueError("Empty image payload.")
    stripped = _DATA_URI_RE.sub("", b64_str.strip())
    try:
        return base64.b64decode(stripped, validate=True)
    except Exception as e:
        raise ValueError(f"Invalid base64 image: {e}") from e


def bytes_to_pil(data: bytes) -> Image.Image:
    """Decode raw bytes to a PIL Image (RGB)."""
    try:
        img = Image.open(io.BytesIO(data))
        img.load()
        return img.convert("RGB")
    except Exception as e:
        raise ValueError(f"Could not decode image: {e}") from e


def base64_to_pil(b64_str: str) -> Image.Image:
    """Combined convenience function."""
    return bytes_to_pil(base64_to_bytes(b64_str))


def pil_to_numpy(img: Image.Image) -> np.ndarray:
    """Convert PIL image to a HxWx3 uint8 numpy array (RGB)."""
    return np.array(img, dtype=np.uint8)


def base64_to_numpy(b64_str: str) -> np.ndarray:
    """One-shot conversion for detectors."""
    return pil_to_numpy(base64_to_pil(b64_str))


def resize_max_side(img: Image.Image, max_side: int = 640) -> Image.Image:
    """
    Resize an image so its largest side equals `max_side` (preserving aspect).
    Returns the original if it's already smaller.
    """
    w, h = img.size
    longest = max(w, h)
    if longest <= max_side:
        return img

    scale = max_side / float(longest)
    new_size = (int(round(w * scale)), int(round(h * scale)))
    return img.resize(new_size, Image.LANCZOS)


def get_dimensions(b64_str: str) -> Tuple[int, int]:
    """Return (width, height) of the image, or (0,0) if unavailable."""
    try:
        img = base64_to_pil(b64_str)
        return img.size
    except Exception:
        return (0, 0)


def clamp(value: float, lo: float = 0.0, hi: float = 1.0) -> float:
    """Clamp a numeric value to [lo, hi]."""
    return max(lo, min(hi, value))