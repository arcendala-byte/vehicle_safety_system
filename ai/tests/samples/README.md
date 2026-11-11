
---

## 📄 File 2: `ai/tests/samples/README.md` (NEW)

**Full path:** `C:\xampp2\htdocs\vehicle_safety_system\ai\tests\samples\README.md`

```markdown
# Test Samples

Place test images here for the detector unit tests.

## Naming Convention

- `driver_with_seatbelt.jpg` — a person in a car wearing a seatbelt
- `driver_no_seatbelt.jpg` — a person in a car without a seatbelt
- `driver_with_phone.jpg` — a driver holding a phone
- `driver_drowsy.jpg` — eyes closed or yawning
- `empty_frame.jpg` — no person (should return "uncertain")

Tests will skip gracefully if a sample is missing.

## Where to Get Sample Images

For quick testing, use:
- Your own webcam (take a selfie with/without a seatbelt)
- Public image datasets (do not commit copyrighted images)
- Google Image search — but only for personal test usage

Do NOT commit large image files to git. Sample images are
`.gitignore`-excluded by default.