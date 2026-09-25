#!/usr/bin/env bash
# Pack plugin ZIP + update.json for auto-updates (GitHub Releases by default).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${1:-$ROOT/demo/public/download}"
UPDATES_DIR="${2:-$ROOT/demo/public/updates}"
OUT="$OUT_DIR/f2f-ai-chatbot.zip"
JSON="$UPDATES_DIR/f2f-ai-chatbot.json"
ROOT_JSON="$ROOT/updates/f2f-ai-chatbot.json"

PUBLIC_HOME="${F2F_PUBLIC_HOME:-https://www.f2fbilisim.com}"
GITHUB_REPO="${F2F_GITHUB_REPO:-f2fbilisim-afk/f2f-ai-chatbot}"

mkdir -p "$OUT_DIR" "$UPDATES_DIR" "$ROOT/updates"
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

VERSION="$(grep -E '^\s*\*\s*Version:' "$ROOT/f2f-ai-chatbot/f2f-ai-chatbot.php" | head -1 | sed -E 's/.*Version:\s*//' | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
  VERSION="$(grep "F2F_AI_CHATBOT_VERSION" "$ROOT/f2f-ai-chatbot/f2f-ai-chatbot.php" | head -1 | sed -E "s/.*'([^']+)'.*/\1/")"
fi

DATE="$(date -u +%Y-%m-%d)"
PUBLIC_ZIP_URL="${F2F_PUBLIC_ZIP_URL:-https://github.com/${GITHUB_REPO}/releases/download/v${VERSION}/f2f-ai-chatbot.zip}"

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
  "changelog": "<h4>${VERSION}</h4><p>GitHub Releases otomatik güncelleme — F2F Bilişim.</p>"
}
EOF

cp -f "$JSON" "$ROOT_JSON"
cp -f "$JSON" /opt/cursor/artifacts/f2f-ai-chatbot-update.json 2>/dev/null || true
cp -f "$JSON" "$OUT_DIR/f2f-ai-chatbot-update.json" 2>/dev/null || true

echo "Wrote $OUT (v${VERSION})"
echo "Wrote $JSON"
echo "Wrote $ROOT_JSON"
echo "GitHub release asset URL: ${PUBLIC_ZIP_URL}"
echo "Manifest (raw): https://raw.githubusercontent.com/${GITHUB_REPO}/main/updates/f2f-ai-chatbot.json"
