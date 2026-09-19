#!/usr/bin/env bash
# Pack plugin ZIP + update.json for auto-updates.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${1:-$ROOT/demo/public/download}"
UPDATES_DIR="${2:-$ROOT/demo/public/updates}"
OUT="$OUT_DIR/f2f-ai-chatbot.zip"
JSON="$UPDATES_DIR/f2f-ai-chatbot.json"

# Public URLs customers' WordPress will fetch (override when packing for staging).
PUBLIC_ZIP_URL="${F2F_PUBLIC_ZIP_URL:-https://www.f2fbilisim.com/downloads/f2f-ai-chatbot.zip}"
PUBLIC_HOME="${F2F_PUBLIC_HOME:-https://www.f2fbilisim.com}"

mkdir -p "$OUT_DIR" "$UPDATES_DIR"
rm -f "$OUT"
(
  cd "$ROOT"
  zip -r -q "$OUT" f2f-ai-chatbot \
    -x 'f2f-ai-chatbot/.DS_Store' \
    -x 'f2f-ai-chatbot/**/.DS_Store' \
    -x 'f2f-ai-chatbot/.git/*'
)
cp -f "$OUT" "$ROOT/demo/public/assets/f2f-ai-chatbot.zip" 2>/dev/null || true
cp -f "$OUT" /opt/cursor/artifacts/f2f-ai-chatbot.zip 2>/dev/null || true

VERSION="$(grep -E '^\s*\*\s*Version:' "$ROOT/f2f-ai-chatbot/f2f-ai-chatbot.php" | head -1 | sed -E 's/.*Version:\s*//')"
VERSION="$(echo "$VERSION" | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
  VERSION="$(grep "F2F_AI_CHATBOT_VERSION" "$ROOT/f2f-ai-chatbot/f2f-ai-chatbot.php" | head -1 | sed -E "s/.*'([^']+)'.*/\1/")"
fi

CHANGELOG=""
if [[ -f "$ROOT/f2f-ai-chatbot/readme.txt" ]]; then
  CHANGELOG="$(awk '/^= [0-9]/{if(n++)exit} n{print}' "$ROOT/f2f-ai-chatbot/readme.txt" | head -20 | sed 's/"/\\"/g' | paste -sd '\\n' -)"
fi
DATE="$(date -u +%Y-%m-%d)"

cat > "$JSON" <<EOF
{
  "name": "F2F AI Chatbot",
  "slug": "f2f-ai-chatbot",
  "version": "${VERSION}",
  "download_url": "${PUBLIC_ZIP_URL}",
  "homepage": "${PUBLIC_HOME}",
  "requires": "6.0",
  "tested": "6.7",
  "requires_php": "7.4",
  "last_updated": "${DATE}",
  "changelog": "<h4>${VERSION}</h4><p>Otomatik güncelleme paketi — detay için F2F Bilişim.</p>"
}
EOF

cp -f "$JSON" /opt/cursor/artifacts/f2f-ai-chatbot-update.json 2>/dev/null || true
# Also keep a copy next to downloads for easy FTP upload together.
cp -f "$JSON" "$OUT_DIR/f2f-ai-chatbot-update.json" 2>/dev/null || true

echo "Wrote $OUT (v${VERSION})"
echo "Wrote $JSON"
echo "Upload both to hosting:"
echo "  ZIP  → ${PUBLIC_ZIP_URL}"
echo "  JSON → https://www.f2fbilisim.com/updates/f2f-ai-chatbot.json"
