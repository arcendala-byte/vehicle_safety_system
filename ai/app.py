"""
============================================================
VEHISAFE AI SERVICE - FLASK APPLICATION
============================================================
Phase 15: All 4 image detectors + black-spot ML model.
============================================================
"""

from flask import Flask, request, jsonify
from config import Config
from utils.logger import get_logger
from utils.api_auth import require_api_key
from utils import image_utils

# ---- Boot ----
Config.ensure_dirs()
logger = get_logger("app")

app = Flask(__name__)
app.config["JSON_SORT_KEYS"] = False

# ---- Detector cache ----
_detector_cache = {}
_blackspot_loaded = False


def get_detector(name: str):
    if name in _detector_cache:
        return _detector_cache[name]
    if name == "seatbelt":
        from detection.seatbelt import SeatbeltDetector
        _detector_cache[name] = SeatbeltDetector()
    elif name == "phone":
        from detection.phone import PhoneDetector
        _detector_cache[name] = PhoneDetector()
    elif name == "drowsiness":
        from detection.drowsiness import DrowsinessDetector
        _detector_cache[name] = DrowsinessDetector()
    elif name == "distraction":
        from detection.distraction import DistractionDetector
        _detector_cache[name] = DistractionDetector()
    else:
        raise ValueError(f"Unknown detector: {name}")
    return _detector_cache[name]


ALL_DETECTORS = ["seatbelt", "phone", "drowsiness", "distraction"]


# ============================================================
# MIDDLEWARE
# ============================================================
@app.after_request
def _log_request(response):
    logger.debug("%s %s -> %s", request.method, request.path, response.status_code)
    return response


# ============================================================
# HEALTH / INFO
# ============================================================
@app.route("/api/health", methods=["GET"])
def health():
    loaded = [name for name in ALL_DETECTORS if name in _detector_cache]
    return jsonify({
        "success": True,
        "service": Config.SERVICE_NAME,
        "version": Config.SERVICE_VERSION,
        "status": "ok",
        "host": Config.HOST,
        "port": Config.PORT,
        "models_loaded": loaded,
        "blackspot_model_loaded": _blackspot_loaded,
    })


@app.route("/", methods=["GET"])
def root():
    return jsonify({
        "service": Config.SERVICE_NAME,
        "message": "VEHISAFE AI Service is running.",
    })


# ============================================================
# REQUEST PARSING
# ============================================================
def _parse_detection_request():
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return None, (jsonify({
            "success": False, "error": "BadRequest",
            "message": "Expected JSON body.",
        }), 400)
    required = ["driver_id", "image_base64"]
    missing = [k for k in required if k not in data]
    if missing:
        return None, (jsonify({
            "success": False, "error": "BadRequest",
            "message": f"Missing required fields: {', '.join(missing)}",
        }), 400)
    try:
        dims = image_utils.get_dimensions(data["image_base64"])
    except Exception as e:
        return None, (jsonify({
            "success": False, "error": "BadImage",
            "message": str(e),
        }), 400)
    data["_image_dimensions"] = dims
    return data, None


def _decode_image(payload):
    try:
        img = image_utils.base64_to_numpy(payload["image_base64"])
        return img, None
    except Exception as e:
        logger.exception("Image decode error: %s", e)
        return None, (jsonify({
            "success": False, "error": "BadImage",
            "message": "Could not decode image.",
        }), 400)


def _run_single_detector(detector_name, img, payload):
    import time
    t0 = time.perf_counter()
    detector = get_detector(detector_name)
    result = detector.detect(img)
    elapsed_ms = int((time.perf_counter() - t0) * 1000)
    logger.info("%s driver=%s status=%s conf=%.2f in %dms",
                detector_name.capitalize(),
                payload.get("driver_id"),
                result["status"], result["confidence"], elapsed_ms)
    return {
        "success": True,
        "detections": [{
            "type": detector_name,
            "status": result["status"],
            "confidence": result["confidence"],
            "details": result["details"],
        }],
        "processing_time_ms": elapsed_ms,
    }


# ============================================================
# IMAGE DETECTION ENDPOINTS
# ============================================================
@app.route("/api/detect/seatbelt", methods=["POST"])
@require_api_key
def detect_seatbelt():
    payload, err = _parse_detection_request()
    if err: return err
    img, err = _decode_image(payload)
    if err: return err
    return jsonify(_run_single_detector("seatbelt", img, payload))


@app.route("/api/detect/phone", methods=["POST"])
@require_api_key
def detect_phone():
    payload, err = _parse_detection_request()
    if err: return err
    img, err = _decode_image(payload)
    if err: return err
    return jsonify(_run_single_detector("phone", img, payload))


@app.route("/api/detect/drowsiness", methods=["POST"])
@require_api_key
def detect_drowsiness():
    payload, err = _parse_detection_request()
    if err: return err
    img, err = _decode_image(payload)
    if err: return err
    return jsonify(_run_single_detector("drowsiness", img, payload))


@app.route("/api/detect/distraction", methods=["POST"])
@require_api_key
def detect_distraction():
    payload, err = _parse_detection_request()
    if err: return err
    img, err = _decode_image(payload)
    if err: return err
    return jsonify(_run_single_detector("distraction", img, payload))


@app.route("/api/detect/all", methods=["POST"])
@require_api_key
def detect_all():
    import time
    payload, err = _parse_detection_request()
    if err: return err
    img, err = _decode_image(payload)
    if err: return err
    t0 = time.perf_counter()
    detections = []
    for name in ALL_DETECTORS:
        try:
            det = get_detector(name)
            r = det.detect(img)
            detections.append({
                "type": name, "status": r["status"],
                "confidence": r["confidence"], "details": r["details"],
            })
        except Exception as e:
            logger.exception("%s failed in detect_all: %s", name, e)
            detections.append({
                "type": name, "status": "uncertain",
                "confidence": 0.0, "details": {"reason": "internal_error"},
            })
    elapsed_ms = int((time.perf_counter() - t0) * 1000)
    logger.info("Combined detection driver=%s in %dms",
                payload.get("driver_id"), elapsed_ms)
    return jsonify({
        "success": True,
        "detections": detections,
        "processing_time_ms": elapsed_ms,
    })


# ============================================================
# BLACK-SPOT ML PREDICTION ENDPOINT
# ============================================================
@app.route("/api/predict/blackspot", methods=["POST"])
@require_api_key
def predict_blackspot_endpoint():
    global _blackspot_loaded
    import time

    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return jsonify({"success": False, "error": "BadRequest",
                        "message": "Expected JSON body."}), 400

    required = ["latitude", "longitude"]
    missing = [k for k in required if k not in data]
    if missing:
        return jsonify({"success": False, "error": "BadRequest",
                        "message": f"Missing: {', '.join(missing)}"}), 400

    try:
        lat = float(data["latitude"])
        lng = float(data["longitude"])
    except (TypeError, ValueError):
        return jsonify({"success": False, "error": "BadRequest",
                        "message": "latitude/longitude must be numbers."}), 400

    if not (-90 <= lat <= 90) or not (-180 <= lng <= 180):
        return jsonify({"success": False, "error": "BadRequest",
                        "message": "Out-of-range coordinates."}), 400

    t0 = time.perf_counter()
    try:
        from blackspot.predict import predict_blackspot
        result = predict_blackspot(
            latitude=lat,
            longitude=lng,
            road_condition=str(data.get("road_condition", "fair")),
            weather=str(data.get("weather", "clear")),
            traffic_density=str(data.get("traffic_density", "medium")),
            accident_count=int(data.get("accident_count", 0)),
        )
        _blackspot_loaded = True
    except FileNotFoundError as e:
        logger.error("Model not trained: %s", e)
        return jsonify({"success": False, "error": "ModelNotTrained",
                        "message": str(e)}), 503
    except Exception as e:
        logger.exception("Prediction failed: %s", e)
        return jsonify({"success": False, "error": "InternalServerError",
                        "message": str(e)}), 500

    elapsed_ms = int((time.perf_counter() - t0) * 1000)
    logger.info("Blackspot predict (%.4f, %.4f) -> score=%.3f, level=%s in %dms",
                lat, lng, result["risk_score"], result["risk_level"], elapsed_ms)

    return jsonify({
        "success": True,
        "prediction": result,
        "processing_time_ms": elapsed_ms,
    })


# ============================================================
# ARDUINO IGNITION CONTROL (Phase 19)
# ============================================================
@app.route("/api/arduino/ignition", methods=["POST"])
@require_api_key
def arduino_set_ignition():
    """
    Set the ignition state on the Arduino prototype.
    Body: { "ignition_on": true | false }
    """
    from utils.arduino_bridge import ArduinoBridge

    data = request.get_json(silent=True)
    if not isinstance(data, dict) or "ignition_on" not in data:
        return jsonify({
            "success": False, "error": "BadRequest",
            "message": "Expected { \"ignition_on\": true|false }"
        }), 400

    on = bool(data["ignition_on"])
    bridge = ArduinoBridge.get()

    # Auto-connect on first call
    if not bridge.connected:
        bridge.connect()

    result = bridge.set_ignition(on)
    return jsonify(result)


@app.route("/api/arduino/status", methods=["GET"])
@require_api_key
def arduino_status():
    """Return current Arduino ignition state."""
    from utils.arduino_bridge import ArduinoBridge
    bridge = ArduinoBridge.get()
    if not bridge.connected:
        bridge.connect()
    return jsonify({
        "success": True,
        "state": bridge.get_status(),
    })


@app.route("/api/arduino/connect", methods=["POST"])
@require_api_key
def arduino_connect():
    """Attempt to connect to Arduino."""
    from utils.arduino_bridge import ArduinoBridge
    bridge = ArduinoBridge.get()
    ok = bridge.connect()
    return jsonify({
        "success": True,
        "connected": ok,
        "state": bridge.get_status(),
    })

# ============================================================
# ERROR HANDLERS
# ============================================================
@app.errorhandler(404)
def _not_found(e):
    return jsonify({"success": False, "error": "NotFound",
                    "message": "Endpoint not found."}), 404


@app.errorhandler(405)
def _bad_method(e):
    return jsonify({"success": False, "error": "MethodNotAllowed",
                    "message": "Wrong HTTP method."}), 405


@app.errorhandler(500)
def _server_error(e):
    logger.exception("Internal error: %s", e)
    return jsonify({"success": False, "error": "InternalServerError",
                    "message": "See server logs."}), 500


# ============================================================
# BOOT
# ============================================================
def _warm_models():
    logger.info("Warming models...")
    import numpy as np
    dummy = np.zeros((64, 64, 3), dtype=np.uint8)
    for name in ALL_DETECTORS:
        try:
            det = get_detector(name)
            det.detect(dummy)
            logger.info("Warmed: %s", name)
        except Exception as e:
            logger.warning("Warm-up failed for %s: %s", name, e)

    # Warm black-spot model
    global _blackspot_loaded
    try:
        from blackspot.predict import predict_blackspot
        predict_blackspot(latitude=-1.2921, longitude=36.8219)
        _blackspot_loaded = True
        logger.info("Warmed: blackspot-model")
    except Exception as e:
        logger.warning("Black-spot model warm-up failed: %s", e)

    logger.info("Models warmed.")


if __name__ == "__main__":
    logger.info("Starting %s v%s on %s:%s",
                Config.SERVICE_NAME, Config.SERVICE_VERSION, Config.HOST, Config.PORT)
    _warm_models()
    app.run(host=Config.HOST, port=Config.PORT, debug=Config.DEBUG)

