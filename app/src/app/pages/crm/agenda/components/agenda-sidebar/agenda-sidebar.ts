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
 * Sidebar da visualização de calendário com lista de próximos eventos
 * e chips de tipo de evento arrastáveis para criar novos eventos via drag-and-drop.
 */
@Component({
  selector: 'app-agenda-sidebar',
  standalone: true,
  imports: [AgendaUpcomingListComponent, CheckboxInputComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-sidebar.html',
})
export class AgendaSidebarComponent implements AfterViewInit {
  /** Todos os eventos para a lista de próximos. */
  readonly events = input<CRMEvent[]>([]);

  /** Opções de tipo de evento para os chips arrastáveis. */
  readonly typeOptions = input<AgendaTypeOption[]>([]);

  /** Controle do checkbox "remover após soltar". */
  readonly dropRemoveControl = input.required<FormControl<boolean>>();

  /** Emitido quando o usuário clica em um evento da lista. */
  readonly eventClicked = output<CRMEvent>();

  ngAfterViewInit(): void {
    this.initDraggables();
  }

  /** Retorna a classe CSS do chip de drag por tipo de evento. */
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

  /** Inicializa o Draggable do FullCalendar no container de eventos externos. */
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
