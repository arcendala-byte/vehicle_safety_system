# Arduino Ignition Control — Wiring Diagram

## Components

| Component | Purpose |
|-----------|---------|
| Arduino Uno R3 | Microcontroller |
| 1-Channel 5V Relay Module | Simulates ignition relay |
| Jumper Wires | Connections |
| USB Cable | Arduino → PC |
| *(Optional)* LED + 220Ω Resistor | Alternative to relay (pure visual demo) |

## Wiring

### Option A — Relay (recommended for realism)

$content = @'
# VEHISAFE — Arduino Ignition Control Prototype

## Overview

This folder contains the Arduino code for the ignition-control
prototype described in the VEHISAFE project proposal.

**Purpose:** Demonstrate that the vehicle ignition is disabled
when the driver's seatbelt is not fastened. This is a
**prototype** — it does NOT connect to a real vehicle.

## Files

| File | Purpose |
|------|---------|
| `ignition_control.ino` | Arduino sketch (upload with Arduino IDE) |
| `wiring_diagram.md` | Wiring instructions |
| `README.md` | This file |

## Setup

### 1. Install Arduino IDE
Download from https://www.arduino.cc/en/software

### 2. Wire the board
Follow `wiring_diagram.md`. Use a relay or LED. **Do not
connect to a real vehicle ignition.**

### 3. Upload the sketch
1. Open `ignition_control.ino` in Arduino IDE
2. Select **Tools → Board → Arduino Uno**
3. Select **Tools → Port → COM#** (the one showing your Arduino)
4. Click **Upload** (right-arrow icon)
5. Open **Tools → Serial Monitor**, set baud rate to **9600**
6. Type `PING` and press Enter — should respond `PONG`

### 4. Test the protocol
In Serial Monitor:

| Type | Response |
|------|----------|
| `IGNITION:ON` | `STATE:ON` — relay/LED activates |
| `IGNITION:OFF` | `STATE:OFF` — relay/LED deactivates |
| `STATUS:?` | `STATE:ON` or `STATE:OFF` |

## Integration with Python

The Flask AI service uses `pyserial` to communicate with the
Arduino. See `ai/utils/arduino_bridge.py` for the implementation.

**Simulation mode:** If the Arduino is not connected, the Python
bridge auto-falls back to simulation — no hardware needed.

## Safety Warnings

- Never connect the relay to mains power
- Never connect the relay to a real vehicle's ignition
- Use only low-voltage (< 12V) DC loads for demonstration
- Unplug USB before modifying wiring

## Academic Purpose

This prototype demonstrates:
1. Serial communication between software and hardware
2. Event-driven relay control
3. Safety interlock logic (seatbelt → ignition)
4. Integration with a web-based safety monitoring system

It is a **proof-of-concept** suitable for academic defense,
not for production vehicle use.
