import { icons, type LucideIconData, type LucideIcons } from 'lucide-angular';

/**
 * Auxiliar de asserção de tipo para converter um valor em LucideIconData.
 * Usado internamente para tipar os dados brutos do mapa de ícones do lucide-angular.
 *
 * @param value Valor a ser convertido
 * @returns O valor tipado como LucideIconData
 */
function asLucideIconData(value: unknown): LucideIconData {
  return value as LucideIconData;
}

/**
 * Constrói o registro completo de todos os ícones Lucide disponíveis.
 * Converte todos os ícones da biblioteca lucide-angular para o tipo LucideIconData correto.
 * Também adiciona aliases para nomes legados e comuns usados na aplicação.
 *
 * @returns Objeto LucideIcons mapeando nomes de ícones para seus dados
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
