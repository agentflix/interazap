/**
 * Models and types for tree-view component.
 */

/** Tree node */
export interface AfTreeNode {
  id: string;
  label: string;
  icon?: string;
  children?: AfTreeNode[];
}
