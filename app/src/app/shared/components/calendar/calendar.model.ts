/** Modelos e tipos do componente de calendário. */

/** Evento exibido em uma célula do calendário. */
export interface AfCalendarEvent {
  id: string;
  title: string;
  /** Data no formato YYYY-MM-DD */
  date: string;
  /** Cor de destaque do evento (CSS) */
  color?: string;
}
