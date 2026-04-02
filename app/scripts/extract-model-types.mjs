/**
 * PLAN-032: Extract exported interfaces/types/enums/consts from component files
 * into dedicated {name}.model.ts files.
 *
 * Strategy:
 *  - Identifies @Component .ts files with exported declarations (interface/type/enum/const)
 *  - Extracts them to a new .model.ts file
 *  - Adds `export * from './{name}.model'` re-export to the component .ts so barrel & consumers keep working
 *  - For non-component files (e.g. *.models.ts), renames to *.model.ts (single)
 *
 * Usage: node scripts/extract-model-types.mjs [--dry-run]
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../src');
const DRY_RUN = process.argv.includes('--dry-run');

// ─── Helpers ──────────────────────────────────────────────────────────────────

function* walkTs(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) yield* walkTs(full);
    else if (entry.isFile() && entry.name.endsWith('.ts') && !entry.name.endsWith('.spec.ts'))
      yield full;
  }
}

/** Returns true if file contains a real @Component decorator (not in comment) */
function hasComponentDecorator(src) {
  return /@Component\s*\(/.test(src);
}

/**
 * Find the end of a top-level declaration block starting at `startIdx`.
 * Handles: multi-line interfaces/enums/types with braces, union types, etc.
 * Returns the index AFTER the declaration (including final semicolon if present).
 */
function findDeclarationEnd(src, startIdx) {
  let i = startIdx;
  const len = src.length;

  // Skip to first meaningful char
  while (i < len && /\s/.test(src[i])) i++;

  // Different ending strategies based on what we see
  const firstChar = src[i];

  if (firstChar === '{') {
    // Block (interface, enum, namespace)
    let depth = 0;
    while (i < len) {
      if (src[i] === '{') depth++;
      else if (src[i] === '}') {
        depth--;
        if (depth === 0) {
          i++;
          break;
        }
      }
      i++;
    }
    // Consume trailing semicolon
    while (i < len && src[i] === ';') i++;
    return i;
  }

  // For type aliases / const — end at semicolon at the top level (depth 0)
  let depth = 0;
  let inString = false;
  let stringChar = '';
  let inBacktick = false;
  let btDepth = 0;

  while (i < len) {
    const ch = src[i];
    if (inBacktick) {
      if (ch === '\\') {
        i += 2;
        continue;
      }
      if (ch === '$' && src[i + 1] === '{') {
        btDepth++;
        i += 2;
        continue;
      }
      if (ch === '}' && btDepth > 0) {
        btDepth--;
        i++;
        continue;
      }
      if (ch === '`' && btDepth === 0) inBacktick = false;
      i++;
      continue;
    }
    if (inString) {
      if (ch === '\\') {
        i += 2;
        continue;
      }
      if (ch === stringChar) inString = false;
      i++;
      continue;
    }
    if (ch === '`') {
      inBacktick = true;
      i++;
      continue;
    }
    if (ch === '"' || ch === "'") {
      inString = true;
      stringChar = ch;
      i++;
      continue;
    }
    if (ch === '(' || ch === '[' || ch === '<') {
      depth++;
      i++;
      continue;
    }
    if (ch === ')' || ch === ']' || ch === '>') {
      depth--;
      i++;
      continue;
    }
    if (ch === '{') {
      depth++;
      i++;
      continue;
    }
    if (ch === '}') {
      if (depth === 0) break; // end of outer scope
      depth--;
      i++;
      continue;
    }
    if (ch === ';' && depth === 0) {
      i++;
      break;
    }
    // Newline at depth 0 for simple type aliases: `export type Foo = 'a' | 'b'`
    if (ch === '\n' && depth === 0) {
      // Look ahead; if next non-blank line starts with export/class/@, stop
      const rest = src.slice(i + 1).trimStart();
      if (/^(export|class|@|\/\*\*)/.test(rest)) break;
    }
    i++;
  }
  return i;
}

/**
 * Extract all top-level exported declarations from src.
 * Returns array of { fullText: string, startIdx: number, endIdx: number }
 */
function extractExports(src) {
  const results = [];
  // Match: (/** ... */ \n)? export (interface|type|enum) Name...
  // NOTE: 'const' is intentionally excluded — consts often have runtime imports that can't be trivially relocated
  const re = /^(\/\*\*[\s\S]*?\*\/\s*\n)?(export\s+(?:interface|type|enum)\s+\w)/gm;
  let m;

  while ((m = re.exec(src)) !== null) {
    const startIdx = m.index;

    // Skip if inside a class/function (by checking if there's an unclosed { before us at top level)
    // A simple heuristic: check if we're not inside a class body
    const prefix = src.slice(0, startIdx);
    const openBraces = (prefix.match(/\{/g) || []).length;
    const closeBraces = (prefix.match(/\}/g) || []).length;
    if (openBraces > closeBraces) {
      // We're inside some block — skip (class member or method)
      continue;
    }

    // Find keyword start (skip JSDoc if present)
    const exportKeywordIdx = src.indexOf('export', startIdx);

    // Find end of declaration
    const afterKeyword = src.indexOf(' ', exportKeywordIdx + 7); // skip "export "
    const endIdx = findDeclarationEnd(src, afterKeyword);
    const fullText = src.slice(startIdx, endIdx);

    results.push({ fullText, startIdx, endIdx });
  }

  return results;
}

// ─── Main ─────────────────────────────────────────────────────────────────────

let modelCount = 0;
let renamedCount = 0;
const errors = [];

for (const tsFile of walkTs(ROOT)) {
  const stem = path.basename(tsFile, '.ts');
  const dir = path.dirname(tsFile);

  // ── 1. Rename *.models.ts → *.model.ts ──
  if (stem.endsWith('.models')) {
    const newStem = stem.slice(0, -1); // remove trailing 's' from 'models'
    const newPath = path.join(dir, `${newStem}.ts`);
    if (!fs.existsSync(newPath)) {
      if (!DRY_RUN) {
        fs.renameSync(tsFile, newPath);
      }
      console.log(`[rename]   ${path.relative(ROOT, tsFile)} → ${path.relative(ROOT, newPath)}`);
      renamedCount++;
    } else {
      errors.push(`SKIP rename: ${path.relative(ROOT, tsFile)} (target exists)`);
    }
    continue;
  }

  // ── 2. Skip non-component files ──
  const src = fs.readFileSync(tsFile, 'utf8');
  if (!hasComponentDecorator(src)) continue;

  // ── 3. Skip if model file already exists ──
  const modelFile = path.join(dir, `${stem}.model.ts`);
  if (fs.existsSync(modelFile)) continue;

  // ── 4. Extract exported declarations ──
  const exports_ = extractExports(src);
  if (exports_.length === 0) continue;

  // Build model file content
  const modelLines = [`/**\n * Models and types for ${stem} component.\n */\n`];

  // Collect types, deduplicating
  for (const exp of exports_) {
    modelLines.push(exp.fullText.trimEnd() + '\n');
  }

  const modelContent = modelLines.join('\n');

  // Remove extracted declarations from the component file
  // Build new src by removing them in reverse order (to preserve indices)
  let newSrc = src;
  const sortedByStart = [...exports_].sort((a, b) => b.startIdx - a.startIdx);
  for (const { startIdx, endIdx } of sortedByStart) {
    newSrc = newSrc.slice(0, startIdx) + newSrc.slice(endIdx);
  }

  // Add re-export at the top of the component file (after last import line)
  const lastImport = newSrc.lastIndexOf('import ');
  const importLineEnd = newSrc.indexOf('\n', lastImport);
  const reExport = `\nexport * from './${stem}.model';\n`;
  newSrc = newSrc.slice(0, importLineEnd + 1) + reExport + newSrc.slice(importLineEnd + 1);

  if (!DRY_RUN) {
    fs.writeFileSync(modelFile, modelContent, 'utf8');
    fs.writeFileSync(tsFile, newSrc, 'utf8');
  }

  console.log(`[model]    ${path.relative(ROOT, tsFile)} → ${path.relative(ROOT, modelFile)}`);
  modelCount++;
}

console.log('\n──────────────────────────────────────');
console.log(`Model files created: ${modelCount}`);
console.log(`Files renamed      : ${renamedCount}`);
if (DRY_RUN) console.log('(DRY RUN — no files written)');
if (errors.length) {
  console.log('\nWarnings:');
  errors.forEach((e) => console.log(' ', e));
}
