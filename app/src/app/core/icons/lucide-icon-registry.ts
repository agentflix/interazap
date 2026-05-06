import { icons, type LucideIconData, type LucideIcons } from 'lucide-angular';

/**
 * Type assertion helper that validates a value as LucideIconData.
 * Used internally to cast raw icon data from the lucide-angular icons map.
 *
 * @param value - The value to cast
 * @returns The value typed as LucideIconData
 */
function asLucideIconData(value: unknown): LucideIconData {
  return value as LucideIconData;
}

/**
 * Builds a complete registry of all available Lucide icons.
 * Casts all icons from the lucide-angular library to the proper LucideIconData type.
 * Also adds aliases for legacy/common icon names used across the app.
 *
 * @returns A LucideIcons object mapping icon names to their icon data
 *
 * @example
 * ```typescript
 * const icons = buildLucideIconRegistry();
 * const searchIcon = icons['Search'];
 * ```
 */
export function buildLucideIconRegistry(): LucideIcons {
  const registry: LucideIcons = {};

  for (const [name, iconData] of Object.entries(icons as Record<string, unknown>)) {
    registry[name] = asLucideIconData(iconData);
  }

  if (registry['CloudUpload']) {
    registry['UploadCloud'] = registry['CloudUpload'];
  }

  if (registry['TriangleAlert']) {
    registry['AlertTriangle'] = registry['TriangleAlert'];
    registry['alert-triangle'] = registry['TriangleAlert'];
  }

  return registry;
}
