#!/usr/bin/env python3
"""
agv_pi4_final.py — Robot AGV Sagemcom El Zahra
===============================================
LOGIQUE COMPLÈTE :
  1. IR détecte bobine → LED rouge → attendre scan QR
  2. Scan QR conforme picklist → LED VERTE + buzzer slot ✅
     - Quantité incomplète → interface ORANGE
     - Quantité complète   → interface VERTE
     - Toute la picklist complète → confirmation automatique
  3. QR non conforme → LED rouge + buzzer principal ❌
     - Rappel buzzer principal toutes les 60s
  4. Bobine retirée après confirmation → décrémenter + cycle recommence

BUZZERS :
  - Slot   → bipe si bobine CONFIRMÉE ✅
  - Main   → bipe si ERREUR ❌ ou rappel

GPIO (BCM) :
  BUZZER_MAIN=19
  A: rouge=14 verte=15 ir=16 buzzer=17
  B: rouge=27 verte=22 ir=23 buzzer=24
  C: rouge=5  verte=6  ir=12 buzzer=13
  IR HIGH = bobine détectée
"""

import RPi.GPIO as GPIO
import time, threading, requests, serial, sys, logging, json

# ══════════════════════════════════════════════════════════════
#  CONFIG
# ══════════════════════════════════════════════════════════════
SERVER_IP    = "192.168.1.235"
BASE         = f"http://{SERVER_IP}:80/robot-inventaire"
KEY          = "AGV_SAGEMCOM_SECRET_2025"
URL_SCAN     = f"{BASE}/api.php"
URL_PING     = f"{BASE}/emergency_api.php"
URL_CMDS     = f"{BASE}/get_robot_commands.php"
URL_CONFIRM  = f"{BASE}/confirm_command.php"
URL_SLOT     = f"{BASE}/slot_status.php"
URL_PICKLIST = f"{BASE}/get_active_picklist.php"
URL_PL_DONE  = f"{BASE}/confirm_picklist_done.php"

SCANNER_PORT  = "/dev/ttyACM0"
SCANNER_BAUD  = 9600
HTTP_TIMEOUT  = 3.0
QR_COOLDOWN   = 2.0
BUZZ_INTERVAL = 60.0

# ══════════════════════════════════════════════════════════════
#  GPIO
# ══════════════════════════════════════════════════════════════
BUZZER_MAIN = 19
SLOTS = {
    "A": {"id":1,"nom":"Slot 1","rouge":14,"verte":15,"ir":16,"buzzer":17},
    "B": {"id":2,"nom":"Slot 2","rouge":27,"verte":22,"ir":23,"buzzer":24},
    "C": {"id":3,"nom":"Slot 3","rouge":5, "verte":6, "ir":12,"buzzer":13},
}

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(sys.stdout),
              logging.FileHandler("/tmp/agv.log",encoding="utf-8")]
)
log = logging.getLogger("AGV")

# ══════════════════════════════════════════════════════════════
#  ÉTAT GLOBAL
# ══════════════════════════════════════════════════════════════
running      = True
is_emergency = False
last_ping_t  = time.time()
last_qr_code = None
last_qr_time = 0.0

# État slot : empty | waiting_scan | confirmed | error
slot_state = {
    k: {"status":"empty","barcode":None,"ir":False,
        "last_buzz":0.0,"error_msg":""}
    for k in SLOTS
}

# Picklist cache — { reference: {quantite, loaded} }
pl_lock    = threading.Lock()
pl_header  = {"id": None, "code": None}
pl_lines   = {}   # reference -> {"quantite":N, "loaded":M}

# QR pending (scanné avant de poser la bobine)
pq_lock = threading.Lock()
pq      = {"barcode":None,"time":0.0,"ok":False}

# ══════════════════════════════════════════════════════════════
#  GPIO SETUP
# ══════════════════════════════════════════════════════════════
def setup_gpio():
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    GPIO.setup(BUZZER_MAIN, GPIO.OUT, initial=GPIO.LOW)
    for s in SLOTS.values():
        GPIO.setup(s["rouge"],  GPIO.OUT, initial=GPIO.LOW)
        GPIO.setup(s["verte"],  GPIO.OUT, initial=GPIO.LOW)
        GPIO.setup(s["buzzer"], GPIO.OUT, initial=GPIO.LOW)
        GPIO.setup(s["ir"],     GPIO.IN,  pull_up_down=GPIO.PUD_UP)
        GPIO.output(s["rouge"], GPIO.HIGH)
    log.info("[GPIO] OK")

def gpio_cleanup():
    for s in SLOTS.values():
        GPIO.output(s["verte"],GPIO.LOW)
        GPIO.output(s["rouge"],GPIO.LOW)
        GPIO.output(s["buzzer"],GPIO.LOW)
    GPIO.output(BUZZER_MAIN,GPIO.LOW)
    GPIO.cleanup()

# ══════════════════════════════════════════════════════════════
#  LEDS & BUZZERS
# ══════════════════════════════════════════════════════════════
def led_vert(key):
    GPIO.output(SLOTS[key]["rouge"],GPIO.LOW)
    GPIO.output(SLOTS[key]["verte"],GPIO.HIGH)

def led_rouge(key):
    GPIO.output(SLOTS[key]["verte"],GPIO.LOW)
    GPIO.output(SLOTS[key]["rouge"],GPIO.HIGH)

def beep(pin, n=2, dur=0.12):
    for _ in range(n):
        GPIO.output(pin,GPIO.HIGH); time.sleep(dur)
        GPIO.output(pin,GPIO.LOW);  time.sleep(0.08)

def bip_slot(key):       beep(SLOTS[key]["buzzer"], 2, 0.12)  # confirmation
def bip_pose(key):       beep(SLOTS[key]["buzzer"], 1, 0.07)  # bobine posée
def bip_erreur():        # erreur principale
    GPIO.output(BUZZER_MAIN,GPIO.HIGH); time.sleep(1.0)
    GPIO.output(BUZZER_MAIN,GPIO.LOW)
def bip_rappel():        beep(BUZZER_MAIN, 1, 0.5)
def bip_complet():       beep(BUZZER_MAIN, 3, 0.15)  # picklist complète!
def bip_demarrage():
    for s in SLOTS.values(): beep(s["buzzer"],1,0.07); time.sleep(0.07)
    beep(BUZZER_MAIN,1,0.4)

# ══════════════════════════════════════════════════════════════
#  HTTP
# ══════════════════════════════════════════════════════════════
def http_post(url, data):
    try: return requests.post(url,data=data,timeout=HTTP_TIMEOUT).json()
    except: return None

def http_get(url, params=None):
    try: return requests.get(url,params=params,timeout=HTTP_TIMEOUT).json()
    except: return None

# ══════════════════════════════════════════════════════════════
#  PICKLIST
# ══════════════════════════════════════════════════════════════
def load_picklist():
    r = http_get(URL_PICKLIST, {"key":KEY})
    if not r or not r.get("lines"):
        return False
    with pl_lock:
        pl_header["id"]   = r.get("picklist_id")
        pl_header["code"] = r.get("picklist_code")
        pl_lines.clear()
        for line in r["lines"]:
            ref = line["reference"]
            pl_lines[ref] = {
                "quantite": int(line["quantite"]),
                "loaded":   0,
            }
    log.info("[PL] %d références chargées", len(pl_lines))
    return True

def pl_increment(ref):
    with pl_lock:
        if ref in pl_lines:
            pl_lines[ref]["loaded"] += 1
            return pl_lines[ref]["loaded"], pl_lines[ref]["quantite"]
    return 0, 0

def pl_decrement(ref):
    with pl_lock:
        if ref in pl_lines:
            pl_lines[ref]["loaded"] = max(0, pl_lines[ref]["loaded"] - 1)
            return pl_lines[ref]["loaded"], pl_lines[ref]["quantite"]
    return 0, 0

def pl_is_complete():
    with pl_lock:
        if not pl_lines: return False
        return all(v["loaded"] >= v["quantite"] for v in pl_lines.values())

def pl_get_status():
    """Retourne la liste des références avec statut : pending/partial/complete"""
    with pl_lock:
        result = []
        for ref, v in pl_lines.items():
            loaded   = v["loaded"]
            needed   = v["quantite"]
            if loaded == 0:      status = "pending"
            elif loaded < needed: status = "partial"
            else:                 status = "complete"
            result.append({"reference":ref,"loaded":loaded,"quantite":needed,"status":status})
        overall = "green" if all(r["status"]=="complete" for r in result) else \
                  "orange" if any(r["status"] in ("partial","complete") for r in result) else "red"
    return overall, result

def push_status():
    """Envoie l'état complet au serveur → interface magasinier."""
    overall, lines = pl_get_status()
    slots_data = []
    for key, st in slot_state.items():
        slots_data.append({
            "slot":    SLOTS[key]["id"],
            "key":     key,
            "status":  st["status"],
            "barcode": st["barcode"] or "",
            "ir":      st["ir"],
        })
    threading.Thread(
        target=http_post,
        args=(URL_SLOT, {
            "key":              KEY,
            "slots":            json.dumps(slots_data),
            "picklist_overall": overall,
            "picklist_lines":   json.dumps(lines),
        }),
        daemon=True
    ).start()

def auto_confirm_picklist():
    """Confirme automatiquement la picklist si tout est chargé."""
    if pl_is_complete() and pl_header.get("id"):
        log.info("[PL] Picklist complète → confirmation automatique")
        print("\n  🎉 PICKLIST COMPLÈTE — Confirmation automatique !")
        bip_complet()
        threading.Thread(
            target=http_post,
            args=(URL_PL_DONE, {"key":KEY,"picklist_id":pl_header["id"]}),
            daemon=True
        ).start()

# ══════════════════════════════════════════════════════════════
#  TRAITEMENT SLOT
# ══════════════════════════════════════════════════════════════
def confirmer(key, barcode):
    loaded, needed = pl_increment(barcode)
    slot_state[key]["status"]    = "confirmed"
    slot_state[key]["barcode"]   = barcode
    slot_state[key]["error_msg"] = ""

    led_vert(key)
    bip_slot(key)   # buzzer SLOT ✅

    if loaded >= needed:
        print(f"  ✅ {SLOTS[key]['nom']} — CONFORME : {barcode}")
        print(f"     Quantité : {loaded}/{needed} — COMPLET ✅")
    else:
        print(f"  🟡 {SLOTS[key]['nom']} — CONFORME : {barcode}")
        print(f"     Quantité : {loaded}/{needed} — {needed-loaded} bobine(s) encore nécessaire(s)")

    push_status()
    auto_confirm_picklist()

def rejeter(key, barcode, raison):
    slot_state[key]["status"]    = "error"
    slot_state[key]["barcode"]   = barcode
    slot_state[key]["error_msg"] = raison
    slot_state[key]["last_buzz"] = time.time()

    led_rouge(key)
    bip_erreur()    # buzzer PRINCIPAL ❌

    print(f"  ❌ {SLOTS[key]['nom']} — NON CONFORME : {barcode}")
    print(f"     Raison : {raison}")
    print(f"     → Retirer cette bobine et en placer une correcte !")

    push_status()

# ══════════════════════════════════════════════════════════════
#  SCAN QR
# ══════════════════════════════════════════════════════════════
def process_scan(barcode: str):
    global last_qr_code, last_qr_time
    barcode = barcode.strip()
    if not barcode or len(barcode) < 2: return

    now = time.time()
    if barcode == last_qr_code and (now - last_qr_time) < QR_COOLDOWN: return
    last_qr_code = barcode
    last_qr_time = now

    log.info("[SCAN] %s", barcode)
    print(f"\n  [QR] Code : {barcode}")

    # Slots avec bobine présente en attente
    slots_att = [k for k,st in slot_state.items()
                 if st["ir"] and st["status"] in ("waiting_scan","error","empty")]

    if not slots_att:
        # Mémoriser le QR — la bobine viendra après
        with pq_lock:
            pq["barcode"] = barcode
            pq["time"]    = now
            pq["ok"]      = False
        print(f"  [QR] Aucun slot en attente — QR mémorisé 30s")
        print(f"       → Poser la bobine maintenant")

    # Vérifier picklist
    result = http_post(URL_SCAN, {"key":KEY,"barcode":barcode,"zone":"zone1"})
    if result is None:
        # Mode local
        with pl_lock: ok_local = barcode in pl_lines
        result = {"signal":"green" if ok_local else "red",
                  "message":"Cache local" if ok_local else "Référence absente"}

    signal = result.get("signal","red")
    msg    = result.get("message","")

    if signal == "green":
        with pq_lock:
            pq["barcode"] = barcode
            pq["time"]    = now
            pq["ok"]      = True
        if slots_att:
            confirmer(slots_att[0], barcode)
        else:
            print(f"  ✅ QR conforme — posez la bobine dans les 30 secondes")
    else:
        raison = msg or "Référence absente de la picklist"
        if slots_att:
            rejeter(slots_att[0], barcode, raison)
        else:
            print(f"  ❌ QR non conforme : {raison}")
            bip_erreur()

# ══════════════════════════════════════════════════════════════
#  THREAD IR
# ══════════════════════════════════════════════════════════════
def ir_thread():
    global running
    log.info("[IR] Thread démarré")
    prev = {k:False for k in SLOTS}

    while running:
        now = time.time()
        for key, pins in SLOTS.items():
            detected = (GPIO.input(pins["ir"]) == GPIO.HIGH)
            st = slot_state[key]

            if detected != prev[key]:
                prev[key] = detected
                st["ir"]  = detected

                if detected:
                    # ── Bobine posée ──
                    log.info("[IR] %s → DETECTE", key)
                    print(f"\n  [IR] {pins['nom']} — Bobine posée")

                    if st["status"] in ("empty","error"):
                        st["status"] = "waiting_scan"
                    bip_pose(key)

                    # QR déjà scanné et valide ?
                    with pq_lock:
                        qr_ok    = pq["ok"]
                        qr_fresh = (now - pq["time"]) < 30.0
                        qr_bc    = pq["barcode"]

                    if qr_ok and qr_fresh and qr_bc:
                        with pq_lock:
                            pq["ok"]=False; pq["barcode"]=None
                        confirmer(key, qr_bc)
                    else:
                        led_rouge(key)
                        print(f"       → Scanner le QR code maintenant !")

                    push_status()

                else:
                    # ── Bobine retirée ──
                    log.info("[IR] %s → RETIRE", key)
                    print(f"\n  [IR] {pins['nom']} — Bobine retirée")

                    if st["status"] == "confirmed" and st["barcode"]:
                        loaded, needed = pl_decrement(st["barcode"])
                        print(f"     ⚠️  Bobine confirmée retirée ! {loaded}/{needed}")
                        print(f"     → Replacer et rescanner !")
                        bip_erreur()

                    st["status"]    = "empty"
                    st["barcode"]   = None
                    st["error_msg"] = ""
                    st["last_buzz"] = 0.0
                    led_rouge(key)
                    push_status()

            # Rappel buzzer si erreur > 60s
            if st["status"] == "error":
                if (now - st["last_buzz"]) >= BUZZ_INTERVAL:
                    st["last_buzz"] = now
                    bip_rappel()
                    print(f"\n  ⏰ RAPPEL : {pins['nom']} — Bobine non conforme !")

        # Expiration QR pending
        with pq_lock:
            if pq["ok"] and pq["time"] > 0 and (now-pq["time"]) > 30.0:
                log.info("[IR] QR expiré")
                print("\n  ⏰ QR expiré — Rescanner !")
                pq["ok"]=False; pq["barcode"]=None; pq["time"]=0.0
                bip_erreur()

        time.sleep(0.1)

    log.info("[IR] Arrêté")

# ══════════════════════════════════════════════════════════════
#  THREAD SCANNER
# ══════════════════════════════════════════════════════════════
def scanner_thread():
    global running
    log.info("[Scanner] Ouverture %s", SCANNER_PORT)
    ser = None
    for i in range(5):
        try:
            ser = serial.Serial(SCANNER_PORT, SCANNER_BAUD, timeout=0.1)
            log.info("[Scanner] Connecté")
            break
        except Exception as e:
            log.warning("[Scanner] Tentative %d/5 — %s", i+1, e)
            time.sleep(2)

    if ser is None:
        log.error("[Scanner] Échec → mode clavier")
        print("  → Taper QR + Entrée :")
        while running:
            try:
                c = input()
                if c.strip():
                    threading.Thread(target=process_scan,args=(c.strip(),),daemon=True).start()
            except (KeyboardInterrupt,EOFError): break
        return

    log.info("[Scanner] En attente...")
    buf = ""
    while running:
        try:
            if ser.in_waiting > 0:
                buf += ser.read(ser.in_waiting).decode("utf-8",errors="ignore")
                while "\n" in buf:
                    line, buf = buf.split("\n",1)
                    line = line.strip()
                    if line:
                        threading.Thread(target=process_scan,args=(line,),daemon=True).start()
        except Exception as e:
            log.error("[Scanner] %s",e); time.sleep(1)
        time.sleep(0.05)
    if ser.is_open: ser.close()

# ══════════════════════════════════════════════════════════════
#  THREAD HEARTBEAT
# ══════════════════════════════════════════════════════════════
def heartbeat_thread():
    global running, is_emergency, last_ping_t
    log.info("[HB] Thread démarré")
    load_picklist()
    counter = 0

    while running:
        time.sleep(2)
        counter += 1
        r = http_post(URL_PING,{
            "key":"AGV_SAGEMCOM_SECRET_2025","action":"ping",
            "zone":"zone1","battery_pct":100,"wifi_strength":100,
            "mode":"emergency" if is_emergency else "auto"
        })
        if not r:
            log.warning("[HB] Serveur inaccessible")
            if time.time()-last_ping_t>10 and not is_emergency:
                is_emergency=True
                for k in SLOTS: led_rouge(k)
            continue
        last_ping_t  = time.time()
        was          = is_emergency
        is_emergency = bool(r.get("is_emergency",False))
        if is_emergency and not was:
            for k in SLOTS: led_rouge(k); bip_erreur()
        elif not is_emergency and was:
            for k,st in slot_state.items():
                led_vert(k) if st["status"]=="confirmed" else led_rouge(k)
        if counter % 15 == 0:
            load_picklist()

    log.info("[HB] Arrêté")

# ══════════════════════════════════════════════════════════════
#  THREAD COMMANDES
# ══════════════════════════════════════════════════════════════
def commands_thread():
    global running, is_emergency
    log.info("[CMD] Thread démarré")
    while running:
        time.sleep(0.5)
        if is_emergency: continue
        r = http_get(URL_CMDS, {"key":KEY})
        if not r or r.get("command") in (None,"none",""): continue
        cmd=r.get("command",""); cid=r.get("id"); ok=False
        log.info("[CMD] %s",cmd)
        if cmd=="emergency_stop":
            is_emergency=True
            for k in SLOTS: led_rouge(k)
            bip_erreur(); ok=True
        elif cmd=="resume":
            is_emergency=False
            for k,st in slot_state.items():
                led_vert(k) if st["status"]=="confirmed" else led_rouge(k)
            ok=True
        elif cmd=="reload_picklist":
            load_picklist(); ok=True
        elif cmd in ["call_robot","return_to_zone3","confirm_discharge",
                     "manual_forward","manual_backward","manual_left","manual_right","manual_stop"]:
            ok=True
        if cid:
            http_post(f"{BASE}/confirm_command.php",{"key":KEY,"id":cid,"success":"1" if ok else "0"})
    log.info("[CMD] Arrêté")

# ══════════════════════════════════════════════════════════════
#  MAIN
# ══════════════════════════════════════════════════════════════
def main():
    global running
    print("\n"+"="*60)
    print("  ROBOT AGV — SAGEMCOM EL ZAHRA")
    print(f"  Serveur : {BASE}")
    print(f"  Scanner : {SCANNER_PORT}")
    print("="*60+"\n")

    setup_gpio()
    bip_demarrage()
    print("  GPIO OK")

    print("  Test serveur... ",end="",flush=True)
    r = http_post(URL_PING,{"key":KEY,"action":"ping","zone":"zone1",
                             "battery_pct":100,"wifi_strength":100,"mode":"auto"})
    print("OK" if r else f"ÉCHEC — Vérifier XAMPP sur {SERVER_IP}")

    for t in [threading.Thread(target=ir_thread,name="IR",daemon=True),
               threading.Thread(target=heartbeat_thread,name="HB",daemon=True),
               threading.Thread(target=commands_thread,name="CMD",daemon=True)]:
        t.start(); print(f"  Thread {t.name} : OK")

    print("\n  SYSTÈME EN MARCHE !")
    print("  1. Poser bobine → LED rouge → Scanner QR")
    print("  2. QR conforme  → LED VERTE ✅ + bip slot")
    print("  3. QR incorrect → LED rouge ❌ + bip principal")
    print("  4. Picklist complète → confirmation automatique 🎉\n")

    try:
        scanner_thread()
    except KeyboardInterrupt:
        print("\n  Arrêt...")
    finally:
        running=False; time.sleep(0.5)
        gpio_cleanup(); print("  Au revoir !")

if __name__=="__main__": main()
