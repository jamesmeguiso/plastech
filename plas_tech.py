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
GPIO.setup(PIN_FAN, GPIO.OUT)

# Turn ON fan continuously
GPIO.output(PIN_FAN, GPIO.HIGH)

STATUS_FILE = "/var/www/html/plastech/status.json"

# Cooldown tracking variables
last_ir_time = 0
last_metal_time = 0

IR_COOLDOWN = 2.0      # 2 seconds IR cooldown
METAL_COOLDOWN = 3.0   # 3 seconds complete metal block window

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
        return {"bottles": 0, "active": False, "seconds": 0, "metal_rejected": 0, "client_ip": ""}
    try:
        with open(STATUS_FILE, "r") as f:
            data = json.load(f)
            if "metal_rejected" not in data:
                data["metal_rejected"] = 0
            if "client_ip" not in data:
                data["client_ip"] = ""
            return data
    except Exception:
        return {"bottles": 0, "active": False, "seconds": 0, "metal_rejected": 0, "client_ip": ""}

def write_status(data):
    try:
        temp_file = STATUS_FILE + ".tmp"
        with open(temp_file, "w") as f:
            json.dump(data, f)
        os.replace(temp_file, STATUS_FILE)
    except Exception:
        pass

def grant_internet(ip):
    if ip:
        os.system(f"sudo iptables -t nat -I PREROUTING -s {ip} -j ACCEPT")
        os.system(f"sudo iptables -I FORWARD -s {ip} -j ACCEPT")
        print(f"[UNLOCKED] Internet granted for IP: {ip}")

print("========================================")
print("    PLAS-TECH CONTROL SYSTEM RUNNING    ")
print("========================================")

# Initialize status file on boot
current_data = read_status()
if "bottles" not in current_data:
    current_data = {"bottles": 0, "active": False, "seconds": 0, "metal_rejected": 0, "client_ip": ""}
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
        client_ip = status.get("client_ip", "")

        current_ir_state = GPIO.input(PIN_IR)
        current_time = time.time()

        # Only check items if the pop-up modal is open (active == true)
        if is_active:
            metal_triggered = (GPIO.input(PIN_IND) == 1)

            if metal_triggered:
                last_metal_time = current_time

            # Check for transition on IR sensor
            if last_ir_state != current_ir_state:
                # Check if we are inside the 3-second block window after any metal detection
                if (current_time - last_metal_time) < METAL_COOLDOWN:
                    print(f"[BLOCKED] IR triggered within 3s metal block window. Web updated.")
                    status["metal_rejected"] = status.get("metal_rejected", 0) + 1
                    write_status(status)
                elif metal_triggered:
                    # Metal is actively present right now
                    status["metal_rejected"] = status.get("metal_rejected", 0) + 1
                    write_status(status)
                    print(f"[REJECTED] Metal object detected! Web updated.")
                else:
                    # Check the normal 2-second IR cooldown
                    if (current_time - last_ir_time) < IR_COOLDOWN:
                        print(f"[IGNORED] IR cooldown active (2s).")
                    else:
                        # Valid plastic bottle!
                        last_ir_time = current_time
                        status["bottles"] = status.get("bottles", 0) + 1
                        status["seconds"] = calculate_minutes(status["bottles"]) * 60
                        write_status(status)
                        print(f"[TRIGGERED] Plastic bottle detected! Total: {status['bottles']}")
                        
                        # Trigger network access unlock for the user's IP
                        if client_ip:
                            grant_internet(client_ip)

        last_ir_state = current_ir_state
        time.sleep(0.05)

except KeyboardInterrupt:
    print("\n[EXITING] Cleaning up GPIO...")
    GPIO.cleanup()
