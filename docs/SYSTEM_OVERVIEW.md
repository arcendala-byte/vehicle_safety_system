# VEHISAFE — System Overview

**AI-Based Intelligent Vehicle Safety Monitoring and Road Black Spot Prediction System**

---

## Project Summary

VEHISAFE is a final-year academic prototype that shifts road safety from **reactive accident response** to **proactive accident prevention**. It combines four locally-running AI detectors, GPS tracking, a trained machine learning model, and an Arduino ignition prototype into one integrated platform.

---

## Core Components

### 1. Web Application (PHP + MySQL)
- Admin dashboard with live KPIs
- Driver and vehicle management
- Trip tracking with GPS
- Violation workflow
- Reports and analytics
- CSV export and print views

### 2. AI Detection Service (Python + Flask)
- **Seatbelt**: YOLO person detection + heuristic torso analysis
- **Phone**: YOLO's COCO cell phone class (class 67)
- **Drowsiness**: MediaPipe Face Landmarker + Eye Aspect Ratio (EAR) + Mouth Aspect Ratio (MAR)
- **Distraction**: MediaPipe head pose (yaw + pitch)

### 3. Machine Learning (scikit-learn)
- Random Forest Regressor
- Trained on 170 synthetic accident records
- Predicts black-spot risk score (0.0 – 1.0)
- **R² = 0.96**, MAE = 0.04
- Feature importance: accident frequency (82%), location (15%), conditions (3%)

### 4. GPS + Black-Spot Warnings
- Real browser GPS + simulated track
- Haversine proximity to known black spots
- Automatic warning when within 500m of HIGH/CRITICAL zones

### 5. Arduino Ignition Prototype
- Serial communication: Flask ↔ Arduino
- Simulated ignition control (relay or LED)
- Auto-locks when seatbelt not detected
- Auto-unlocks when seatbelt is fastened

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript, Lucide icons, Chart.js, Leaflet |
| Backend (Web) | PHP 8.0, Apache, MariaDB |
| Backend (AI) | Python 3.13, Flask 3.0.3 |
| AI Models | Ultralytics YOLOv8, MediaPipe FaceLandmarker, scikit-learn |
| Hardware | Arduino Uno (optional prototype) |

---

## Key Achievements

| Achievement | Metric |
|-------------|--------|
| AI detection modules | 4 working detectors |
| ML accuracy | R² = 0.96 (96%) |
| System test pass rate | 76/76 (100%) |
| Average detection time | 250ms |
| Total code files | 60+ |
| Total database tables | 11 |
| Total users tested | 13 drivers, 3 vehicles |
| Reports available | 5 (driver safety, violations, trips, black spots, analytics) |

---

## What Makes This System Distinctive

1. **Locally-run AI** — No cloud dependency, all models on CPU
2. **Real ML** — Random Forest achieves 96% R², not a lookup table
3. **End-to-end integration** — PHP ↔ Python ↔ Arduino all working
4. **Real-time alerting** — Audio + visual + voice
5. **Production-quality UI** — Enterprise-grade design system
6. **Defense-ready** — Full test suite, demo script, API docs

---

## Deliverables Checklist

- [x] Working web application
- [x] 4 AI detectors (100% tested)
- [x] ML model (96% R²)
- [x] GPS tracking + warnings
- [x] Live monitoring dashboard
- [x] Alert system (audio/visual/voice)
- [x] Arduino prototype (simulation verified)
- [x] Reports + CSV export
- [x] Full test suite (76/76 passing)
- [x] API documentation
- [x] Demo script

---

**Author:** Dickson Munene Muchiri  
**Registration:** DIT/2025/40888  
**Supervisor:** Mr. Kelvin Njagi  
**Institution:** Mount Kenya University — School of Computing and Informatics  
**Academic Year:** 2025-2026
