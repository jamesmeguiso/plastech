import time
import os
import json
import fcntl
import OPi.GPIO as GPIO

GPIO.setmode(GPIO.BOARD)
GPIO.setwarnings(False)

PIN_IR = 7
PIN_IND = 15
PIN_FAN = 11

GPIO.setup(PIN_IR, GPIO.IN)
GPIO.setup(PIN_IND, GPIO.IN)
GPIO.setup(PIN_FAN, GPIO.OUT)
GPIO.output(PIN_FAN, GPIO.HIGH)  # Fan starts OFF (relay is active-low)

STATUS_DIR = "/var/www/html/plastech"
STATUS_FILE = os.path.join(STATUS_DIR, "status.json")
LOCK_FILE = os.path.join(STATUS_DIR, "status.lock")

THERMAL_ZONE = "/sys/class/thermal/thermal_zone0/temp"
FAN_ON_TEMP_C = 29.0
FAN_OFF_TEMP_C = 25.0

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

def get_cpu_temp_c():
    try:
        with open(THERMAL_ZONE, "r") as f:
            raw = f.read().strip()
        return int(raw) / 1000.0
    except Exception as e:
        print(f"[ERROR] Could not read CPU temp: {e}")
        return None

def update_fan_state(current_fan_state):
    temp = get_cpu_temp_c()
    if temp is None:
        return current_fan_state

    if temp >= FAN_ON_TEMP_C and not current_fan_state:
        GPIO.output(PIN_FAN, GPIO.LOW)
        print(f"[FAN] ON at {temp:.1f}C")
        return True
    elif temp <= FAN_OFF_TEMP_C and current_fan_state:
        GPIO.output(PIN_FAN, GPIO.HIGH)
        print(f"[FAN] OFF at {temp:.1f}C")
        return False

    return current_fan_state

def grant_internet(ip):
    if ip:
        check_cmd = f"iptables -C FORWARD -i end0 -s {ip} -j ACCEPT 2>/dev/null"
        if os.system(check_cmd) != 0:
            os.system(f"iptables -I FORWARD 1 -i end0 -s {ip} -j ACCEPT")
            print(f"[UNLOCKED] Firewall opened for IP: {ip}")

def revoke_internet(ip):
    if ip:
        while os.system(f"iptables -D FORWARD -i end0 -s {ip} -j ACCEPT 2>/dev/null") == 0:
            pass
        os.system(f"conntrack -D -s {ip} 2>/dev/null")
        print(f"[LOCKED] Firewall closed for IP: {ip}")

def read_status_locked(lock_handle):
    if not os.path.exists(STATUS_FILE):
        return {}
    try:
        with open(STATUS_FILE, "r") as f:
            data = json.load(f)
            return data if isinstance(data, dict) else {}
    except Exception:
        return {}

def write_status_locked(data):
    try:
        if not os.path.exists(STATUS_DIR):
            os.makedirs(STATUS_DIR, exist_ok=True)
        with open(STATUS_FILE, "w") as f:
            json.dump(data, f)
        os.chmod(STATUS_FILE, 0o777)
    except Exception as e:
        print(f"[ERROR] Could not write status: {e}")

print("========================================")
print("    PLAS-TECH SENSOR SYSTEM RUNNING       ")
print("========================================")

try:
    last_ir_state = GPIO.input(PIN_IR)
    counter = 0
    fan_state = False
    temp_check_counter = 0
    last_ir_time = 0
    last_metal_time = 0

    while True:
        current_time = time.time()
        current_ir_state = GPIO.input(PIN_IR)

        temp_check_counter += 1
        if temp_check_counter >= 20:
            temp_check_counter = 0
            fan_state = update_fan_state(fan_state)

        lock_fd = open(LOCK_FILE, "w")
        fcntl.flock(lock_fd, fcntl.LOCK_EX)

        status_data = read_status_locked(lock_fd)

        counter += 1
        if counter >= 20:
            counter = 0
            for token, session_data in status_data.items():
                if isinstance(session_data, dict):
                    ip = session_data.get("current_ip")
                    if session_data.get("seconds", 0) > 0:
                        session_data["seconds"] -= 1
                        grant_internet(ip)
                        if session_data["seconds"] <= 0:
                            session_data["seconds"] = 0
                            revoke_internet(ip)
                            print(f"[LOCKED] Time expired for token {token} (ip {ip})")

        active_token = None
        active_ip = None
        for token, session_data in status_data.items():
            if isinstance(session_data, dict) and session_data.get("active", False):
                active_token = token
                active_ip = session_data.get("current_ip")
                break

        if active_token:
            metal_triggered = (GPIO.input(PIN_IND) == 1)
            if metal_triggered:
                last_metal_time = current_time

            if last_ir_state != current_ir_state:
                if (current_time - last_metal_time) < METAL_COOLDOWN:
                    status_data[active_token]["metal_rejected"] = status_data[active_token].get("metal_rejected", 0) + 1
                elif metal_triggered:
                    status_data[active_token]["metal_rejected"] = status_data[active_token].get("metal_rejected", 0) + 1
                else:
                    if (current_time - last_ir_time) >= IR_COOLDOWN:
                        last_ir_time = current_time

                        status_data[active_token]["bottles"] = status_data[active_token].get("bottles", 0) + 1
                        status_data[active_token]["session_bottles"] = status_data[active_token].get("session_bottles", 0) + 1

                        base_seconds = status_data[active_token].get("base_seconds", 0)
                        session_bottles = status_data[active_token]["session_bottles"]
                        status_data[active_token]["seconds"] = base_seconds + calculate_minutes(session_bottles) * 60

                        grant_internet(active_ip)
                        print(f"[TRIGGERED] Bottle added for token {active_token} (ip {active_ip}). "
                              f"Session bottles: {session_bottles}, Lifetime: {status_data[active_token]['bottles']}, "
                              f"Seconds now: {status_data[active_token]['seconds']}")

        write_status_locked(status_data)

        fcntl.flock(lock_fd, fcntl.LOCK_UN)
        lock_fd.close()

        last_ir_state = current_ir_state
        time.sleep(0.05)

except KeyboardInterrupt:
    GPIO.output(PIN_FAN, GPIO.HIGH)
    GPIO.cleanup()
