/**
 * Models and types for timeline component.
 */

/** Timeline entry definition */
export interface AfTimelineEntry {
  title: string;
  description?: string;
  timestamp: string;
  icon?: string;
  variant?: 'default' | 'success' | 'danger' | 'warning' | 'info';
}
