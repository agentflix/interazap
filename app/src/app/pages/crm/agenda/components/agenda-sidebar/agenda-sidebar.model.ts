/**
 * Modelos e tipos para o componente de sidebar da agenda.
 */

import { type EventType } from 'src/app/core/services/event.service';

/** Opção de tipo de evento para chips de drag-and-drop. */
export interface AgendaTypeOption {
  id: EventType;
  label: string;
}
