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
 * Agenda month view component for the Crm module.
 * @selector app-agenda-month-view
 */
@Component({
  selector: 'app-agenda-month-view',
  standalone: true,
  imports: [AgendaCalendarViewComponent, AgendaSidebarComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agenda-month-view.html',
})
export class AgendaMonthViewComponent {
  readonly options = input.required<CalendarOptions>();
  readonly events = input<CRMEvent[]>([]);
  readonly typeOptions = input<AgendaTypeOption[]>([]);
  readonly dropRemoveControl = input.required<FormControl<boolean>>();

  readonly eventClicked = output<CRMEvent>();

  private readonly calendarView = viewChild<AgendaCalendarViewComponent>('calendarView');

  readonly monthOptions = computed(
    (): CalendarOptions => ({
      ...this.options(),
      initialView: 'dayGridMonth',
    }),
  );

  getApi(): ReturnType<AgendaCalendarViewComponent['getApi']> | undefined {
    return this.calendarView()?.getApi();
  }
}
