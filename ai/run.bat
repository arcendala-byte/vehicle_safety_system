@echo off
REM ============================================================
REM VEHISAFE AI SERVICE — Windows launcher
REM ============================================================
REM Run this from the "ai/" folder.
REM Assumes a Python virtual environment in "venv/" exists.
REM ============================================================

cd /d "%~dp0"

if not exist "venv\Scripts\activate.bat" (
    echo.
    echo [ERROR] No virtual environment found at ai\venv\
    echo.
    echo Run these commands first:
    echo     cd C:\xampp2\htdocs\vehicle_safety_system\ai
    echo     python -m venv venv
    echo     venv\Scripts\activate.bat
    echo     pip install -r requirements.txt
    echo.
    pause
    exit /b 1
)

call venv\Scripts\activate.bat
echo.
echo ============================================================
echo VEHISAFE AI Service
echo ============================================================
echo.
python app.py

pause