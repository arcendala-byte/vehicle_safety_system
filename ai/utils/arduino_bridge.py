"""
============================================================
VEHISAFE AI SERVICE — ARDUINO BRIDGE
============================================================
Handles USB serial communication with the Arduino Uno for
the ignition-control prototype.

Auto-detects an Arduino on common COM ports. If none found,
falls back to simulation mode (in-memory state only).
============================================================
"""

import threading
import time
from typing import Optional

from utils.logger import get_logger

logger = get_logger("arduino_bridge")

# ---- Try to import pyserial (optional) ----
try:
    import serial
    from serial.tools import list_ports
    HAS_PYSERIAL = True
except ImportError:
    HAS_PYSERIAL = False
    logger.warning("pyserial not installed — Arduino in simulation mode")


class ArduinoBridge:
    """
    Manages Arduino serial connection.
    Thread-safe singleton.
    """

    _instance: Optional['ArduinoBridge'] = None
    _lock = threading.Lock()

    def __init__(self) -> None:
        self.serial: Optional[any] = None
        self.port: Optional[str] = None
        self.connected: bool = False
        self.ignition_on: bool = False  # in-memory state
        self._write_lock = threading.Lock()

    @classmethod
    def get(cls) -> 'ArduinoBridge':
        if cls._instance is None:
            with cls._lock:
                if cls._instance is None:
                    cls._instance = ArduinoBridge()
        return cls._instance

    # ------------------------------------------------------------------
    # CONNECTION
    # ------------------------------------------------------------------
    def connect(self) -> bool:
        """Try to open serial connection to Arduino."""
        if self.connected:
            return True

        if not HAS_PYSERIAL:
            logger.info("Simulation mode: pyserial not available")
            return False

        # Try to auto-detect an Arduino on COM ports
        candidates = []
        for p in list_ports.comports():
            # Arduino Uno shows up as USB Serial Device on Windows
            desc = (p.description or "").lower()
            if "arduino" in desc or "usb serial" in desc or "ch340" in desc:
                candidates.append(p.device)

        if not candidates:
            logger.info("No Arduino detected on any COM port — simulation mode")
            return False

        for port in candidates:
            try:
                self.serial = serial.Serial(port, 9600, timeout=1)
                time.sleep(2)  # Arduino resets on connect; wait for ready
                self.port = port
                self.connected = True
                logger.info("Arduino connected on %s", port)

                # Drain any boot messages
                try:
                    self.serial.readline()
                except Exception:
                    pass
                return True
            except Exception as e:
                logger.warning("Could not open %s: %s", port, e)

        return False

    def disconnect(self) -> None:
        if self.serial:
            try:
                self.serial.close()
            except Exception:
                pass
        self.serial = None
        self.connected = False

    # ------------------------------------------------------------------
    # IGNITION CONTROL
    # ------------------------------------------------------------------
    def set_ignition(self, on: bool) -> dict:
        """Send ignition state to Arduino (or simulate)."""
        cmd = "IGNITION:ON\n" if on else "IGNITION:OFF\n"

        if not self.connected:
            # Simulation: just update in-memory state
            self.ignition_on = on
            logger.info("Simulation: ignition %s", "ON" if on else "OFF")
            return {
                "success": True,
                "mode": "simulation",
                "ignition_on": on,
                "message": "Simulated state updated (Arduino not connected).",
            }

        try:
            with self._write_lock:
                self.serial.write(cmd.encode("utf-8"))
                self.serial.flush()
                time.sleep(0.1)
                response = self.serial.readline().decode("utf-8", errors="ignore").strip()

            self.ignition_on = on
            logger.info("Arduino: ignition %s, response: %s", "ON" if on else "OFF", response)
            return {
                "success": True,
                "mode": "hardware",
                "port": self.port,
                "ignition_on": on,
                "arduino_response": response,
            }
        except Exception as e:
            logger.exception("Arduino write failed: %s", e)
            return {
                "success": False,
                "mode": "error",
                "ignition_on": self.ignition_on,
                "error": str(e),
            }

    def get_status(self) -> dict:
        """Return current ignition state."""
        if not self.connected:
            return {
                "connected": False,
                "mode": "simulation",
                "ignition_on": self.ignition_on,
                "port": None,
            }

        try:
            with self._write_lock:
                self.serial.write(b"STATUS:?\n")
                self.serial.flush()
                time.sleep(0.1)
                response = self.serial.readline().decode("utf-8", errors="ignore").strip()
            return {
                "connected": True,
                "mode": "hardware",
                "ignition_on": "STATE:ON" in response,
                "port": self.port,
                "arduino_response": response,
            }
        except Exception as e:
            logger.exception("Status check failed: %s", e)
            return {
                "connected": False,
                "mode": "error",
                "ignition_on": self.ignition_on,
                "port": self.port,
                "error": str(e),
            }
