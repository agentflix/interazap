/**
 * Models and types for calendar component.
 */

/** Calendar event */
export interface AfCalendarEvent {
  id: string;
  title: string;
  date: string; // YYYY-MM-DD
  color?: string;
}
