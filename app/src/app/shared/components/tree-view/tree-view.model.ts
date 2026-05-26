/**
 * Modelos e tipos do componente de árvore.
 */

/** Nó da árvore */
export interface AfTreeNode {
  id: string;
  label: string;
  icon?: string;
  children?: AfTreeNode[];
}
