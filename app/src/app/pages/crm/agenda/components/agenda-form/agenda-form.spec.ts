import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { AgendaFormComponent } from './agenda-form';
import { EventService } from 'src/app/core/services/event.service';
import { of } from 'rxjs';

describe('AgendaFormComponent', () => {
  let component: AgendaFormComponent;
  let fixture: ComponentFixture<AgendaFormComponent>;
  let eventServiceMock: Partial<EventService>;

  beforeEach(async () => {
    eventServiceMock = {
      create: vi.fn().mockReturnValue(of({ id: '1' })),
      update: vi.fn().mockReturnValue(of({ id: '1' })),
    };

    await TestBed.configureTestingModule({
      imports: [AgendaFormComponent],
      providers: [{ provide: EventService, useValue: eventServiceMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(AgendaFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('cria componente corretamente', () => {
    expect(component).toBeTruthy();
  });

  it('inicializa form com campos obrigatórios', () => {
    expect(component.form).toBeDefined();
    expect(component.form.get('title')).toBeDefined();
  });

  it('possui options de status', () => {
    expect(component.statusSelectOptions.length).toBeGreaterThan(0);
    expect(component.statusSelectOptions[0]).toEqual({ value: 'scheduled', label: 'Agendado' });
  });

  it('possui options de tipo', () => {
    expect(component.typeSelectOptions.length).toBeGreaterThan(0);
    expect(component.typeSelectOptions[0]).toEqual({ value: 'meeting', label: 'Reunião' });
  });

  it('emite evento saved ao salvar com sucesso', async () => {
    const savedPromise = new Promise<void>((resolve) => {
      component.saved.subscribe(() => resolve());
    });

    component.form.patchValue({ title: 'Test Event', starts_at: '2024-01-01T10:00' });
    component.submit();

    await savedPromise;
  });
});
