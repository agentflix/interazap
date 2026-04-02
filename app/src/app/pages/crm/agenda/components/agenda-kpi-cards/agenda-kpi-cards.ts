import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import { AfCardComponent } from '@shared/components';
import { type Event as CRMEvent } from 'src/app/core/services/event.service';

/**
 * Displays 4 KPI stat cards for the agenda overview:
 * Today, This Week, This Month, and Overdue events.
 */
@Component({
  selector: 'app-agenda-kpi-cards',
  standalone: true,
  imports: [AfCardComponent, LucideAngularModule, DecimalPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-kpi-cards.html',
})
export class AgendaKpiCardsComponent {
  /** All events loaded for the current filters */
  readonly events = input<CRMEvent[]>([]);

  protected readonly cards = computed(() => {
    const events = this.events();
    const now = new Date();
    const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const endOfDay = new Date(startOfDay.getTime() + 86_400_000);

    const startOfWeek = new Date(startOfDay);
    startOfWeek.setDate(startOfDay.getDate() - startOfDay.getDay());
    const endOfWeek = new Date(startOfWeek.getTime() + 7 * 86_400_000);

    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59);

    const todayCount = events.filter((e) => {
      const d = new Date(e.starts_at);
      return d >= startOfDay && d < endOfDay;
    }).length;

    const weekCount = events.filter((e) => {
      const d = new Date(e.starts_at);
      return d >= startOfWeek && d < endOfWeek;
    }).length;

    const monthCount = events.filter((e) => {
      const d = new Date(e.starts_at);
      return d >= startOfMonth && d <= endOfMonth;
    }).length;

    const overdueCount = events.filter((e) => {
      return e.status === 'scheduled' && new Date(e.starts_at) < now;
    }).length;

    return [
      {
        label: 'Hoje',
        value: todayCount,
        subtitle: 'Eventos agendados para hoje',
        icon: 'calendar-clock',
        iconClass: 'bg-blue-50 dark:bg-blue-500/10 text-blue-500',
      },
      {
        label: 'Esta semana',
        value: weekCount,
        subtitle: 'Eventos na semana atual',
        icon: 'calendar-days',
        iconClass: 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500',
      },
      {
        label: 'Este mês',
        value: monthCount,
        subtitle: 'Eventos no mês atual',
        icon: 'calendar-range',
        iconClass: 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-500',
      },
      {
        label: 'Atrasados',
        value: overdueCount,
        subtitle: 'Eventos não realizados',
        icon: 'alert-triangle',
        iconClass: 'bg-amber-50 dark:bg-amber-500/10 text-amber-500',
      },
    ];
  });
}
