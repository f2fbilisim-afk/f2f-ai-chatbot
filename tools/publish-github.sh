#!/usr/bin/env bash
# Pack plugin, push to GitHub remote, create Release with ZIP.
# Requires: GITHUB_TOKEN or GH_TOKEN (repo scope).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TOKEN="${GITHUB_TOKEN:-${GH_TOKEN:-}}"
if [[ -z "$TOKEN" ]]; then
  echo "ERROR: GITHUB_TOKEN / GH_TOKEN yok." >&2
  exit 1
fi

PLUGIN_DIR="$ROOT/f2f-ai-chatbot"
VERSION="$(grep -E '^\s*\*\s*Version:' "$PLUGIN_DIR/f2f-ai-chatbot.php" | head -1 | sed -E 's/.*Version:\s*//' | tr -d '[:space:]')"
if [[ -z "$VERSION" ]]; then
  echo "ERROR: version okunamadı" >&2
  exit 1
fi
TAG="v${VERSION}"
REPO="${F2F_GITHUB_REPO:-f2fbilisim-afk/f2f-ai-chatbot}"
API="https://api.github.com"
RELEASE_ZIP_URL="https://github.com/${REPO}/releases/download/${TAG}/f2f-ai-chatbot.zip"
DATE="$(date -u +%Y-%m-%d)"
CHANGELOG="${F2F_CHANGELOG:-<h4>${VERSION}</h4><p>F2F AI Chatbot ${VERSION}</p>}"

echo "==> Pack ${VERSION}"
F2F_PUBLIC_ZIP_URL="$RELEASE_ZIP_URL" bash "$ROOT/tools/pack-plugin.sh"

python3 - "$ROOT" "$VERSION" "$RELEASE_ZIP_URL" "$DATE" "$CHANGELOG" <<'PY'
import json, pathlib, sys
root, version, zip_url, date, changelog = sys.argv[1:]
data = {
  "name": "F2F AI Chatbot",
  "slug": "f2f-ai-chatbot",
  "version": version,
  "download_url": zip_url,
  "homepage": "https://www.f2fbilisim.com",
  "requires": "6.0",
  "tested": "6.7",
  "requires_php": "7.4",
  "last_updated": date,
  "changelog": changelog,
}
text = json.dumps(data, ensure_ascii=False, indent=2) + "\n"
for rel in (
  "updates/f2f-ai-chatbot.json",
  "demo/public/updates/f2f-ai-chatbot.json",
  "demo/public/download/f2f-ai-chatbot-update.json",
):
  pathlib.Path(root, rel).write_text(text)
print("Wrote update manifests ->", zip_url)
PY

ZIP="$ROOT/demo/public/download/f2f-ai-chatbot.zip"
test -f "$ZIP"

# Point github remote at tokenized URL (do not print URL with token)
git remote remove github 2>/dev/null || true
git remote add github "https://x-access-token:${TOKEN}@github.com/${REPO}.git"

# Commit manifest if dirty (release download_url must match GitHub)
if ! git diff --quiet -- updates/f2f-ai-chatbot.json demo/public/updates/f2f-ai-chatbot.json demo/public/download/f2f-ai-chatbot-update.json 2>/dev/null \
  || ! git diff --quiet --cached -- updates/f2f-ai-chatbot.json 2>/dev/null; then
  git add updates/f2f-ai-chatbot.json demo/public/updates/f2f-ai-chatbot.json demo/public/download/f2f-ai-chatbot-update.json tools/publish-github.sh 2>/dev/null || true
  if ! git diff --cached --quiet; then
    git commit -m "chore: point update manifest to GitHub Release ${TAG}"
  fi
fi

echo "==> Push origin main"
git push -u origin HEAD:main || git push origin HEAD:main

echo "==> Push github main"
git push github HEAD:main

echo "==> Create / update Release ${TAG}"
EXISTING="$(curl -sS -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/vnd.github+json" \
  "${API}/repos/${REPO}/releases/tags/${TAG}" || true)"
if echo "$EXISTING" | python3 -c "import sys,json; d=json.load(sys.stdin); raise SystemExit(0 if d.get('id') else 1)" 2>/dev/null; then
  REL_ID="$(echo "$EXISTING" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])")"
  echo "Release exists (id=$REL_ID) — recreating..."
  curl -fsS -X DELETE -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/vnd.github+json" \
    "${API}/repos/${REPO}/releases/${REL_ID}" >/dev/null
  curl -fsS -X DELETE -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/vnd.github+json" \
    "${API}/repos/${REPO}/git/refs/tags/${TAG}" >/dev/null || true
fi

CREATE="$(curl -fsS -X POST -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/vnd.github+json" \
  "${API}/repos/${REPO}/releases" \
  -d "$(python3 -c "import json; print(json.dumps({'tag_name':'''$TAG''','name':'''F2F AI Chatbot $TAG''','body':'''F2F AI Chatbot $VERSION\n\nAuto-update ZIP: f2f-ai-chatbot.zip''','draft':False,'prerelease':False}))")")"

UPLOAD_URL="$(echo "$CREATE" | python3 -c "import sys,json; print(json.load(sys.stdin)['upload_url'].split('{')[0])")"

curl -fsS -X POST \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/zip" \
  -H "Accept: application/vnd.github+json" \
  --data-binary @"$ZIP" \
  "${UPLOAD_URL}?name=f2f-ai-chatbot.zip" >/dev/null

# Also upload update JSON as release asset for convenience
curl -fsS -X POST \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/vnd.github+json" \
  --data-binary @"$ROOT/updates/f2f-ai-chatbot.json" \
  "${UPLOAD_URL}?name=f2f-ai-chatbot.json" >/dev/null

# Scrub token from remote URL after push
git remote remove github 2>/dev/null || true
git remote add github "https://github.com/${REPO}.git"

echo "OK: https://github.com/${REPO}/releases/tag/${TAG}"
echo "Asset: ${RELEASE_ZIP_URL}"
