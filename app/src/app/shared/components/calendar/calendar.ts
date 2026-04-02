import { Component, ChangeDetectionStrategy, input, output, computed, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfCalendarEvent } from './calendar.model';
export * from './calendar.model';



/**
 * AfCalendarComponent — Monthly calendar view with events.
 *
 * @example
 * ```html
 * <af-calendar [events]="events" (dateSelected)="onDate($event)" />
 * ```
 */
@Component({
  selector: 'af-calendar',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './calendar.html',
})
export class AfCalendarComponent {
  /** Events to display */
  readonly events = input<AfCalendarEvent[]>([]);

  /** Emitted when a date cell is clicked */
  readonly dateSelected = output<string>();

  protected readonly weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

  protected readonly currentMonth = signal(new Date().getMonth());
  protected readonly currentYear = signal(new Date().getFullYear());

  protected readonly monthYearLabel = computed(() => {
    const months = [
      'Janeiro',
      'Fevereiro',
      'Março',
      'Abril',
      'Maio',
      'Junho',
      'Julho',
      'Agosto',
      'Setembro',
      'Outubro',
      'Novembro',
      'Dezembro',
    ];
    return `${months[this.currentMonth()]} ${this.currentYear()}`;
  });

  protected readonly calendarDays = computed(() => {
    const year = this.currentYear();
    const month = this.currentMonth();
    const today = new Date();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    const events = this.events();

    const cells: {
      day: number;
      currentMonth: boolean;
      isToday: boolean;
      dateStr: string;
      events: AfCalendarEvent[];
    }[] = [];

    // Previous month fill
    for (let i = firstDay - 1; i >= 0; i--) {
      const d = daysInPrevMonth - i;
      const dt = new Date(year, month - 1, d);
      const ds = this.formatDate(dt);
      cells.push({
        day: d,
        currentMonth: false,
        isToday: false,
        dateStr: ds,
        events: events.filter((e) => e.date === ds),
      });
    }

    // Current month
    for (let d = 1; d <= daysInMonth; d++) {
      const dt = new Date(year, month, d);
      const ds = this.formatDate(dt);
      const isTd =
        dt.getDate() === today.getDate() &&
        dt.getMonth() === today.getMonth() &&
        dt.getFullYear() === today.getFullYear();
      cells.push({
        day: d,
        currentMonth: true,
        isToday: isTd,
        dateStr: ds,
        events: events.filter((e) => e.date === ds),
      });
    }

    // Next month fill
    const remaining = 42 - cells.length;
    for (let d = 1; d <= remaining; d++) {
      const dt = new Date(year, month + 1, d);
      const ds = this.formatDate(dt);
      cells.push({
        day: d,
        currentMonth: false,
        isToday: false,
        dateStr: ds,
        events: events.filter((e) => e.date === ds),
      });
    }

    return cells;
  });

  protected prevMonth(): void {
    if (this.currentMonth() === 0) {
      this.currentMonth.set(11);
      this.currentYear.update((y) => y - 1);
    } else {
      this.currentMonth.update((m) => m - 1);
    }
  }

  protected nextMonth(): void {
    if (this.currentMonth() === 11) {
      this.currentMonth.set(0);
      this.currentYear.update((y) => y + 1);
    } else {
      this.currentMonth.update((m) => m + 1);
    }
  }

  private formatDate(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }
}
