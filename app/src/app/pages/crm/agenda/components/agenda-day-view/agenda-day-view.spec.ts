import { Component, input, output } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { FormControl } from '@angular/forms';
import { type CalendarOptions } from '@fullcalendar/core';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type Event as CRMEvent } from 'src/app/core/services/event.service';
import { AgendaDayViewComponent } from './agenda-day-view';
import { AgendaCalendarViewComponent } from '../agenda-calendar-view/agenda-calendar-view';
import { AgendaSidebarComponent, type AgendaTypeOption } from '../agenda-sidebar/agenda-sidebar';

@Component({
  selector: 'app-agenda-calendar-view',
  standalone: true,
  template: '',
})
class StubAgendaCalendarViewComponent {
  readonly options = input.required<CalendarOptions>();

  private readonly calendarApi = {
    next: vi.fn(),
  };

  getApi(): { next: ReturnType<typeof vi.fn> } {
    return this.calendarApi;
  }
}

@Component({
  selector: 'app-agenda-sidebar',
  standalone: true,
  template: '',
})
class StubAgendaSidebarComponent {
  readonly events = input<CRMEvent[]>([]);
  readonly typeOptions = input<AgendaTypeOption[]>([]);
  readonly dropRemoveControl = input.required<FormControl<boolean>>();
  readonly eventClicked = output<CRMEvent>();
}

describe('AgendaDayViewComponent', () => {
  let component: AgendaDayViewComponent;
  let fixture: ComponentFixture<AgendaDayViewComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AgendaDayViewComponent],
    })
      .overrideComponent(AgendaDayViewComponent, {
        remove: {
          imports: [AgendaCalendarViewComponent, AgendaSidebarComponent],
        },
        add: {
          imports: [StubAgendaCalendarViewComponent, StubAgendaSidebarComponent],
        },
      })
      .compileComponents();

    fixture = TestBed.createComponent(AgendaDayViewComponent);
    component = fixture.componentInstance;

    fixture.componentRef.setInput('options', { locale: 'pt-br' } as CalendarOptions);
    fixture.componentRef.setInput(
      'dropRemoveControl',
      new FormControl(false, { nonNullable: true }),
    );
    fixture.detectChanges();
  });

  it('deve definir initialView como timeGridDay', () => {
    expect(component.dayOptions().initialView).toBe('timeGridDay');
  });

  it('deve delegar getApi para AgendaCalendarView', () => {
    const calendarDebug = fixture.debugElement.query(By.directive(StubAgendaCalendarViewComponent));
    const calendar = calendarDebug.componentInstance as StubAgendaCalendarViewComponent;

    expect(component.getApi()).toBe(calendar.getApi());
  });
});
