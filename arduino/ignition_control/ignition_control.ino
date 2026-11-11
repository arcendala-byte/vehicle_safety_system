/*
 * ============================================================
 * VEHISAFE — IGNITION CONTROL (Arduino Uno)
 * ============================================================
 * Listens on USB serial for commands:
 *   "IGNITION:ON\n"   → Relay ON (ignition enabled)
 *   "IGNITION:OFF\n"  → Relay OFF (ignition disabled)
 *   "STATUS:?\n"      → Responds with "STATE:ON" or "STATE:OFF"
 *
 * Hardware:
 *   - Arduino Uno
 *   - 1-channel 5V relay module
 *   - Relay IN  → Arduino Pin 8
 *   - Relay VCC → Arduino 5V
 *   - Relay GND → Arduino GND
 *
 * ⚠️  SAFETY:
 *   DO NOT connect to a real vehicle ignition system.
 *   Use an LED for pure simulation, or a relay for a
 *   low-voltage demonstration (e.g., powering a small bulb).
 * ============================================================
 */

const int RELAY_PIN = 8;
bool ignitionOn = false;

void setup() {
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);  // Start with ignition OFF
  Serial.begin(9600);
  Serial.println("BOOT:Arduino ignition controller ready");
  Serial.println("STATE:OFF");
}

void loop() {
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();

    if (command == "IGNITION:ON") {
      digitalWrite(RELAY_PIN, HIGH);
      ignitionOn = true;
      Serial.println("STATE:ON");
    }
    else if (command == "IGNITION:OFF") {
      digitalWrite(RELAY_PIN, LOW);
      ignitionOn = false;
      Serial.println("STATE:OFF");
    }
    else if (command == "STATUS:?") {
      Serial.print("STATE:");
      Serial.println(ignitionOn ? "ON" : "OFF");
    }
    else if (command.startsWith("PING")) {
      Serial.println("PONG");
    }
  }
}
