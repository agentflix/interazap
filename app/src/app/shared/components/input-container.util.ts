/**
 * Resolves container classes for form inputs with backward compatibility.
 *
 * - When `classContainer` is `null`/`undefined`: spacing is controlled by `spacing`.
 * - When `classContainer` is an empty string: no margin is applied (legacy override).
 * - When `classContainer` contains explicit `mb-*`: explicit margin wins.
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
