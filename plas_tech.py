import time
import os
import json
import OPi.GPIO as GPIO

# Setup GPIO mode
GPIO.setmode(GPIO.BOARD)
GPIO.setwarnings(False)

# Pin Definitions
PIN_IR = 7          # IR Sensor Input
PIN_IND = 15        # Metal Sensor Input (Inductive Proximity Sensor)
PIN_FAN = 11        # Fan Relay Control Pin

GPIO.setup(PIN_IR, GPIO.IN)
GPIO.setup(PIN_IND, GPIO.IN)
GPIO.setup(FAN_PIN, GPIO.OUT)

# Turn ON fan continuously
GPIO.output(PIN_FAN, GPIO.HIGH)

STATUS_FILE = "/var/www/html/plastech/status.json"

# Cooldown tracking variable
last_trigger_time = 0
COOLDOWN_SECONDS = 1.5

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
        return {"bottles": 0, "active": False, "seconds": 0, "metal_rejected": 0}
    try:
        with open(STATUS_FILE, "r") as f:
            data = json.load(f)
            if "metal_rejected" not in data:
                data["metal_rejected"] = 0
            return data
    except Exception:
        return {"bottles": 0, "active": False, "seconds": 0, "metal_rejected": 0}

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
    current_data = {"bottles": 0, "active": False, "seconds": 0, "metal_rejected": 0}
    write_status(current_data)

try:
    last_ir_state = GPIO.input(PIN_IR)
    print(f"[INFO] Initial IR Sensor State: {last_ir_state}")

    while True:
        # Ensure fan stays on
        GPIO.output(PIN_FAN, GPIO.HIGH)

        # Read web status to check if insertion window is active
        status = read_status()
        is_active = status.get("active", False)

        current_ir_state = GPIO.input(PIN_IR)
        current_time = time.time()

        # Only check items if the pop-up modal is open (active == true)
        if is_active:
            # Check for transition on IR sensor AND ensure the 1.5-second cooldown has passed
            if last_ir_state != current_ir_state and (current_time - last_trigger_time) > COOLDOWN_SECONDS:
                # Give a tiny pause to let the metal sensor register if it's metallic
                time.sleep(0.02)
                metal_triggered = (GPIO.input(PIN_IND) == 0) # Assuming active low for inductive sensor

                if metal_triggered:
                    # It's metal! Do NOT add a bottle. Track rejection instead.
                    status["metal_rejected"] = status.get("metal_rejected", 0) + 1
                    print(f"[REJECTED] Metal object detected! Bottle count NOT increased.")
                else:
                    # It's plastic/valid! Add to bottle count.
                    status["bottles"] = status.get("bottles", 0) + 1
                    status["seconds"] = calculate_minutes(status["bottles"]) * 60
                    print(f"[TRIGGERED] Plastic bottle detected! Total: {status['bottles']}")

                write_status(status)
                last_trigger_time = time.time() # Reset cooldown timestamp after a successful check

        last_ir_state = current_ir_state
        time.sleep(0.05)

except KeyboardInterrupt:
    print("\n[EXITING] Cleaning up GPIO...")
    GPIO.cleanup()
