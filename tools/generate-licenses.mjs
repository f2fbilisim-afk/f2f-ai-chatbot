#!/usr/bin/env node
/**
 * Generates 1000 F2F license keys.
 * - Private CSV for the seller (never ship in the plugin zip)
 * - SHA-256 hash pool PHP file embedded in the plugin
 */
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const COUNT = 1000;
const YEAR_DAYS = 365;

function segment() {
  return crypto.randomBytes(2).toString('hex').toUpperCase();
}

function generateKey(index) {
  // F2F-XXXX-XXXX-XXXX-XXXX
  return `F2F-${segment()}-${segment()}-${segment()}-${segment()}`;
}

function normalize(key) {
  return String(key || '')
    .trim()
    .toUpperCase()
    .replace(/\s+/g, '');
}

function hashKey(key) {
  return crypto.createHash('sha256').update(normalize(key), 'utf8').digest('hex');
}

const keys = new Set();
while (keys.size < COUNT) {
  keys.add(generateKey(keys.size));
}

const list = [...keys];
const createdAt = new Date().toISOString();

const csvLines = [
  'index,license_key,status,sold_to,sold_at,notes',
  ...list.map((k, i) => `${i + 1},${k},available,,,`),
];

const licensesDir = path.join(ROOT, 'licenses');
fs.mkdirSync(licensesDir, { recursive: true });

const privateCsv = path.join(licensesDir, 'F2F-LICENSE-KEYS-PRIVATE.csv');
fs.writeFileSync(privateCsv, csvLines.join('\n') + '\n', 'utf8');

const artifactCsv = path.join('/opt/cursor/artifacts', 'F2F-LICENSE-KEYS-PRIVATE.csv');
fs.writeFileSync(artifactCsv, csvLines.join('\n') + '\n', 'utf8');

const hashes = list.map(hashKey).sort();
const php = `<?php
/**
 * F2F AI Chatbot — valid license key hashes (SHA-256).
 * Generated: ${createdAt}
 * Count: ${COUNT}
 * Premium duration: ${YEAR_DAYS} days from first activation.
 *
 * Plaintext keys are NOT stored here. Keep licenses/F2F-LICENSE-KEYS-PRIVATE.csv private.
 *
 * @package F2F_AI_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
\texit;
}

return array(
${hashes.map((h) => `\t'${h}',`).join('\n')}
);
`;

const poolPath = path.join(ROOT, 'f2f-ai-chatbot', 'includes', 'license-pool.php');
fs.writeFileSync(poolPath, php, 'utf8');

const meta = {
  count: COUNT,
  createdAt,
  premiumDays: YEAR_DAYS,
  format: 'F2F-XXXX-XXXX-XXXX-XXXX',
  privateCsv: 'licenses/F2F-LICENSE-KEYS-PRIVATE.csv',
  hashFile: 'f2f-ai-chatbot/includes/license-pool.php',
};
fs.writeFileSync(path.join(licensesDir, 'manifest.json'), JSON.stringify(meta, null, 2) + '\n');

console.log(`Generated ${COUNT} keys`);
console.log(`Private: ${privateCsv}`);
console.log(`Artifact: ${artifactCsv}`);
console.log(`Pool: ${poolPath}`);
console.log(`Sample keys (first 3 — also in private CSV):`);
list.slice(0, 3).forEach((k, i) => console.log(`  ${i + 1}. ${k}`));
