import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { type Contact } from 'src/app/core/models/contact.model';
import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { type Negotiation } from 'src/app/core/services/negotiation.service';
import { NegotiationTimelineComponent } from './negotiation-timeline.component';

describe('NegotiationTimelineComponent', () => {
  let component: NegotiationTimelineComponent;
  let fixture: ComponentFixture<NegotiationTimelineComponent>;

  const contact: Contact = {
    id: 'contact-1',
    name: 'Maria Souza',
    phone: '11999999999',
    crm_company_id: 'company-1',
    is_active: true,
    company_id: 'tenant-1',
    created_at: '2026-03-29T10:00:00Z',
    updated_at: '2026-03-29T10:00:00Z',
  };

  const negotiation = {
    id: 'neg-1',
    title: 'Renovacao anual',
    status: 'open',
    value: 1500,
    expected_close_date: '2026-04-10T12:00:00Z',
    step: { id: 'step-1', name: 'Diagnostico' },
  } as Negotiation;

  const funnels = [{ id: 'funnel-1', name: 'Vendas' }] as Funnel[];
  const steps = [{ id: 'step-1', name: 'Diagnostico' }] as FunnelStep[];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationTimelineComponent],
      providers: [provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationTimelineComponent);
    component = fixture.componentInstance;

    fixture.componentRef.setInput('currentContact', contact);
    fixture.componentRef.setInput('hasNegotiations', true);
    fixture.componentRef.setInput('negotiations', [negotiation]);
    fixture.componentRef.setInput('selectedNegotiationId', 'neg-1');
    fixture.componentRef.setInput('createForm', {
      title: '',
      funnelId: '',
      stepId: '',
      expectedClose: '',
      notes: '',
      companyId: '',
    });
    fixture.componentRef.setInput('canCreateNegotiation', true);
    fixture.componentRef.setInput('funnels', funnels);
    fixture.componentRef.setInput('createSteps', steps);
    fixture.detectChanges();
  });

  it('emits selectNegotiation when a negotiation card is clicked', () => {
    const selectNegotiationSpy = vi.fn<(value: string | number) => void>();
    component.selectNegotiation.subscribe(selectNegotiationSpy);

    const negotiationButton = fixture.nativeElement.querySelector(
      'button.w-full',
    ) as HTMLButtonElement | null;
    negotiationButton?.click();

    expect(selectNegotiationSpy).toHaveBeenCalledWith('neg-1');
  });

  it('emits createFieldChange for creation form inputs', () => {
    const createFieldChangeSpy = vi.fn<(value: { field: string; value: string }) => void>();
    component.createFieldChange.subscribe(createFieldChangeSpy);

    const titleInput = fixture.nativeElement.querySelector(
      '#create_title',
    ) as HTMLInputElement | null;
    const funnelSelect = fixture.nativeElement.querySelector(
      '#create_funnel',
    ) as HTMLSelectElement | null;

    if (!titleInput || !funnelSelect) {
      throw new Error('Expected creation form controls to exist.');
    }

    titleInput.value = 'Nova oportunidade';
    titleInput.dispatchEvent(new Event('input'));

    funnelSelect.value = 'funnel-1';
    funnelSelect.dispatchEvent(new Event('change'));

    expect(createFieldChangeSpy).toHaveBeenNthCalledWith(1, {
      field: 'title',
      value: 'Nova oportunidade',
    });
    expect(createFieldChangeSpy).toHaveBeenNthCalledWith(2, {
      field: 'funnelId',
      value: 'funnel-1',
    });
  });

  it('emits createNegotiation when the create button is clicked', () => {
    const createNegotiationSpy = vi.fn<() => void>();
    component.createNegotiation.subscribe(createNegotiationSpy);

    const actionButtons = Array.from(
      fixture.nativeElement.querySelectorAll('button[type="button"]'),
    ) as HTMLButtonElement[];
    const createButton = actionButtons[actionButtons.length - 1];
    createButton?.click();

    expect(createNegotiationSpy).toHaveBeenCalledTimes(1);
  });
});
