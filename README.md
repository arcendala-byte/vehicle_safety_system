# VEHISAFE — AI-Based Intelligent Vehicle Safety Monitoring and Road Black Spot Prediction System

A final-year academic prototype developed for **Mount Kenya University — School of Computing and Informatics**.

**Author:** Dickson Munene Muchiri (DIT/2025/40888)  
**Supervisor:** Mr. Kelvin Njagi

---

## 1. Project Overview

VEHISAFE is an intelligent vehicle safety monitoring and road black-spot prediction system that integrates Artificial Intelligence, Computer Vision, Machine Learning, GPS technology, and embedded systems.

The system aims to shift road safety from **reactive accident response to proactive accident prevention** through real-time driver monitoring, hazard identification, and safety analytics.

### Core Capabilities

- Real-time driver monitoring (seatbelt usage, phone usage, drowsiness, and distraction).
- Road accident black-spot prediction using Machine Learning.
- GPS-based hazard warnings.
- Driver safety scoring and analytics.
- Prototype ignition control through Arduino (seatbelt interlock).

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Web Application | PHP 8+, XAMPP (Apache + MySQL), PDO |
| Frontend | HTML5, CSS3, JavaScript |
| Icons | Lucide |
| Fonts | Inter, IBM Plex Mono |
| AI / ML | Python 3.x, OpenCV, YOLO, MediaPipe, Scikit-learn |
| AI Service | Flask / FastAPI (HTTP/JSON API on port 5000) |
| Database | MySQL (`vehicle_safety`) |
| Hardware | Arduino Uno, Relay Module, GPS Module (prototype) |

---

## 3. Development Environment

- **XAMPP directory:** `C:\xampp2\`
- **Apache port:** `80`
- **Project directory:** `C:\xampp2\htdocs\vehicle_safety_system\`
- **Local URL:** `http://localhost/vehicle_safety_system/`
- **Database name:** `vehicle_safety`
- **Database username:** `root`
- **Database password:** Empty by default in a standard XAMPP installation.

---

## 4. Installation and Setup

### 4.1 Start XAMPP

1. Open the XAMPP2 Control Panel.
2. Start Apache.
3. Start MySQL.

### 4.2 Access the Web Application

Open your browser and visit:

`http://localhost/vehicle_safety_system/`

The application should redirect to `login.php` and display the sign-in page.

### 4.3 Database Configuration

1. Open phpMyAdmin: `http://localhost/phpmyadmin/`
2. Create a database named `vehicle_safety`.
3. Import the SQL file located at:

   `database/vehicle_safety.sql`

4. Verify the database connection settings in `config/database.php`.

### 4.4 Python AI Service Setup

Open PowerShell and execute:

```powershell
cd C:\xampp2\htdocs\vehicle_safety_system\ai

python -m venv venv

.\venv\Scripts\Activate.ps1

pip install -r requirements.txt

python app.py
```

The AI service is configured to run locally on:

`http://127.0.0.1:5000`

### 4.5 Arduino Setup

1. Open the Arduino IDE.
2. Navigate to `arduino/ignition_control/`.
3. Open `ignition_control.ino`.
4. Select the appropriate Arduino board and COM port.
5. Upload the sketch to the Arduino Uno.
6. Connect the required prototype components according to the hardware configuration.

---

## 5. Project Folder Structure

```text
vehicle_safety_system/
│
├── admin/                  → Admin dashboard and management modules
├── driver/                 → Driver dashboard and safety modules
├── api/                    → PHP API endpoints
├── ai/                     → Python AI service and ML modules
├── arduino/                → Arduino ignition control prototype
├── assets/                 → CSS, JavaScript, images, audio and media
├── config/                 → Application and database configuration
├── database/               → Database schema and SQL files
├── docs/                   → Technical documentation and testing guides
├── includes/               → Shared PHP functions and components
├── uploads/                → User-uploaded files
│
├── index.php               → Application entry point
├── login.php               → User authentication
├── dashboard.php           → Main dashboard
├── register.php            → User registration
├── logout.php              → User logout
├── setup_password.php      → Password setup
├── .htaccess               → Apache configuration
├── README.md               → Project documentation
└── DEPLOYMENT_TRANSFER.md  → Deployment and transfer documentation
```

---

## 6. Development Phases — Completion Status

All 24 development phases are marked complete in this project overview.

| Phase | Development Activity | Status |
|---:|---|---|
| 1 | Architecture analysis and confirmation | ✅ Complete |
| 2 | Project structure and base configuration | ✅ Complete |
| 3 | MySQL database schema | ✅ Complete |
| 4 | Authentication and role-based access | ✅ Complete |
| 5 | Admin dashboard development | ✅ Complete |
| 6 | Driver and vehicle management | ✅ Complete |
| 7 | Trip management | ✅ Complete |
| 8 | Violation and event management | ✅ Complete |
| 9 | Python AI service foundation | ✅ Complete |
| 10 | Seatbelt detection | ✅ Complete |
| 11 | Phone usage detection | ✅ Complete |
| 12 | Drowsiness detection | ✅ Complete |
| 13 | Driver distraction detection | ✅ Complete |
| 14 | GPS integration | ✅ Complete |
| 15 | Black-spot Machine Learning model | ✅ Complete |
| 16 | Black-spot and PHP integration | ✅ Complete |
| 17 | Driver warning system | ✅ Complete |
| 18 | Driver safety score engine | ✅ Complete |
| 19 | Arduino ignition control prototype | ✅ Complete |
| 20 | Reports and analytics | ✅ Complete |
| 21 | System testing and debugging | ✅ Complete |
| 22 | Security review | ✅ Complete |
| 23 | User interface and experience improvements | ✅ Complete |
| 24 | Final project documentation | ✅ Complete |

**Overall project status: 24/24 phases marked complete.**

---

## 7. Demo Mode

Demo mode is enabled through the `DEMO_MODE` configuration in `config/config.php`.

When enabled, the system supports:

- Simulated GPS coordinates without requiring physical GPS hardware.
- Sample driver and vehicle records.
- Sample road black-spot information.
- Sample accident datasets for Machine Learning.
- Virtual Arduino states without a physical board.

Simulated information should be clearly identified as **DEMO DATA** within the application.

---

## 8. Privacy, Security and Ethics

VEHISAFE uses camera-based monitoring and computer vision to support driver safety.

The project follows these principles:

- **Prototype limitation:** The system is an academic prototype and is not certified for real-world vehicle integration.
- **Data privacy:** Raw camera footage is not stored by default.
- **Informed consent:** Drivers should be informed before monitoring begins.
- **AI limitations:** Predictions are probabilistic and are not guaranteed to be accurate.
- **Data protection:** Authentication and appropriate security measures are used to protect stored information.

---

## 9. Project License

Academic project developed for educational and research purposes.

Commercial use or redistribution requires permission from the relevant project owner.

---

## 10. Contact and Project Information

**Author:** Arcel Ndala  
**Institution:** Mount Kenya University  
**School:** School of Computing and Informatics   
**Project Supervisor:** Mr. Kelvin Njagi

---

*VEHISAFE — Towards safer roads through intelligent vehicle monitoring and predictive road safety technology.*
