#!/usr/bin/env node
/**
 * Generates 1000 F2F license keys across 3 packages.
 * - Private CSV for the seller (never ship in the plugin zip)
 * - Hash → plan map embedded in the plugin (no plaintext keys)
 */
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const YEAR_DAYS = 365;

/** @type {{id:string,label:string,messages:number,count:number}[]} */
const PACKAGES = [
  { id: 'starter', label: 'Starter', messages: 1000, count: 600 },
  { id: 'business', label: 'Business', messages: 5000, count: 300 },
  { id: 'pro', label: 'Pro', messages: 15000, count: 100 },
];

function segment() {
  return crypto.randomBytes(2).toString('hex').toUpperCase();
}

function generateKey() {
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

const allKeys = new Set();
/** @type {{index:number,key:string,plan:string,label:string,messages:number}[]} */
const rows = [];
let index = 1;

for (const pkg of PACKAGES) {
  let made = 0;
  while (made < pkg.count) {
    const key = generateKey();
    if (allKeys.has(key)) continue;
    allKeys.add(key);
    rows.push({
      index,
      key,
      plan: pkg.id,
      label: pkg.label,
      messages: pkg.messages,
    });
    index += 1;
    made += 1;
  }
}

const createdAt = new Date().toISOString();
const licensesDir = path.join(ROOT, 'licenses');
fs.mkdirSync(licensesDir, { recursive: true });

const csvLines = [
  'index,license_key,plan,messages_limit,status,sold_to,sold_at,notes',
  ...rows.map(
    (r) =>
      `${r.index},${r.key},${r.plan},${r.messages},available,,,`
  ),
];

const privateCsv = path.join(licensesDir, 'F2F-LICENSE-KEYS-PRIVATE.csv');
fs.writeFileSync(privateCsv, csvLines.join('\n') + '\n', 'utf8');
fs.writeFileSync(
  path.join('/opt/cursor/artifacts', 'F2F-LICENSE-KEYS-PRIVATE.csv'),
  csvLines.join('\n') + '\n',
  'utf8'
);

const poolEntries = rows
  .map((r) => {
    const h = hashKey(r.key);
    return `\t'${h}' => array(\n\t\t'plan'     => '${r.plan}',\n\t\t'label'    => '${r.label}',\n\t\t'messages' => ${r.messages},\n\t),`;
  })
  .sort((a, b) => a.localeCompare(b));

const php = `<?php
/**
 * F2F AI Chatbot — license hash → package map.
 * Generated: ${createdAt}
 * Total keys: ${rows.length}
 * Packages: Starter ${PACKAGES[0].messages} / Business ${PACKAGES[1].messages} / Pro ${PACKAGES[2].messages} messages
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
${poolEntries.join('\n')}
);
`;

const poolPath = path.join(ROOT, 'f2f-ai-chatbot', 'includes', 'license-pool.php');
fs.writeFileSync(poolPath, php, 'utf8');

fs.writeFileSync(
  path.join(licensesDir, 'manifest.json'),
  JSON.stringify(
    {
      count: rows.length,
      createdAt,
      premiumDays: YEAR_DAYS,
      format: 'F2F-XXXX-XXXX-XXXX-XXXX',
      packages: PACKAGES,
      privateCsv: 'licenses/F2F-LICENSE-KEYS-PRIVATE.csv',
      hashFile: 'f2f-ai-chatbot/includes/license-pool.php',
    },
    null,
    2
  ) + '\n'
);

console.log(`Generated ${rows.length} keys`);
for (const pkg of PACKAGES) {
  console.log(`  ${pkg.label}: ${pkg.count} keys × ${pkg.messages} messages`);
}
console.log(`Private: ${privateCsv}`);
console.log('Samples:');
for (const pkg of PACKAGES) {
  const sample = rows.find((r) => r.plan === pkg.id);
  console.log(`  ${pkg.label}: ${sample.key}`);
}
