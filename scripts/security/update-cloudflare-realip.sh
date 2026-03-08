#!/usr/bin/env bash
set -euo pipefail

OUT_FILE="${1:-/etc/nginx/cloudflare-realip.conf}"
TMP_FILE="$(mktemp)"

{
    echo "# Auto-generated Cloudflare real IP ranges"
    echo "# Generated at: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    echo

    curl -fsSL https://www.cloudflare.com/ips-v4 | sed -e '/^$/d' -e 's/^/set_real_ip_from /' -e 's/$/;/'
    curl -fsSL https://www.cloudflare.com/ips-v6 | sed -e '/^$/d' -e 's/^/set_real_ip_from /' -e 's/$/;/'
} > "$TMP_FILE"

mv "$TMP_FILE" "$OUT_FILE"
chmod 644 "$OUT_FILE"

echo "Updated $OUT_FILE"
