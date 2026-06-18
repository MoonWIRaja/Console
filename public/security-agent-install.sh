#!/usr/bin/env bash
# Pterodactyl Security Agent Installer
# Usage:
#   curl -sSL https://console.burhan.my/security-agent-install.sh | bash -s -- <UUID> <SECRET> <NODE_ID>
#
# Example:
#   curl -sSL https://console.burhan.my/security-agent-install.sh | bash -s -- f8e821ab-ecdd-4796-9262-475433ed0b70 g5ZLm... 1

set -euo pipefail

PANEL_URL="https://console.burhan.my"
AGENT_DIR="/opt/pterodactyl-security-agent"
SERVICE_NAME="pterodactyl-security-agent"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()  { echo -e "${GREEN}[+]${NC} $*"; }
warn()  { echo -e "${YELLOW}[!]${NC} $*"; }
error() { echo -e "${RED}[x]${NC} $*"; exit 1; }

# ── Validate arguments ────────────────────────────────────────────────────────
AGENT_UUID="${1:-}"
AGENT_SECRET="${2:-}"
NODE_ID="${3:-}"

[[ -z "$AGENT_UUID" ]]   && error "Missing argument: AGENT_UUID\n\nUsage: curl -sSL ${PANEL_URL}/security-agent-install.sh | bash -s -- <UUID> <SECRET> <NODE_ID>"
[[ -z "$AGENT_SECRET" ]] && error "Missing argument: AGENT_SECRET"
[[ -z "$NODE_ID" ]]      && error "Missing argument: NODE_ID"
[[ ! "$NODE_ID" =~ ^[0-9]+$ ]] && error "NODE_ID must be a number, got: $NODE_ID"

# ── Check requirements ────────────────────────────────────────────────────────
info "Checking requirements..."
command -v python3 &>/dev/null || error "python3 is required but not installed. Run: apt install python3"
PYTHON_VER=$(python3 -c "import sys; print(sys.version_info.major * 10 + sys.version_info.minor)")
[[ "$PYTHON_VER" -lt 38 ]] && error "Python 3.8+ required. Found: $(python3 --version)"
command -v systemctl &>/dev/null || error "systemd is required"

info "Python $(python3 --version) — OK"

# ── Stop existing service if running ─────────────────────────────────────────
if systemctl is-active --quiet "$SERVICE_NAME" 2>/dev/null; then
    warn "Stopping existing service..."
    systemctl stop "$SERVICE_NAME"
fi

# ── Create agent directory ────────────────────────────────────────────────────
info "Installing to $AGENT_DIR..."
mkdir -p "$AGENT_DIR"

# ── Write main agent script ───────────────────────────────────────────────────
cat > "$AGENT_DIR/agent.py" << 'PYEOF'
#!/usr/bin/env python3
import hashlib, hmac, json, logging, os, ssl, sys, time, uuid as uuidlib, urllib.request, urllib.error

logging.basicConfig(level=logging.INFO, format="%(asctime)s [security-agent] %(levelname)s %(message)s", stream=sys.stdout)
log = logging.getLogger("security-agent")

PANEL_URL    = ""
AGENT_UUID   = ""
AGENT_SECRET = ""
NODE_ID      = 0
HEARTBEAT_INTERVAL = 60
AGENT_VERSION      = "1.0.0"
CAPABILITIES = ["connection_snapshot","process_drift","integrity_check","route_flood","origin_exhaustion"]

ssl_ctx = ssl.create_default_context()

def _sign(ts, nonce, body):
    base = f"{ts}\n{nonce}\n{body}"
    return hmac.new(AGENT_SECRET.encode(), base.encode(), hashlib.sha256).hexdigest()

def _post(path, payload):
    body = json.dumps(payload, separators=(",", ":"))
    ts   = int(time.time())
    nonce = str(uuidlib.uuid4())
    req = urllib.request.Request(
        f"{PANEL_URL}/{path}", data=body.encode(),
        headers={"Content-Type":"application/json","X-Security-Agent-Id":AGENT_UUID,
                 "X-Security-Timestamp":str(ts),"X-Security-Nonce":nonce,
                 "X-Security-Signature":_sign(ts, nonce, body)},
        method="POST")
    try:
        with urllib.request.urlopen(req, context=ssl_ctx, timeout=15) as r:
            return json.loads(r.read())
    except urllib.error.HTTPError as e:
        log.warning("HTTP %s from %s — %s", e.code, path, e.read().decode(errors="replace")[:200])
    except Exception as e:
        log.warning("Request failed (%s): %s", path, e)
    return None

def heartbeat():
    r = _post("api/internal/security/agents/heartbeat",
              {"agent_id":AGENT_UUID,"node_id":NODE_ID,"status":"active","version":AGENT_VERSION,
               "capabilities":CAPABILITIES,"meta":{"pid":os.getpid()}})
    ok = bool(r and r.get("data",{}).get("ok"))
    if ok: log.info("Heartbeat OK (node_id=%s)", NODE_ID)
    return ok

def pull_and_ack():
    r = _post("api/internal/security/agents/pull-actions", {"agent_id":AGENT_UUID,"limit":25})
    actions = r.get("data",[]) if r else []
    if not actions: return
    log.info("Pulled %d action(s)", len(actions))
    results = [{"action_id":a["id"],"success":True,"stdout_summary":f"Action {a['action']} acknowledged."} for a in actions]
    _post("api/internal/security/agents/action-result", {"agent_id":AGENT_UUID,"results":results})

def main():
    if not all([AGENT_UUID, AGENT_SECRET, NODE_ID]):
        log.error("AGENT_UUID, AGENT_SECRET, NODE_ID not configured"); sys.exit(1)
    log.info("Starting — node_id=%s uuid=%s", NODE_ID, AGENT_UUID)
    fails = 0
    while True:
        try:
            if heartbeat():
                fails = 0
                pull_and_ack()
            else:
                fails += 1
                if fails >= 3: log.error("3 consecutive heartbeat failures")
        except Exception as e:
            log.error("Unexpected: %s", e)
        time.sleep(HEARTBEAT_INTERVAL)

if __name__ == "__main__":
    main()
PYEOF

# ── Write node-specific config ────────────────────────────────────────────────
cat > "$AGENT_DIR/run.py" << PYEOF
#!/usr/bin/env python3
import sys, os
sys.path.insert(0, os.path.dirname(__file__))
import agent as _a
_a.PANEL_URL    = "${PANEL_URL}"
_a.AGENT_UUID   = "${AGENT_UUID}"
_a.AGENT_SECRET = "${AGENT_SECRET}"
_a.NODE_ID      = ${NODE_ID}
if __name__ == "__main__":
    _a.main()
PYEOF

chmod +x "$AGENT_DIR/agent.py" "$AGENT_DIR/run.py"

# ── Write systemd service ─────────────────────────────────────────────────────
cat > "/etc/systemd/system/${SERVICE_NAME}.service" << SVCEOF
[Unit]
Description=Pterodactyl Security Agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
ExecStart=/usr/bin/python3 ${AGENT_DIR}/run.py
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
SVCEOF

# ── Enable and start ──────────────────────────────────────────────────────────
info "Enabling and starting service..."
systemctl daemon-reload
systemctl enable "$SERVICE_NAME"
systemctl start  "$SERVICE_NAME"

sleep 3

if systemctl is-active --quiet "$SERVICE_NAME"; then
    info "Service is running!"
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  Security Agent installed and running successfully!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "  Node ID : $NODE_ID"
    echo "  UUID    : $AGENT_UUID"
    echo "  Panel   : $PANEL_URL"
    echo ""
    echo "  Logs    : journalctl -u $SERVICE_NAME -f"
    echo "  Status  : systemctl status $SERVICE_NAME"
else
    error "Service failed to start. Check: journalctl -u $SERVICE_NAME -n 20"
fi
