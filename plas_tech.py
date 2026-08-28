import time
import os
import json
import OPi.GPIO as GPIO

GPIO.setmode(GPIO.BOARD)
GPIO.setwarnings(False)

PIN_IR = 7
PIN_IND = 15
PIN_FAN = 11

GPIO.setup(PIN_IR, GPIO.IN)
GPIO.setup(PIN_IND, GPIO.IN)
GPIO.setup(PIN_FAN, GPIO.OUT)
GPIO.output(PIN_FAN, GPIO.HIGH)

STATUS_DIR = "/var/www/html/plastech"
STATUS_FILE = os.path.join(STATUS_DIR, "status.json")

last_ir_time = 0
last_metal_time = 0
IR_COOLDOWN = 2.0
METAL_COOLDOWN = 3.0

def calculate_minutes(bottles):
    if bottles <= 0: return 0
    if bottles == 1: return 10
    if bottles == 2: return 20
    if bottles == 3: return 30
    if bottles == 5: return 60
    if bottles == 10: return 180
    return bottles * 10

def read_status():
    if not os.path.exists(STATUS_FILE):
        return {}
    try:
        with open(STATUS_FILE, "r") as f:
            data = json.load(f)
            return data if isinstance(data, dict) else {}
    except Exception:
        return {}

def write_status(data):
    try:
        if not os.path.exists(STATUS_DIR):
            os.makedirs(STATUS_DIR, exist_ok=True)

        with open(STATUS_FILE, "w") as f:
            json.dump(data, f)
        os.chmod(STATUS_FILE, 0o777)
    except Exception as e:
        print(f"[ERROR] Could not write status: {e}")

def grant_internet(ip):
    if ip:
        check_cmd = f"sudo iptables -C FORWARD -i end0 -s {ip} -j ACCEPT 2>/dev/null"
        if os.system(check_cmd) != 0:
            os.system(f"sudo iptables -I FORWARD 1 -i end0 -s {ip} -j ACCEPT")
            print(f"[UNLOCKED] Firewall opened for IP: {ip}")

def revoke_internet(ip):
    if ip:
        while os.system(f"sudo iptables -D FORWARD -i end0 -s {ip} -j ACCEPT 2>/dev/null") == 0:
            pass
        print(f"[LOCKED] Firewall closed for IP: {ip}")

print("========================================")
print("    PLAS-TECH SENSOR SYSTEM RUNNING       ")
print("========================================")

try:
    last_ir_state = GPIO.input(PIN_IR)
    counter = 0

    while True:
        GPIO.output(PIN_FAN, GPIO.HIGH)
        status_data = read_status()
        current_time = time.time()
        current_ir_state = GPIO.input(PIN_IR)

        counter += 1
        if counter >= 20:
            counter = 0
            for ip, session_data in status_data.items():
                if isinstance(session_data, dict):
                    if session_data.get("seconds", 0) > 0:
                        session_data["seconds"] -= 1
                        grant_internet(ip)
                        if session_data["seconds"] <= 0:
                            session_data["seconds"] = 0
                            revoke_internet(ip)
                            print(f"[LOCKED] Time expired for IP {ip}")

        active_ip = None
        for ip, session_data in status_data.items():
            if isinstance(session_data, dict) and session_data.get("active", False):
                active_ip = ip
                break

        if active_ip:
            metal_triggered = (GPIO.input(PIN_IND) == 1)
            if metal_triggered:
                last_metal_time = current_time

            if last_ir_state != current_ir_state:
                if (current_time - last_metal_time) < METAL_COOLDOWN:
                    if active_ip in status_data and isinstance(status_data[active_ip], dict):
                        status_data[active_ip]["metal_rejected"] = status_data[active_ip].get("metal_rejected", 0) + 1
                elif metal_triggered:
                    if active_ip in status_data and isinstance(status_data[active_ip], dict):
                        status_data[active_ip]["metal_rejected"] = status_data[active_ip].get("metal_rejected", 0) + 1
                else:
                    if (current_time - last_ir_time) >= IR_COOLDOWN:
                        last_ir_time = current_time
                        if active_ip in status_data and isinstance(status_data[active_ip], dict):
                            status_data[active_ip]["bottles"] = status_data[active_ip].get("bottles", 0) + 1
                            status_data[active_ip]["seconds"] = calculate_minutes(status_data[active_ip]["bottles"]) * 60
                            grant_internet(active_ip)
                            print(f"[TRIGGERED] Bottle added for IP {active_ip}. Total: {status_data[active_ip]['bottles']}")

        write_status(status_data)
        last_ir_state = current_ir_state
        time.sleep(0.05)

except KeyboardInterrupt:
    GPIO.cleanup()
