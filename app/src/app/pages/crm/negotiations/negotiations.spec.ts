import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { Negotiations } from './negotiations';

describe('Negotiations', () => {
  let component: Negotiations;
  let fixture: ComponentFixture<Negotiations>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Negotiations],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Negotiations);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should default to kanban view mode', () => {
    expect(component.viewMode()).toBe('kanban');
  });

  it('should return status labels', () => {
    expect(component.getStatusLabel('open')).toBe('Aberta');
    expect(component.getStatusLabel('won')).toBe('Ganha');
    expect(component.getStatusLabel('lost')).toBe('Perdida');
  });

  it('should return step totals and drop ids', () => {
    const step: Parameters<Negotiations['getStepTotal']>[0] = {
      id: 12,
      name: 'Etapa',
      order: 1,
      is_active: true,
      negotiations: [
        {
          id: 1,
          title: 'Negócio',
          status: 'open',
          value: 1200,
          contact_id: 'contact-1',
        },
      ],
      funnel_id: 1,
    };

    expect(component.getStepTotal(step)).toBe(1200);
    expect(component.getDropListId(step)).toBe('step-12');
  });

  it('should format date fallback', () => {
    expect(component.formatDate('invalid-date')).toBe('-');
  });

  it('should clear status filter chip state', () => {
    component.negotiationStatusControl.setValue('won');

    component.removeActiveFilter('status');

    expect(component.negotiationStatusControl.value).toBe('all');
  });
});
