/**
 * PLAN-032: Migrate Angular inline templates and styles to external files.
 *
 * What this script does:
 *  - Finds all .ts files with `template: \`...\`` and extracts to a .html file
 *  - Finds all .ts files with `styles: [...]` or `styles: \`...\`` and extracts to a .scss file
 *  - Replaces inline declarations with `templateUrl` / `styleUrl` references
 *
 * Usage: node scripts/migrate-inline-templates.mjs [--dry-run]
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../src');
const DRY_RUN = process.argv.includes('--dry-run');

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Walk directory recursively, yielding .ts file paths */
function* walkTs(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      yield* walkTs(full);
    } else if (entry.isFile() && entry.name.endsWith('.ts') && !entry.name.endsWith('.spec.ts')) {
      yield full;
    }
  }
}

/**
 * Find the matching closing backtick for an opening template literal.
 * Handles nested `${...}` expressions with their own backticks.
 */
function findClosingBacktick(src, startIdx) {
  let i = startIdx;
  let depth = 0; // depth of ${ } nesting

  while (i < src.length) {
    const ch = src[i];

    if (ch === '\\') {
      i += 2; // skip escaped char
      continue;
    }

    if (ch === '`' && depth === 0) {
      return i; // found the closing backtick
    }

    if (ch === '$' && src[i + 1] === '{') {
      depth++;
      i += 2;
      continue;
    }

    if (ch === '}' && depth > 0) {
      depth--;
    }

    i++;
  }

  return -1;
}

/**
 * Returns true when the `template:` keyword found is inside a JSDoc block comment.
 * Uses a simple backward scan for `/*` without a closing `* /` before keyIdx.
 */
function isInsideComment(src, keyIdx) {
  // Check for // line comment
  const lineStart = src.lastIndexOf('\n', keyIdx) + 1;
  const linePrefix = src.slice(lineStart, keyIdx);
  if (linePrefix.includes('//')) return true;

  // Check for /* block comment — find the last opening that isn't closed
  let search = 0;
  let inBlock = false;
  while (search < keyIdx) {
    const open = src.indexOf('/*', search);
    const close = src.indexOf('*/', search);
    if (open === -1 || open > keyIdx) break;
    if (close !== -1 && close < open) {
      // stray close before next open
      search = close + 2;
      continue;
    }
    inBlock = true;
    if (close !== -1 && close < keyIdx) {
      inBlock = false;
      search = close + 2;
    } else {
      break;
    }
  }
  return inBlock;
}

/**
 * Returns true when this file contains a real @Component decorator
 * (not just in a comment/JSDoc).
 */
function hasComponentDecorator(src) {
  const re = /@Component\s*\(/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    if (!isInsideComment(src, m.index)) return true;
  }
  return false;
}

// ─── Template extraction ──────────────────────────────────────────────────────

/**
 * Extracts `template: \`...\`` from src, returning:
 *   { html, newSrc, htmlFile } or null if not found
 */
function extractTemplateLiteral(src, tsFilePath) {
  const templateKey = /\btemplate\s*:\s*`/g;
  let m;

  while ((m = templateKey.exec(src)) !== null) {
    const absoluteKeyIdx = m.index;
    if (isInsideComment(src, absoluteKeyIdx)) continue;

    // Position of the opening backtick
    const openBacktick = src.indexOf('`', absoluteKeyIdx);
    const closeBacktick = findClosingBacktick(src, openBacktick + 1);

    if (closeBacktick === -1) continue;

    const html = src.slice(openBacktick + 1, closeBacktick);

    // Derive output filename (same dir, same stem, .html)
    const dir = path.dirname(tsFilePath);
    const stem = path.basename(tsFilePath, '.ts');
    const htmlFile = path.join(dir, `${stem}.html`);
    const relHtml = `./${stem}.html`;

    // Replace `template: \`...\`` with templateUrl
    const before = src.slice(0, absoluteKeyIdx);
    const after = src.slice(closeBacktick + 1);
    const newSrc = `${before}templateUrl: '${relHtml}'${after}`;

    return { html, newSrc, htmlFile };
  }

  return null;
}

// ─── Styles extraction ────────────────────────────────────────────────────────

/**
 * Extracts `styles: [...]` or `styles: \`...\`` from src, returning:
 *   { scss, newSrc, scssFile } or null if not found
 */
function extractStyles(src, tsFilePath) {
  // Handle backtick format: styles: `...`
  const backtickKey = /\bstyles\s*:\s*`/g;
  let m = backtickKey.exec(src);
  if (m && !isInsideComment(src, m.index)) {
    const openBacktick = src.indexOf('`', m.index);
    const closeBacktick = findClosingBacktick(src, openBacktick + 1);
    if (closeBacktick !== -1) {
      const scss = src.slice(openBacktick + 1, closeBacktick);
      const dir = path.dirname(tsFilePath);
      const stem = path.basename(tsFilePath, '.ts');
      const scssFile = path.join(dir, `${stem}.scss`);
      const relScss = `./${stem}.scss`;
      const before = src.slice(0, m.index);
      const after = src.slice(closeBacktick + 1);
      const newSrc = `${before}styleUrl: '${relScss}'${after}`;
      return { scss, newSrc, scssFile };
    }
  }

  // Handle array format: styles: [ `...` ] or styles: ['...']
  const arrayKey = /\bstyles\s*:\s*\[/g;
  m = arrayKey.exec(src);
  if (!m || isInsideComment(src, m.index)) return null;

  // Find matching closing bracket
  const openBracket = src.indexOf('[', m.index);
  let depth = 1;
  let i = openBracket + 1;
  let inBacktick = false;
  let inSingle = false;
  let inDouble = false;

  while (i < src.length && depth > 0) {
    const ch = src[i];
    if (ch === '\\' && (inBacktick || inSingle || inDouble)) { i += 2; continue; }
    if (!inSingle && !inDouble && ch === '`') { inBacktick = !inBacktick; i++; continue; }
    if (!inBacktick && !inDouble && ch === "'") { inSingle = !inSingle; i++; continue; }
    if (!inBacktick && !inSingle && ch === '"') { inDouble = !inDouble; i++; continue; }
    if (!inBacktick && !inSingle && !inDouble) {
      if (ch === '[') depth++;
      if (ch === ']') depth--;
    }
    i++;
  }

  const closeBracket = i - 1;
  const arrayContent = src.slice(openBracket + 1, closeBracket).trim();

  // Extract string content from the array
  // Could be `...` or '...' or "..."
  let scss = '';
  if (arrayContent.startsWith('`')) {
    const innerClose = findClosingBacktick(arrayContent, 1);
    scss = innerClose !== -1 ? arrayContent.slice(1, innerClose) : arrayContent;
  } else if (arrayContent.startsWith("'") || arrayContent.startsWith('"')) {
    const quote = arrayContent[0];
    const closeQ = arrayContent.indexOf(quote, 1);
    scss = closeQ !== -1 ? arrayContent.slice(1, closeQ) : arrayContent;
  } else {
    scss = arrayContent;
  }

  const dir = path.dirname(tsFilePath);
  const stem = path.basename(tsFilePath, '.ts');
  const scssFile = path.join(dir, `${stem}.scss`);
  const relScss = `./${stem}.scss`;

  const before = src.slice(0, m.index);
  const after = src.slice(closeBracket + 1);
  const newSrc = `${before}styleUrl: '${relScss}'${after}`;

  return { scss, newSrc, scssFile };
}

// ─── Detect inline decls ──────────────────────────────────────────────────────

function hasInlineTemplate(src) {
  const re = /\btemplate\s*:\s*`/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    if (!isInsideComment(src, m.index)) return true;
  }
  return false;
}

function hasInlineStyles(src) {
  const reArray = /\bstyles\s*:\s*\[/g;
  const reBacktick = /\bstyles\s*:\s*`/g;
  let m;
  while ((m = reArray.exec(src)) !== null) {
    if (!isInsideComment(src, m.index)) return true;
  }
  while ((m = reBacktick.exec(src)) !== null) {
    if (!isInsideComment(src, m.index)) return true;
  }
  return false;
}

// ─── Main ─────────────────────────────────────────────────────────────────────

let templateCount = 0;
let stylesCount = 0;
const errors = [];

for (const tsFile of walkTs(ROOT)) {
  let src = fs.readFileSync(tsFile, 'utf8');

  // Skip files that don't have a real @Component decorator
  if (!hasComponentDecorator(src)) continue;

  let mutated = false;

  // ── Extract template ──
  if (hasInlineTemplate(src)) {
    const result = extractTemplateLiteral(src, tsFile);
    if (result) {
      const { html, newSrc, htmlFile } = result;

      if (!DRY_RUN) {
        fs.writeFileSync(htmlFile, html, 'utf8');
        src = newSrc;
        mutated = true;
      }

      templateCount++;
      console.log(`[template] ${path.relative(ROOT, tsFile)} → ${path.relative(ROOT, htmlFile)}`);
    } else {
      errors.push(`WARN: could not extract template from ${path.relative(ROOT, tsFile)}`);
    }
  }

  // ── Extract styles ──
  if (hasInlineStyles(src)) {
    const result = extractStyles(src, tsFile);
    if (result) {
      const { scss, newSrc, scssFile } = result;

      if (!DRY_RUN) {
        fs.writeFileSync(scssFile, scss.trim() + '\n', 'utf8');
        src = newSrc;
        mutated = true;
      }

      stylesCount++;
      console.log(`[styles]   ${path.relative(ROOT, tsFile)} → ${path.relative(ROOT, scssFile)}`);
    } else {
      errors.push(`WARN: could not extract styles from ${path.relative(ROOT, tsFile)}`);
    }
  }

  // ── Write mutated .ts ──
  if (mutated) {
    fs.writeFileSync(tsFile, src, 'utf8');
  }
}

console.log('\n──────────────────────────────────────');
console.log(`Templates migrated : ${templateCount}`);
console.log(`Styles migrated    : ${stylesCount}`);
if (DRY_RUN) console.log('(DRY RUN — no files written)');
if (errors.length) {
  console.log('\nWarnings:');
  errors.forEach((e) => console.log(' ', e));
}
