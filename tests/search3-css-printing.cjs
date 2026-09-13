'use strict';
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const crypto = require('node:crypto');
execFileSync('python3', ['scripts/build/search3_assets.py', '--write'], { stdio: 'inherit' });
const data = fs.readFileSync('v2/search3-entry-v1.css');
console.log('SEARCH3_ENTRY_CANONICAL_SHA256=' + crypto.createHash('sha256').update(data).digest('hex'));
console.log('SEARCH3_ENTRY_CANONICAL_B64=' + data.toString('base64'));
console.log('PASS: diagnostic canonical Search3 entry asset emitted');
