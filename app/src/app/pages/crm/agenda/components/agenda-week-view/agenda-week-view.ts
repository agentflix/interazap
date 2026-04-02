import {
  ChangeDetectionStrategy,
  Component,
  computed,
  input,
  output,
  viewChild,
} from '@angular/core';
import { type FormControl } from '@angular/forms';
import { type CalendarOptions } from '@fullcalendar/core';
import { type Event as CRMEvent } from 'src/app/core/services/event.service';
import { AgendaCalendarViewComponent } from '../agenda-calendar-view/agenda-calendar-view';
import { AgendaSidebarComponent, type AgendaTypeOption } from '../agenda-sidebar/agenda-sidebar';

/**
 * Agenda week view component for the Crm module.
 * @selector app-agenda-week-view
 */
@Component({
  selector: 'app-agenda-week-view',
  standalone: true,
  imports: [AgendaCalendarViewComponent, AgendaSidebarComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-week-view.html',
})
export class AgendaWeekViewComponent {
  readonly options = input.required<CalendarOptions>();
  readonly events = input<CRMEvent[]>([]);
  readonly typeOptions = input<AgendaTypeOption[]>([]);
  readonly dropRemoveControl = input.required<FormControl<boolean>>();

  readonly eventClicked = output<CRMEvent>();

  private readonly calendarView = viewChild<AgendaCalendarViewComponent>('calendarView');

  readonly weekOptions = computed(
    (): CalendarOptions => ({
      ...this.options(),
      initialView: 'timeGridWeek',
    }),
  );

  getApi(): ReturnType<AgendaCalendarViewComponent['getApi']> | undefined {
    return this.calendarView()?.getApi();
  }
}
