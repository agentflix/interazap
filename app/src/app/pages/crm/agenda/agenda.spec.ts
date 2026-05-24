import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { By } from '@angular/platform-browser';
import { of } from 'rxjs';
import { Agenda } from './agenda';
import {
  EventService,
  type Event as CRMEvent,
  type EventFilters,
} from 'src/app/core/services/event.service';
import { UserService } from 'src/app/core/services/user.service';
import { ContactService } from 'src/app/core/services/contact.service';
import { CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { AgendaEventEditorComponent } from './components/agenda-event-editor/agenda-event-editor';

interface CalendarApiLike {
  removeAllEvents: () => void;
  addEventSource: (events: { id?: string }[]) => void;
}

describe('Agenda', () => {
  let component: Agenda;
  let fixture: ComponentFixture<Agenda>;

  const eventListResponse = {
    data: [] as CRMEvent[],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    },
  };

  const mockEvent = {
    id: 'event-1',
    tenant_id: 'tenant-1',
    title: 'Test Meeting',
    starts_at: '2026-01-30T10:00:00Z',
    ends_at: '2026-01-30T11:00:00Z',
    is_all_day: false,
    status: 'scheduled',
    type: 'meeting',
    created_at: '2026-01-30T10:00:00Z',
    updated_at: '2026-01-30T10:00:00Z',
  } as CRMEvent;

  const eventServiceMock = {
    list: vi.fn(() => of(eventListResponse)),
    create: vi.fn(() => of(mockEvent)),
    update: vi.fn(() => of(mockEvent)),
    delete: vi.fn(() => of(undefined)),
  };

  const userServiceMock = {
    list: vi.fn(() => of({ data: [], meta: eventListResponse.meta })),
  };

  const contactServiceMock = {
    list: vi.fn(() => of({ data: [], meta: eventListResponse.meta })),
  };

  const crmCompanyServiceMock = {
    all: vi.fn(() => of([])),
  };

  const negotiationServiceMock = {
    list: vi.fn(() => of(eventListResponse)),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      imports: [Agenda],
      providers: [
        provideRouter([]),
        { provide: EventService, useValue: eventServiceMock },
        { provide: UserService, useValue: userServiceMock },
        { provide: ContactService, useValue: contactServiceMock },
        { provide: CRMCompanyService, useValue: crmCompanyServiceMock },
        { provide: NegotiationService, useValue: negotiationServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Agenda);
    component = fixture.componentInstance;
    fixture.detectChanges();

    eventServiceMock.list.mockClear();
  });

  it('should switch view mode', () => {
    component.setView('list');
    expect(component.viewMode()).toBe('list');

    component.setView('calendar');
    expect(component.viewMode()).toBe('calendar');
  });

  it('should switch calendar display mode between month, week and day', () => {
    expect(component.calendarDisplayMode()).toBe('month');

    component.setCalendarDisplayMode('week');
    expect(component.calendarDisplayMode()).toBe('week');

    component.setCalendarDisplayMode('day');
    expect(component.calendarDisplayMode()).toBe('day');

    component.setCalendarDisplayMode('month');
    expect(component.calendarDisplayMode()).toBe('month');
  });

  it('should expose options and labels', () => {
    expect(component.statusOptions.length).toBe(3);
    expect(component.typeOptions.length).toBe(6);
    expect(component.filterStatusOptions[0].value).toBe('all');

    expect(component.statusLabel('scheduled')).toBe('Agendado');
    expect(component.typeLabel('meeting')).toBe('Reunião');
  });

  it('should resolve event classes', () => {
    expect(component.getTypeClass('meeting')).toContain('bg-info');
    expect(component.getTypeClass('deadline')).toContain('bg-danger');
    expect(component.getTypeClass('other')).toContain('bg-neutral');
  });

  it('should open and close calendar modal', () => {
    component.openCalendarCreate();
    expect(component.isCalendarModalOpen()).toBe(true);
    expect(component.calendarEditingItem()).toBeNull();

    component.openCalendarEdit(mockEvent);
    expect(component.calendarEditingItem()).toEqual(mockEvent);

    component.closeCalendarModal();
    expect(component.isCalendarModalOpen()).toBe(false);
  });

  it('should close editor and reload calendar when editor emits saved', () => {
    const loadCalendarEventsSpy = vi.spyOn(component, 'loadCalendarEvents');

    component.openCalendarCreate();
    fixture.detectChanges();

    const editorDebugElement = fixture.debugElement.query(By.directive(AgendaEventEditorComponent));
    const editorComponent = editorDebugElement.componentInstance as AgendaEventEditorComponent;

    editorComponent.saved.emit(mockEvent);

    expect(component.isCalendarModalOpen()).toBe(false);
    expect(component.calendarEditingItem()).toBeNull();
    expect(component.calendarInitialData()).toBeNull();
    expect(loadCalendarEventsSpy).toHaveBeenCalledTimes(1);
  });

  it('should close editor when editor emits closed (cancel flow)', () => {
    component.openCalendarEdit(mockEvent);
    fixture.detectChanges();

    const editorDebugElement = fixture.debugElement.query(By.directive(AgendaEventEditorComponent));
    const editorComponent = editorDebugElement.componentInstance as AgendaEventEditorComponent;

    editorComponent.closed.emit();

    expect(component.isCalendarModalOpen()).toBe(false);
    expect(component.calendarEditingItem()).toBeNull();
    expect(component.calendarInitialData()).toBeNull();
  });

  it('should keep filters and apply them when switching back to calendar mode', () => {
    component.setView('list');
    component.typeFilterControl.setValue('meeting');
    component.userIdFilterControl.setValue('user-1');
    component.isAllDayFilterControl.setValue('yes');
    component.calendarSearchControl.setValue('  follow up  ');

    eventServiceMock.list.mockClear();

    component.setView('calendar');

    expect(eventServiceMock.list).toHaveBeenCalledTimes(1);

    const lastCall = eventServiceMock.list.mock.calls.at(-1);
    expect(lastCall).toBeDefined();

    if (!lastCall) {
      throw new Error('Expected EventService.list to have at least one call');
    }

    const typedCall = lastCall as [EventFilters?];
    const filters = typedCall[0];
    expect(filters).toBeDefined();
    expect(filters?.search).toBe('follow up');
    expect(filters?.type).toBe('meeting');
    expect(filters?.user_id).toBe('user-1');
    expect(filters?.is_all_day).toBe(true);
  });

  it('should sync events on the active calendar mode after loading events', () => {
    const weekApi: CalendarApiLike = {
      removeAllEvents: vi.fn(),
      addEventSource: vi.fn(),
    };

    vi.spyOn(
      component as object as { getActiveCalendarApi: () => CalendarApiLike },
      'getActiveCalendarApi',
    ).mockReturnValue(weekApi);

    component.setCalendarDisplayMode('week');
    eventServiceMock.list.mockReturnValueOnce(
      of({
        data: [mockEvent],
        meta: eventListResponse.meta,
      }),
    );

    component.loadCalendarEvents();

    expect(component.calendarDisplayMode()).toBe('week');
    expect(component.allEvents()).toEqual([mockEvent]);
    expect(weekApi.removeAllEvents).toHaveBeenCalledTimes(1);
    expect(weekApi.addEventSource).toHaveBeenCalledTimes(1);

    const addEventSourceSpy = weekApi.addEventSource as ReturnType<typeof vi.fn>;
    const firstCallArg = addEventSourceSpy.mock.calls[0]?.[0] as { id?: string }[] | undefined;
    expect(firstCallArg).toBeDefined();
    expect(firstCallArg?.[0]?.id).toBe(mockEvent.id);
  });

  it('should preserve selected filters while toggling between list and calendar', () => {
    component.typeFilterControl.setValue('deadline');
    component.hasRemindersFilterControl.setValue('no');
    component.locationFilterControl.setValue('HQ');

    component.setView('list');
    component.setView('calendar');

    expect(component.typeFilterControl.value).toBe('deadline');
    expect(component.hasRemindersFilterControl.value).toBe('no');
    expect(component.locationFilterControl.value).toBe('HQ');
  });
});
