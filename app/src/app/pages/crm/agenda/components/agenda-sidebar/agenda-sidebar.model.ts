/**
 * Models and types for agenda-sidebar component.
 */

import { type EventType } from 'src/app/core/services/event.service';

/** Type option for drag-and-drop events */
export interface AgendaTypeOption {
  id: EventType;
  label: string;
}
