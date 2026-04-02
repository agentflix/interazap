#!/usr/bin/env node
/**
 * Script to add JSDoc comments to Angular components that lack them.
 * Adds class-level JSDoc and @Input/@Output/@signal documentation.
 */
import { readFileSync, writeFileSync } from 'node:fs';

let files = process.argv.slice(2);

// Support reading from a file list
if (files.length === 1 && files[0].startsWith('@')) {
  const listFile = files[0].slice(1);
  files = readFileSync(listFile, 'utf-8')
    .split('\n')
    .map((l) => l.trim())
    .filter(Boolean);
}

if (files.length === 0) {
  console.error('Usage: node add-jsdoc.mjs <file1.ts> [file2.ts] ...');
  console.error('       node add-jsdoc.mjs @filelist.txt');
  process.exit(1);
}

/**
 * Derive a human-readable description from a class name.
 * E.g. "CircuitBreakerStatusComponent" → "Circuit breaker status"
 */
function classNameToDescription(name) {
  // Remove common suffixes
  let clean = name
    .replace(/Component$/, '')
    .replace(/Directive$/, '')
    .replace(/Pipe$/, '');

  // Split PascalCase into words
  const words = clean.replace(/([a-z])([A-Z])/g, '$1 $2').replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2');

  return words.charAt(0).toUpperCase() + words.slice(1).toLowerCase();
}

/**
 * Extract the Angular selector from @Component decorator.
 */
function extractSelector(content) {
  const match = content.match(/selector:\s*['"]([^'"]+)['"]/);
  return match ? match[1] : null;
}

/**
 * Determine component type from file path and decorator.
 */
function getComponentType(filePath, content) {
  if (filePath.includes('/pages/')) {
    if (filePath.match(/\/(components|tabs)\//)) return 'component';
    return 'page component';
  }
  if (filePath.includes('/shared/')) return 'shared component';
  return 'component';
}

/**
 * Build a module name from file path.
 */
function getModuleName(filePath) {
  const match = filePath.match(/pages\/([^/]+)\//);
  if (match) return match[1].charAt(0).toUpperCase() + match[1].slice(1);
  if (filePath.includes('/shared/')) return 'Shared';
  return '';
}

let modified = 0;
let skipped = 0;

for (const file of files) {
  let content = readFileSync(file, 'utf-8');
  const lines = content.split('\n');

  // Find "export class" line
  const classIdx = lines.findIndex((l) => /^export\s+class\s+/.test(l));
  if (classIdx === -1) {
    console.log(`SKIP (no export class): ${file}`);
    skipped++;
    continue;
  }

  // Check if there's already a /** */ block right before the class
  let prevNonEmpty = classIdx - 1;
  while (prevNonEmpty >= 0 && lines[prevNonEmpty].trim() === '') prevNonEmpty--;

  if (
    prevNonEmpty >= 0 &&
    (lines[prevNonEmpty].trim().endsWith('*/') || lines[prevNonEmpty].trim().startsWith('*/'))
  ) {
    console.log(`SKIP (already has jsdoc): ${file}`);
    skipped++;
    continue;
  }

  // Also check if the @Component decorator line is right above
  // We want to insert ABOVE the @Component decorator
  let decoratorIdx = classIdx - 1;
  while (decoratorIdx >= 0) {
    const trimmed = lines[decoratorIdx].trim();
    if (
      trimmed.startsWith('@Component') ||
      trimmed.startsWith('@Directive') ||
      trimmed.startsWith('@Pipe')
    ) {
      break;
    }
    if (
      trimmed === '' ||
      trimmed.startsWith(')') ||
      trimmed.startsWith('}') ||
      trimmed.startsWith('`') ||
      trimmed.startsWith("'") ||
      trimmed.startsWith(',') ||
      trimmed.startsWith('//') ||
      /^[a-zA-Z]/.test(trimmed) ||
      trimmed.startsWith('[') ||
      trimmed.startsWith('{') ||
      trimmed.startsWith('imports') ||
      trimmed.startsWith('standalone') ||
      trimmed.startsWith('templateUrl') ||
      trimmed.startsWith('template') ||
      trimmed.startsWith('styleUrl') ||
      trimmed.startsWith('styles') ||
      trimmed.startsWith('selector') ||
      trimmed.startsWith('changeDetection') ||
      trimmed.startsWith('host') ||
      trimmed.startsWith('providers') ||
      trimmed.startsWith('encapsulation')
    ) {
      decoratorIdx--;
      continue;
    }
    break;
  }

  // Find the true start of the decorator block
  let insertIdx = decoratorIdx >= 0 ? decoratorIdx : classIdx;

  // Check that there's no jsDoc above the decorator either
  let checkAbove = insertIdx - 1;
  while (checkAbove >= 0 && lines[checkAbove].trim() === '') checkAbove--;
  if (
    checkAbove >= 0 &&
    (lines[checkAbove].trim().endsWith('*/') || lines[checkAbove].trim().startsWith('*/'))
  ) {
    console.log(`SKIP (jsdoc above decorator): ${file}`);
    skipped++;
    continue;
  }

  // Extract info for jsdoc
  const className = (lines[classIdx].match(/export\s+class\s+(\w+)/) || [])[1] || 'Unknown';
  const selector = extractSelector(content);
  const compType = getComponentType(file, content);
  const moduleName = getModuleName(file);
  const description = classNameToDescription(className);

  // Build jsdoc
  const jsdocLines = [];
  jsdocLines.push('/**');
  if (moduleName) {
    jsdocLines.push(` * ${description} ${compType} for the ${moduleName} module.`);
  } else {
    jsdocLines.push(` * ${description} ${compType}.`);
  }
  if (selector) {
    jsdocLines.push(` * @selector ${selector}`);
  }
  jsdocLines.push(' */');

  // Insert above the decorator (or class if no decorator found)
  lines.splice(insertIdx, 0, ...jsdocLines);

  writeFileSync(file, lines.join('\n'));
  console.log(`ADDED jsdoc to ${className} in ${file}`);
  modified++;
}

console.log(`\nDone: ${modified} modified, ${skipped} skipped`);
