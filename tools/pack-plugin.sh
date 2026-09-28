#!/usr/bin/env bash
# Pack plugin ZIP + update.json for auto-updates (hub / GitHub).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${1:-$ROOT/demo/public/download}"
UPDATES_DIR="${2:-$ROOT/demo/public/updates}"
OUT="$OUT_DIR/f2f-ai-chatbot.zip"
JSON="$UPDATES_DIR/f2f-ai-chatbot.json"
ROOT_JSON="$ROOT/updates/f2f-ai-chatbot.json"
PLUGIN_DIR="$ROOT/f2f-ai-chatbot"
DIST_DIR="$PLUGIN_DIR/dist"

PUBLIC_HOME="${F2F_PUBLIC_HOME:-https://www.f2fbilisim.com}"
GITHUB_REPO="${F2F_GITHUB_REPO:-f2fbilisim-afk/f2f-ai-chatbot}"

VERSION="$(grep -E '^\s*\*\s*Version:' "$PLUGIN_DIR/f2f-ai-chatbot.php" | head -1 | sed -E 's/.*Version:\s*//' | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
  VERSION="$(grep "F2F_AI_CHATBOT_VERSION" "$PLUGIN_DIR/f2f-ai-chatbot.php" | head -1 | sed -E "s/.*'([^']+)'.*/\1/")"
fi

mkdir -p "$OUT_DIR" "$UPDATES_DIR" "$ROOT/updates" "$DIST_DIR"
rm -f "$OUT" "$DIST_DIR/f2f-ai-chatbot.zip"

# Pass 1: clean ZIP without nested dist (this becomes the distributable payload).
TMP_CLEAN="$(mktemp -u /tmp/f2f-ai-clean-XXXXXX.zip)"
(
  cd "$ROOT"
  zip -r -q "$TMP_CLEAN" f2f-ai-chatbot \
    -x 'f2f-ai-chatbot/.DS_Store' \
    -x 'f2f-ai-chatbot/**/.DS_Store' \
    -x 'f2f-ai-chatbot/.git/*' \
    -x 'f2f-ai-chatbot/dist/*'
)
cp -f "$TMP_CLEAN" "$DIST_DIR/f2f-ai-chatbot.zip"
echo "$VERSION" > "$DIST_DIR/VERSION"

# Pass 2: final install ZIP includes dist/ so hub can serve /plugin-zip without ZipArchive.
(
  cd "$ROOT"
  zip -r -q "$OUT" f2f-ai-chatbot \
    -x 'f2f-ai-chatbot/.DS_Store' \
    -x 'f2f-ai-chatbot/**/.DS_Store' \
    -x 'f2f-ai-chatbot/.git/*'
)
rm -f "$TMP_CLEAN"

cp -f "$OUT" "$ROOT/demo/public/assets/f2f-ai-chatbot.zip" 2>/dev/null || true
cp -f "$OUT" /opt/cursor/artifacts/f2f-ai-chatbot.zip 2>/dev/null || true

DATE="$(date -u +%Y-%m-%d)"
# Prefer hub download URL (works after hub is updated); GitHub is fallback for history.
PUBLIC_ZIP_URL="${F2F_PUBLIC_ZIP_URL:-https://www.f2fbilisim.com/wp-json/f2f-ai-platform/v1/plugin-zip}"

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
  "changelog": "<h4>${VERSION}</h4><p>F2F hub otomatik güncelleme.</p>"
}
EOF

cp -f "$JSON" "$ROOT_JSON"
cp -f "$JSON" /opt/cursor/artifacts/f2f-ai-chatbot-update.json 2>/dev/null || true
cp -f "$JSON" "$OUT_DIR/f2f-ai-chatbot-update.json" 2>/dev/null || true

echo "Wrote $OUT (v${VERSION})"
echo "Bundled dist ZIP: $DIST_DIR/f2f-ai-chatbot.zip ($(wc -c < "$DIST_DIR/f2f-ai-chatbot.zip") bytes)"
echo "Wrote $JSON"
echo "Hub update: ${PUBLIC_HOME}/wp-json/f2f-ai-platform/v1/update"
echo "Hub ZIP: ${PUBLIC_ZIP_URL}"
