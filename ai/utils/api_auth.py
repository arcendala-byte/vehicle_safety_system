"""
============================================================
VEHISAFE AI SERVICE — API AUTHENTICATION
============================================================
Simple shared-secret check via X-API-Key header.
============================================================
"""

from functools import wraps
from flask import request, jsonify

from config import Config


def require_api_key(fn):
    """
    Decorator: reject requests without a matching X-API-Key header.
    """
    @wraps(fn)
    def wrapper(*args, **kwargs):
        provided = request.headers.get("X-API-Key", "")
        if not provided or provided != Config.API_KEY:
            return jsonify({
                "success": False,
                "error": "Unauthorized",
                "message": "Missing or invalid X-API-Key header.",
            }), 401
        return fn(*args, **kwargs)
    return wrapper