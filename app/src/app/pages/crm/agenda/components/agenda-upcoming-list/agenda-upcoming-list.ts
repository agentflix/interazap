import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { DatePipe } from '@angular/common';
import { type Event as CRMEvent, type EventType } from 'src/app/core/services/event.service';

/**
 * Exibe os próximos 5 eventos agendados ordenados por horário de início.
 * Cada evento é clicável para abrir o modal de edição.
 */
@Component({
  selector: 'app-agenda-upcoming-list',
  standalone: true,
  imports: [DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-upcoming-list.html',
})
export class AgendaUpcomingListComponent {
  /** Todos os eventos para calcular os próximos. */
  readonly events = input<CRMEvent[]>([]);

  /** Emitido quando o usuário clica em um evento. */
  readonly eventClicked = output<CRMEvent>();

  protected readonly upcomingEvents = computed(() => {
    const now = new Date();
    return this.events()
      .filter((e) => e.status === 'scheduled' && new Date(e.starts_at) >= now)
      .sort((a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime())
      .slice(0, 5);
  });

  protected getTypeDotClass(type: EventType): string {
    const map: Record<EventType, string> = {
      meeting: 'bg-info-500',
      call: 'bg-primary-600',
      task: 'bg-success-500',
      deadline: 'bg-danger-500',
      reminder: 'bg-primary-400',
      other: 'bg-neutral-500',
    };
    return map[type] ?? 'bg-neutral-500';
  }
}
