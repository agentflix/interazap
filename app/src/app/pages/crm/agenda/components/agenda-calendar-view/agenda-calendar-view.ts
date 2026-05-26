import { ChangeDetectionStrategy, Component, input, viewChild } from '@angular/core';
import { type FullCalendarComponent, FullCalendarModule } from '@fullcalendar/angular';
import { type CalendarOptions } from '@fullcalendar/core';

/**
 * Encapsula o FullCalendar em um container estilizado.
 * Expõe a API do calendário via getApi() para navegação pelo componente pai.
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
  /** Opções de configuração do FullCalendar. */
  readonly options = input.required<CalendarOptions>();

  private readonly calendarRef = viewChild<FullCalendarComponent>('calendar');

  /** Retorna a instância da API do FullCalendar para controle programático. */
  getApi(): ReturnType<FullCalendarComponent['getApi']> | undefined {
    return this.calendarRef()?.getApi();
  }
}
