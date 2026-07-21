/**
 * Modelos e tipos do componente de seleção de cor.
 */

/** Tamanhos disponíveis para o campo */
export type AfInputSize = 'sm' | 'md';

/**
 * Paleta harmônica para categorização no CRM (tags, funis, etapas).
 *
 * Ancorada no verde de marca da landing (#2fc85a) e estendida com tons
 * de luminância/saturação equilibradas que mantêm contraste tanto sobre
 * o canvas escuro (#14161a) quanto no tema claro. Serve como conjunto de
 * atalhos do seletor de cor — o usuário ainda pode escolher qualquer hex.
 */
export const CRM_COLOR_PRESETS: readonly string[] = [
  '#2fc85a', // verde marca
  '#10b981', // esmeralda
  '#14b8a6', // teal
  '#22b8cf', // ciano
  '#3b82f6', // azul
  '#6366f1', // índigo
  '#8b5cf6', // violeta
  '#d6409f', // magenta
  '#ec4899', // rosa
  '#f59e0b', // âmbar
  '#f97316', // laranja
  '#ef4444', // vermelho
  '#64748b', // ardósia (neutro)
];
