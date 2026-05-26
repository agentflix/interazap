/**
 * Resolve as classes do contêiner para campos de formulário com compatibilidade retroativa.
 *
 * - Quando `classContainer` é `null`/`undefined`: o espaçamento é controlado por `spacing`.
 * - Quando `classContainer` é uma string vazia: nenhuma margem é aplicada (override legado).
 * - Quando `classContainer` contém `mb-*` explícito: a margem explícita prevalece.
 */
export function resolveInputContainerClass(
  classContainer: string | null | undefined,
  spacing: boolean,
  defaultSpacing = 'mb-4',
): string {
  if (classContainer === null || classContainer === undefined) {
    return spacing ? defaultSpacing : '';
  }

  const trimmed = classContainer.trim();
  if (trimmed.length === 0) {
    return '';
  }

  const tokens = trimmed.split(/\s+/).filter(Boolean);
  const marginTokens = tokens.filter((token) => /^mb-(?:\[[^\]]+\]|-?\d+(?:\.\d+)?)$/.test(token));
  const otherTokens = tokens.filter((token) => !/^mb-(?:\[[^\]]+\]|-?\d+(?:\.\d+)?)$/.test(token));

  if (marginTokens.length > 0) {
    return [...marginTokens, ...otherTokens].join(' ');
  }

  return [spacing ? defaultSpacing : '', ...otherTokens].filter(Boolean).join(' ');
}
