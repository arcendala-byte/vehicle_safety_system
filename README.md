# VEHISAFE — AI-Based Intelligent Vehicle Safety Monitoring and Road Black Spot Prediction System

A final-year academic prototype developed for **Mount Kenya University — School of Computing and Informatics**.

**Author:** Dickson Munene Muchiri (DIT/2025/40888)
**Supervisor:** Mr. Kelvin Njagi

---

## 1. Project Overview

The system integrates Artificial Intelligence, Computer Vision, Machine Learning,
GPS, and embedded systems to shift road safety from **reactive accident response**
to **proactive accident prevention**.

### Core Capabilities

- Real-time driver monitoring (seatbelt, phone usage, drowsiness, distraction)
- Road accident black-spot prediction using ML
- GPS-based hazard warnings
- Driver safety scoring and analytics
- Prototype ignition control via Arduino (seatbelt interlock)

---

## 2. Technology Stack

| Layer         | Technology                                          |
|---------------|-----------------------------------------------------|
| Web App       | PHP 8+, XAMPP (Apache + MySQL), PDO                 |
| Frontend      | HTML5, CSS3 (custom design system), JavaScript      |
| Icons         | Lucide                                              |
| Fonts         | Inter, IBM Plex Mono                                |
| AI / ML       | Python 3.x, OpenCV, YOLO, MediaPipe, Scikit-learn   |
| AI Service    | Flask / FastAPI (HTTP/JSON API on port 5000)        |
| Database      | MySQL (`vehicle_safety`)                            |
| Hardware      | Arduino Uno, Relay Module, GPS Module (prototype)   |

---

## 3. Environment

- **XAMPP folder:** `C:\xampp2\`
- **Apache port:** `80`
- **Project path:** `C:\xampp2\htdocs\vehicle_safety_system\`
- **Base URL:** `http://localhost/vehicle_safety_system/`
- **Database name:** `vehicle_safety`
- **DB user:** `root`
- **DB password:** *(empty — XAMPP default)*

---

## 4. Setup Instructions

### 4.1 Start XAMPP

1. Open **XAMPP2 Control Panel**
2. Start **Apache** and **MySQL**

### 4.2 Verify Project Loads

Visit: `http://localhost/vehicle_safety_system/`

You should be redirected to `login.php` and see the sign-in page.

### 4.3 Database Setup *(Phase 3 — not yet created)*

Once the SQL schema is available:

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin/`
2. Create a database named `vehicle_safety`
3. Import `database/vehicle_safety.sql`

### 4.4 Python AI Service *(Phase 9 — not yet created)*

```powershell
cd C:\xampp2\htdocs\vehicle_safety_system\ai
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
python app.py
```

The AI service will run on `http://127.0.0.1:5000`.

---

## 5. Folder Structure

```
vehicle_safety_system/
├── admin/          → Admin dashboard modules
├── driver/         → Driver dashboard modules
├── api/            → REST-style PHP API endpoints
├── config/         → Application + database configuration
├── includes/       → Shared header, footer, helpers
├── assets/         → CSS, JS, images, audio, fonts, vendor
├── database/       → SQL schema, seed data, backups
├── ai/             → Python AI service (detection + ML)
├── arduino/        → Arduino prototype (ignition control)
├── uploads/        → User-uploaded files
├── logs/           → Application and error logs
├── docs/           → Screenshots, diagrams, documentation
├── index.php       → Entry point → redirects to login
├── login.php       → Login page
└── README.md       → This file
```

---

## 6. Development Phases

| Phase | Description                             | Status         |
|-------|-----------------------------------------|----------------|
| 1     | Architecture analysis & confirmation    | ✅ Complete    |
| 2     | Project structure & base configuration  | ✅ Complete    |
| 3     | MySQL database schema                   | ⏳ Next        |
| 4     | Authentication & role-based access      | ⏳ Pending     |
| 5     | Admin dashboard shell                   | ⏳ Pending     |
| 6     | Driver & vehicle management             | ⏳ Pending     |
| 7     | Trip management                         | ⏳ Pending     |
| 8     | Violation & event management            | ⏳ Pending     |
| 9     | Python AI service foundation            | ⏳ Pending     |
| 10    | Seatbelt detection                      | ⏳ Pending     |
| 11    | Phone detection                         | ⏳ Pending     |
| 12    | Drowsiness detection                    | ⏳ Pending     |
| 13    | Distraction detection                   | ⏳ Pending     |
| 14    | GPS integration                         | ⏳ Pending     |
| 15    | Black-spot ML model                     | ⏳ Pending     |
| 16    | Black-spot ↔ PHP integration            | ⏳ Pending     |
| 17    | Driver warning system                   | ⏳ Pending     |
| 18    | Safety score engine                     | ⏳ Pending     |
| 19    | Arduino prototype                       | ⏳ Pending     |
| 20    | Reports & analytics                     | ⏳ Pending     |
| 21    | Testing & debugging                     | ⏳ Pending     |
| 22    | Security review                         | ⏳ Pending     |
| 23    | UI/UX polish                            | ⏳ Pending     |
| 24    | Final documentation                     | ⏳ Pending     |

---

## 7. Demo Mode

Demo mode is currently **enabled** (`DEMO_MODE = true` in `config/config.php`).

When enabled, the system provides:

- Simulated GPS coordinates (no hardware required)
- Sample drivers, vehicles, and black spots
- Sample accident dataset for ML training
- Virtual Arduino state (no physical board required)

All simulated data will be **clearly labelled as DEMO DATA** in the UI.

---

## 8. Privacy & Ethics

This system monitors drivers via camera and computer vision.

- **Prototype only** — not certified for real vehicle integration
- **Metadata only** — raw camera footage is NOT stored by default
- **Consent required** — drivers are informed before monitoring begins
- **AI predictions are probabilistic** — not guaranteed to be accurate
- **Data encryption** — stored data protected by authentication and encryption

---

## 9. License

Academic project. Not for commercial use without permission.

---

## 10. Contact

**Dickson Munene Muchiri**
Mount Kenya University — School of Computing and Informatics
Registration: DIT/2025/40888

Supervisor: **Mr. Kelvin Njagi**