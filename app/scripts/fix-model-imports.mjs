/**
 * Fix: After extract-model-types.mjs ran, component files have
 * `export * from './{stem}.model'` but no matching import.
 * This script adds `import type { ... } from './{stem}.model'` so
 * internal uses of the extracted types still resolve.
 *
 * Usage: node scripts/fix-model-imports.mjs [--dry-run]
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../src');
const DRY_RUN = process.argv.includes('--dry-run');

/** Walk all .model.ts files (not .spec.ts) */
function* walkModelTs(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) yield* walkModelTs(full);
    else if (entry.isFile() && entry.name.endsWith('.model.ts') && !entry.name.endsWith('.spec.ts'))
      yield full;
  }
}

/** Extract exported interface/type/enum names from a model file */
function extractExportedNames(src) {
  const names = [];
  // Match: export interface/type/enum Name
  const re = /^export\s+(?:interface|type|enum)\s+(\w+)/gm;
  let m;
  while ((m = re.exec(src)) !== null) {
    names.push(m[1]);
  }
  return names;
}

let fixed = 0;
let skipped = 0;

for (const modelFile of walkModelTs(ROOT)) {
  const modelContent = fs.readFileSync(modelFile, 'utf8');
  const names = extractExportedNames(modelContent);
  if (names.length === 0) continue;

  // Corresponding component file: remove '.model' from stem
  const dir = path.dirname(modelFile);
  const modelBase = path.basename(modelFile); // e.g. tabs.model.ts
  const componentBase = modelBase.replace('.model.ts', '.ts'); // e.g. tabs.ts
  const componentFile = path.join(dir, componentBase);

  if (!fs.existsSync(componentFile)) {
    console.log(`[skip] No component file found for ${path.relative(ROOT, modelFile)}`);
    skipped++;
    continue;
  }

  const componentSrc = fs.readFileSync(componentFile, 'utf8');
  const stem = modelBase.replace('.model.ts', '');
  const reExportLine = `export * from './${stem}.model';`;

  // Check if re-export exists
  if (!componentSrc.includes(reExportLine)) {
    console.log(`[skip] No re-export found in ${path.relative(ROOT, componentFile)}`);
    skipped++;
    continue;
  }

  // Check if import already added
  const importLine = `import type { ${names.join(', ')} } from './${stem}.model';`;
  if (componentSrc.includes(`from './${stem}.model'`) && componentSrc.includes('import type')) {
    console.log(`[skip] Import already present in ${path.relative(ROOT, componentFile)}`);
    skipped++;
    continue;
  }

  // Replace `export * from '...'` with `import type { ... } + export * from '...'`
  const replacement = `import type { ${names.join(', ')} } from './${stem}.model';\n${reExportLine}`;
  const newSrc = componentSrc.replace(reExportLine, replacement);

  if (!DRY_RUN) {
    fs.writeFileSync(componentFile, newSrc, 'utf8');
  }

  console.log(`[fix] ${path.relative(ROOT, componentFile)} ← import { ${names.join(', ')} }`);
  fixed++;
}

console.log('\n──────────────────────────────────────');
console.log(`Fixed  : ${fixed}`);
console.log(`Skipped: ${skipped}`);
if (DRY_RUN) console.log('(DRY RUN — no files written)');
