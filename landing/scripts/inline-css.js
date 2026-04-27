#!/usr/bin/env node
/**
 * Inline compiled Tailwind CSS into index.html.
 * Eliminates the critical request chain for dist/styles.css.
 *
 * Usage: node scripts/inline-css.js
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const HTML_PATH = path.join(ROOT, 'index.html');
const CSS_PATH = path.join(ROOT, 'dist', 'styles.css');

const html = fs.readFileSync(HTML_PATH, 'utf8');
const css = fs.readFileSync(CSS_PATH, 'utf8');

const LINK_PATTERN = /[ \t]*<link rel="stylesheet" href="dist\/styles\.css" \/>\n?/;

if (!LINK_PATTERN.test(html)) {
    console.error('❌  Could not find <link rel="stylesheet" href="dist/styles.css"> in index.html');
    process.exit(1);
}

const inlined = html.replace(LINK_PATTERN, `        <style>${css}</style>\n`);

fs.writeFileSync(HTML_PATH, inlined, 'utf8');
console.log(`✅  Inlined ${(Buffer.byteLength(css, 'utf8') / 1024).toFixed(1)} KB of CSS into index.html`);
