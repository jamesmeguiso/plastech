import time
import os
import json
import OPi.GPIO as GPIO

# Setup GPIO mode
GPIO.setmode(GPIO.BOARD)
GPIO.setwarnings(False)

# Pin Definitions
PIN_IR = 7        # IR Sensor Input
PIN_FAN = 13      # Fan Relay Control Pin

GPIO.setup(PIN_IR, GPIO.IN)
GPIO.setup(PIN_FAN, GPIO.OUT)

# Turn ON fan continuously
GPIO.output(PIN_FAN, GPIO.HIGH)

STATUS_FILE = "/var/www/html/plastech/status.json"

def calculate_minutes(bottles):
    if bottles <= 0: return 0
    if bottles == 1: return 10
    if bottles == 2: return 20
    if bottles == 3: return 30
    if bottles == 4: return 40
    if bottles == 5: return 60    # 1 Hour
    if bottles == 6: return 80    # 1 Hr 20 Mins
    if bottles == 7: return 100   # 1 Hr 40 Mins
    if bottles == 8: return 120   # 2 Hours
    if bottles == 9: return 150   # 2 Hr 30 Mins
    if bottles == 10: return 180  # 3 Hours
    if bottles == 15: return 300  # 5 Hours
    if bottles == 20: return 600  # 10 Hours
    return bottles * 10

def read_status():
    if not os.path.exists(STATUS_FILE):
        return {"bottles": 0, "active": False, "seconds": 0}
    try:
        with open(STATUS_FILE, "r") as f:
            return json.load(f)
    except Exception:
        return {"bottles": 0, "active": False, "seconds": 0}

def write_status(data):
    try:
        with open(STATUS_FILE, "w") as f:
            json.dump(data, f)
    except Exception:
        pass

print("========================================")
print("   PLAS-TECH CONTROL SYSTEM RUNNING     ")
print("========================================")

# Initialize status file on boot
current_data = read_status()
if "bottles" not in current_data:
    current_data = {"bottles": 0, "active": False, "seconds": 0}
    write_status(current_data)

try:
    last_state = GPIO.input(PIN_IR)
    print(f"[INFO] Initial IR Sensor State: {last_state}")

    while True:
        # Ensure fan stays on
        GPIO.output(PIN_FAN, GPIO.HIGH)

        # Read web status to check if insertion window is active
        status = read_status()
        is_active = status.get("active", False)

        current_state = GPIO.input(PIN_IR)

        # Only count bottles if the pop-up modal is open (active == true)
        if is_active:
            if last_state != current_state:
                status["bottles"] = status.get("bottles", 0) + 1
                status["seconds"] = calculate_minutes(status["bottles"]) * 60
                print(f"[TRIGGERED] Bottle detected! Total: {status['bottles']}")
                write_status(status)
                time.sleep(0.5) # Debounce delay

        last_state = current_state
        time.sleep(0.05)

except KeyboardInterrupt:
    print("\n[EXITING] Cleaning up GPIO...")
    GPIO.cleanup()
