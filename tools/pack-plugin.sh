#!/usr/bin/env bash
# Pack plugin ZIP with folder root: f2f-ai-chatbot/...
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${1:-$ROOT/demo/public/download}"
OUT="$OUT_DIR/f2f-ai-chatbot.zip"
mkdir -p "$OUT_DIR"
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
echo "Wrote $OUT"
unzip -l "$OUT" | head -15
