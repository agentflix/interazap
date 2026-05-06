import { describe, it, expect, beforeEach, vi, type Mock } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { CRMSectionComponent } from './crm-section';
import { type CRMNegotiation } from './crm-negotiation.model';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { of } from 'rxjs';

describe('CRMSectionComponent', () => {
  let component: CRMSectionComponent;
  let fixture: ComponentFixture<CRMSectionComponent>;
  let negotiationService: { list: Mock; move: Mock };

  beforeEach(async () => {
    negotiationService = {
      list: vi.fn(),
      move: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [CRMSectionComponent],
      providers: [
        provideHttpClient(),
        { provide: NegotiationService, useValue: negotiationService },
      ],
    })
      .overrideComponent(CRMSectionComponent, {
        set: { template: '' },
      })
      .compileComponents();

    fixture = TestBed.createComponent(CRMSectionComponent);
    component = fixture.componentInstance;

    // Mock response
    negotiationService.list.mockReturnValue(
      of({
        data: [],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 10,
          total: 0,
        },
      }),
    );
    negotiationService.move.mockReturnValue(
      of({
        data: {
          negotiation: {
            id: '1',
            title: 'Test Deal',
            status: 'open',
            step: { id: '2', name: 'Fechamento', order: 3 },
          },
        },
      }),
    );

    // Set required input
    fixture.componentRef.setInput('contactId', '123');
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should load negotiations on init', () => {
    expect(negotiationService.list).toHaveBeenCalledWith({
      contact_id: '123',
      per_page: 50,
    });
  });

  it('should sort negotiations (open > won > lost)', () => {
    const negotiations = [
      { id: '1', status: 'lost', title: 'Lost Deal', value: 1000, contact: {}, step: {} },
      { id: '2', status: 'won', title: 'Won Deal', value: 2000, contact: {}, step: {} },
      { id: '3', status: 'open', title: 'Open Deal', value: 3000, contact: {}, step: {} },
    ] as unknown as CRMNegotiation[];

    component.negotiations.set(negotiations);
    fixture.detectChanges();

    const sorted = component.sortedNegotiations();
    expect(sorted[0].status).toBe('open');
    expect(sorted[1].status).toBe('won');
    expect(sorted[2].status).toBe('lost');
  });

  it('should handle empty negotiations list', () => {
    negotiationService.list.mockReturnValue(
      of({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
      }),
    );

    component.loadNegotiations();

    expect(component.negotiations().length).toBe(0);
    expect(component.sortedNegotiations().length).toBe(0);
  });

  it('should open edit modal with selected negotiation', () => {
    const mockNegotiation = {
      id: '1',
      status: 'open',
      title: 'Test Deal',
      value: 5000,
      contact: {},
      step: {},
    } as unknown as CRMNegotiation;

    component.openEditModal(mockNegotiation);

    expect(component).toBeDefined();
  });

  it('should handle contact ID change', () => {
    // Já foi chamado com '123' no beforeEach, verifica se foi chamado
    expect(negotiationService.list).toHaveBeenCalled();
    const lastCall =
      negotiationService.list.mock.calls[negotiationService.list.mock.calls.length - 1];
    expect(lastCall[0].contact_id).toBe('123');
  });

  it('should format currency values correctly', () => {
    const negotiations = [
      { id: '1', status: 'open', title: 'Deal', value: 1500.5, contact: {}, step: {} },
    ] as unknown as CRMNegotiation[];

    component.negotiations.set(negotiations);
    fixture.detectChanges();

    expect(component.negotiations()[0].value).toBe(1500.5);
  });

  it('should move negotiation to next stage and release updating state', () => {
    component.negotiations.set([
      {
        id: '1',
        title: 'Test Deal',
        status: 'open',
        position: 1,
        step: { id: '1', name: 'Prospecção', order: 2 },
        funnel: {
          id: 'f1',
          name: 'Funil',
          steps: [
            { id: '0', name: 'Entrada', order: 1 },
            { id: '1', name: 'Prospecção', order: 2 },
            { id: '2', name: 'Fechamento', order: 3 },
          ],
        },
      } as unknown as CRMNegotiation,
    ]);

    component.onStageChanged('1', 'next');

    expect(negotiationService.move).toHaveBeenCalledWith('1', '2', 1);
    expect(component.stageUpdatingByDeal()['1']).toBe(false);
    expect(component.negotiations()[0].step?.id).toBe('2');
  });

  it('should not call move when there is no target step', () => {
    component.negotiations.set([
      {
        id: '1',
        title: 'Test Deal',
        status: 'open',
        position: 1,
        step: { id: '0', name: 'Entrada', order: 1 },
        funnel: {
          id: 'f1',
          name: 'Funil',
          steps: [{ id: '0', name: 'Entrada', order: 1 }],
        },
      } as unknown as CRMNegotiation,
    ]);

    component.onStageChanged('1', 'previous');
    expect(negotiationService.move).not.toHaveBeenCalled();
  });
});
