/**
 * Resolve a cor semântica para score de sentimento.
 */
export function sentimentColor(
  score: number | null,
): 'gray' | 'green' | 'yellow' | 'orange' | 'red' {
  if (score === null) return 'gray';
  if (score <= 30) return 'green';
  if (score <= 60) return 'yellow';
  if (score <= 80) return 'orange';
  return 'red';
}

/**
 * Resolve o rótulo semântico para score de sentimento.
 */
export function sentimentLabel(
  score: number | null,
): '' | 'Positivo' | 'Neutro' | 'Atenção' | 'Crítico' {
  if (score === null) return '';
  if (score <= 30) return 'Positivo';
  if (score <= 60) return 'Neutro';
  if (score <= 80) return 'Atenção';
  return 'Crítico';
}
