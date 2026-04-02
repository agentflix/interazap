import { ChangeDetectionStrategy, Component, input, viewChild } from '@angular/core';
import { type FullCalendarComponent, FullCalendarModule } from '@fullcalendar/angular';
import { type CalendarOptions } from '@fullcalendar/core';

/**
 * Wraps FullCalendar in a styled card container.
 * Exposes the calendar API via getApi() for parent navigation.
 */
@Component({
  selector: 'app-agenda-calendar-view',
  standalone: true,
  imports: [FullCalendarModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-calendar-view.html',
  styleUrl: './agenda-calendar-view.scss',
})
export class AgendaCalendarViewComponent {
  /** FullCalendar configuration options */
  readonly options = input.required<CalendarOptions>();

  private readonly calendarRef = viewChild<FullCalendarComponent>('calendar');

  /** Get the FullCalendar API instance for programmatic control */
  getApi(): ReturnType<FullCalendarComponent['getApi']> | undefined {
    return this.calendarRef()?.getApi();
  }
}
