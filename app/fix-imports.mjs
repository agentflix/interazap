/**
 * Fixes no-duplicate-imports: merges duplicate import statements from same module.
 * Run with: node fix-imports.mjs
 */
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';

const ROOT = '/Users/rafael.silva/Documents/interazap/app-new';

// Get files with duplicate imports from lint output
let lintOut = '';
try {
  lintOut = execSync('pnpm --prefix ' + ROOT + ' run lint 2>&1', {
    encoding: 'utf8',
    cwd: ROOT,
    maxBuffer: 50 * 1024 * 1024,
  });
} catch (e) {
  lintOut = e.stdout ?? e.message;
}

const filesWithDups = new Set();
let currentFile = '';
for (const line of lintOut.split('\n')) {
  const trimmed = line.trim();
  if (line.match(/^\/.*\.ts$/)) {
    currentFile = line;
  } else if (trimmed.includes('no-duplicate-imports') && currentFile) {
    filesWithDups.add(currentFile);
  }
}

console.log(`Files with duplicate imports: ${filesWithDups.size}`);

/**
 * Parses a single-line import statement.
 * Handles: import { A, B } from 'x'; and import type { A } from 'x';
 * Returns null if not a recognizable single-line import.
 */
function parseSingleLineImport(line) {
  // import type { ... } from 'module';
  const typeMatch = line.match(/^import\s+type\s+\{([^}]+)\}\s+from\s+['"]([^'"]+)['"]\s*;?\s*$/);
  if (typeMatch) {
    const names = typeMatch[1]
      .split(',')
      .map((n) => n.trim())
      .filter(Boolean);
    return {
      module: typeMatch[2],
      names: names.map((n) => ({ name: n, isType: true })),
      originalAllType: true,
    };
  }

  // import { type A, B } from 'module'; (inline type specifiers)
  const inlineMatch = line.match(/^import\s+\{([^}]+)\}\s+from\s+['"]([^'"]+)['"]\s*;?\s*$/);
  if (inlineMatch) {
    const parts = inlineMatch[1]
      .split(',')
      .map((n) => n.trim())
      .filter(Boolean);
    const names = parts.map((p) => {
      const tm = p.match(/^type\s+(.+)$/);
      return tm ? { name: tm[1].trim(), isType: true } : { name: p, isType: false };
    });
    return { module: inlineMatch[2], names, originalAllType: false };
  }

  // import DefaultExport from 'module'; (no braces) — leave as is
  return null;
}

/**
 * Generates a merged import line from a list of named imports.
 */
function generateImport(module, names) {
  const allType = names.every((n) => n.isType);
  if (allType) {
    const namesList = names.map((n) => n.name).join(', ');
    return `import type { ${namesList} } from '${module}';`;
  }
  const namesList = names.map((n) => (n.isType ? `type ${n.name}` : n.name)).join(', ');
  return `import { ${namesList} } from '${module}';`;
}

let totalFixed = 0;

for (const filePath of filesWithDups) {
  if (!fs.existsSync(filePath)) continue;

  const content = fs.readFileSync(filePath, 'utf8');
  const lines = content.split('\n');

  // Collect all single-line import info indexed by line number
  const importInfoByLine = new Map();
  const moduleToLines = new Map();

  for (let i = 0; i < lines.length; i++) {
    const info = parseSingleLineImport(lines[i]);
    if (info) {
      importInfoByLine.set(i, info);
      if (!moduleToLines.has(info.module)) moduleToLines.set(info.module, []);
      moduleToLines.get(info.module).push(i);
    }
  }

  // Find modules with > 1 import line
  const modulesToMerge = new Map();
  for (const [mod, lineNums] of moduleToLines) {
    if (lineNums.length > 1) {
      modulesToMerge.set(mod, lineNums);
    }
  }

  if (modulesToMerge.size === 0) continue;

  // Build merged imports
  const mergedByModule = new Map();
  for (const [mod, lineNums] of modulesToMerge) {
    const allNames = [];
    for (const ln of lineNums) {
      allNames.push(...importInfoByLine.get(ln).names);
    }
    // Deduplicate by name
    const seen = new Set();
    const unique = [];
    for (const n of allNames) {
      if (!seen.has(n.name)) {
        seen.add(n.name);
        unique.push(n);
      }
    }
    mergedByModule.set(mod, generateImport(mod, unique));
  }

  // Rebuild file lines
  const processedModules = new Set();
  const newLines = [];

  for (let i = 0; i < lines.length; i++) {
    const info = importInfoByLine.get(i);
    if (info && modulesToMerge.has(info.module)) {
      const mod = info.module;
      if (!processedModules.has(mod)) {
        // First occurrence → emit merged line
        newLines.push(mergedByModule.get(mod));
        processedModules.add(mod);
      }
      // Subsequent occurrences → skip
    } else {
      newLines.push(lines[i]);
    }
  }

  const newContent = newLines.join('\n');
  if (newContent !== content) {
    fs.writeFileSync(filePath, newContent, 'utf8');
    console.log(`Fixed: ${path.relative(ROOT, filePath)}`);
    totalFixed++;
  }
}

console.log(`\nTotal files fixed: ${totalFixed}`);
