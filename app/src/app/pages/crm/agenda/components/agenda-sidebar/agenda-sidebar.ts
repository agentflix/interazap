import {
  type AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  input,
  output,
} from '@angular/core';
import { type FormControl } from '@angular/forms';
import { Draggable } from '@fullcalendar/interaction';
import { CheckboxInputComponent } from '@shared/components/inputs';
import { type Event as CRMEvent, type EventType } from 'src/app/core/services/event.service';
import { AgendaUpcomingListComponent } from '../agenda-upcoming-list/agenda-upcoming-list';

import type { AgendaTypeOption } from './agenda-sidebar.model';
export * from './agenda-sidebar.model';



/**
 * Sidebar for the calendar view containing:
 * 1. Upcoming events list
 * 2. Drag-and-drop event type chips (preserved from original)
 */
@Component({
  selector: 'app-agenda-sidebar',
  standalone: true,
  imports: [AgendaUpcomingListComponent, CheckboxInputComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-sidebar.html',
})
export class AgendaSidebarComponent implements AfterViewInit {
  /** All events for upcoming list */
  readonly events = input<CRMEvent[]>([]);

  /** Event type options for drag chips */
  readonly typeOptions = input<AgendaTypeOption[]>([]);

  /** Control for "remove after drop" checkbox */
  readonly dropRemoveControl = input.required<FormControl<boolean>>();

  /** Emitted when an upcoming event is clicked */
  readonly eventClicked = output<CRMEvent>();

  ngAfterViewInit(): void {
    this.initDraggables();
  }

  /** CSS class for drag event chip by type */
  protected getTypeClass(type: EventType): string {
    const map: Record<EventType, string> = {
      meeting: '!bg-info-500 !border-info-500',
      call: '!bg-primary-600 !border-primary-600',
      task: '!bg-success-500 !border-success-500',
      deadline: '!bg-danger-500 !border-danger-500',
      reminder: '!bg-primary-400 !border-primary-400',
      other: '!bg-neutral-500 !border-neutral-500',
    };
    return map[type] ?? '!bg-neutral-500 !border-neutral-500';
  }

  /** Initialize FullCalendar Draggable on external events container */
  private initDraggables(): void {
    const externalEl = document.getElementById('external-events');
    if (externalEl) {
      new Draggable(externalEl, {
        itemSelector: '.external-event',
        eventData: (eventEl: HTMLElement): { title: string } => ({
          title: eventEl.innerText.trim(),
        }),
      });
    }
  }
}
