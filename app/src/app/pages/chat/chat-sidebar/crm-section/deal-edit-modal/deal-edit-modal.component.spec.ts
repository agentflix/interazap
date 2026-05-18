import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { DealEditModalComponent } from './deal-edit-modal.component';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import {
  type Negotiation,
  type NegotiationPayload,
  NegotiationService,
} from 'src/app/core/services/negotiation.service';
import {
  type Funnel,
  type FunnelStep,
  FunnelService,
} from 'src/app/core/services/funnel.service';
import { type PaginatedResponse as FunnelPaginatedResponse } from '@core/models/pagination.model';
import { UserService } from 'src/app/core/services/user.service';
import { type UserListResponse } from '@core/models/user.model';
import { of, throwError } from 'rxjs';
import { ReactiveFormsModule } from '@angular/forms';
import { provideIcons } from '@ng-icons/core';
import { lucideX } from '@ng-icons/lucide';

describe('DealEditModalComponent', () => {
  let component: DealEditModalComponent;
  let fixture: ComponentFixture<DealEditModalComponent>;
  let negotiationServiceSpy: {
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
  };
  let funnelServiceSpy: {
    list: ReturnType<typeof vi.fn>;
  };
  let userServiceSpy: {
    list: ReturnType<typeof vi.fn>;
  };

  const createFunnel = (overrides: Partial<Funnel> = {}): Funnel => ({
    id: '1',
    name: 'Funnel 1',
    is_active: true,
    ...overrides,
  });

  const createStep = (overrides: Partial<FunnelStep> = {}): FunnelStep => ({
    id: 's1',
    name: 'Step 1',
    order: 1,
    ...overrides,
  });

  const createNegotiation = (overrides: Partial<Negotiation> = {}): Negotiation => ({
    id: '1',
    title: 'Deal 1',
    status: 'open',
    ...overrides,
  });

  beforeEach(async () => {
    negotiationServiceSpy = {
      create: vi.fn(),
      update: vi.fn(),
    };
    funnelServiceSpy = {
      list: vi.fn(),
    };
    userServiceSpy = {
      list: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [DealEditModalComponent, ReactiveFormsModule],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideIcons({ lucideX }),
        { provide: NegotiationService, useValue: negotiationServiceSpy },
        { provide: FunnelService, useValue: funnelServiceSpy },
        { provide: UserService, useValue: userServiceSpy },
      ],
    }).compileComponents();

    const emptyFunnels: FunnelPaginatedResponse<Funnel> = {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
    };
    const emptyUsers: UserListResponse = {
      data: [],
      meta: { total: 0, per_page: 10, current_page: 1, last_page: 1 },
    };
    funnelServiceSpy.list.mockReturnValue(of(emptyFunnels));
    userServiceSpy.list.mockReturnValue(of(emptyUsers));

    fixture = TestBed.createComponent(DealEditModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('isOpen', true);
    fixture.componentRef.setInput('contactId', '1');
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  describe('initialization', () => {
    it('should load funnels and users on init', () => {
      expect(funnelServiceSpy.list).toHaveBeenCalled();
      expect(userServiceSpy.list).toHaveBeenCalled();
    });

    it('should patch form when deal input is provided', () => {
      const mockDeal: Negotiation = createNegotiation({
        id: '1',
        title: 'Deal 1',
        value: 100,
        funnel_id: '1',
        step_id: '1',
      });

      fixture.componentRef.setInput('deal', mockDeal);
      fixture.detectChanges();

      expect(component.form.get('title')).toBeTruthy();
    });
  });

  describe('funnel loading', () => {
    it('should set default funnel and step if creating new deal', () => {
      const mockFunnels: Funnel[] = [
        createFunnel({ steps: [createStep({ id: 's1', name: 'Step 1' })] }),
      ];
      const response: FunnelPaginatedResponse<Funnel> = {
        data: mockFunnels,
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
      };
      funnelServiceSpy.list.mockReturnValue(of(response));

      component.loadFunnels();

      expect(component.funnels().length).toBe(1);
      expect(component.form.get('funnel_id')?.value).toBe('1');
      expect(component.form.get('step_id')?.value).toBe('s1');
    });
  });

  describe('submission', () => {
    it('should create new deal', () => {
      const payload: Partial<NegotiationPayload> = {
        title: 'New Deal',
        value: 100,
        funnel_id: '1',
        step_id: 's1',
      };

      component.form.patchValue(payload);
      const created = createNegotiation({
        id: '1',
        title: payload.title ?? 'New Deal',
        value: payload.value,
        funnel_id: payload.funnel_id,
        step_id: payload.step_id,
      });
      negotiationServiceSpy.create.mockReturnValue(of({ data: { negotiation: created } }));

      let emittedDeal: Negotiation | null = null;
      component.dealSaved.subscribe((deal) => (emittedDeal = deal));

      component.onSubmit();

      expect(negotiationServiceSpy.create).toHaveBeenCalled();
      expect(emittedDeal).toBeTruthy();
    });

    it('should update existing deal', () => {
      const mockDeal: Negotiation = createNegotiation({ id: '1', title: 'Old Title' });
      fixture.componentRef.setInput('deal', mockDeal);

      const payload: Partial<NegotiationPayload> = {
        title: 'Updated Title',
        value: 200,
        funnel_id: '1',
        step_id: 's1',
      };
      component.form.patchValue(payload);

      const updated = createNegotiation({
        id: '1',
        title: payload.title ?? 'Updated Title',
        value: payload.value,
        funnel_id: payload.funnel_id,
        step_id: payload.step_id,
      });
      negotiationServiceSpy.update.mockReturnValue(of({ data: { negotiation: updated } }));

      let emittedDeal: Negotiation | null = null;
      component.dealSaved.subscribe((deal) => (emittedDeal = deal));

      component.onSubmit();

      expect(negotiationServiceSpy.update).toHaveBeenCalledWith('1', expect.anything());
      expect(emittedDeal).toBeTruthy();
    });

    it('should not submit invalid form', () => {
      component.form.get('title')?.setValue(''); // Required
      component.onSubmit();
      expect(negotiationServiceSpy.create).not.toHaveBeenCalled();
    });

    it('should handle submission error', () => {
      const payload = { title: 'New Deal', value: 100, funnel_id: '1', step_id: 's1' };
      component.form.patchValue(payload);
      negotiationServiceSpy.create.mockReturnValue(throwError(() => new Error('Error')));

      component.onSubmit();

      expect(component.isSaving()).toBe(false);
    });
  });

  describe('closing', () => {
    it('should reset form and emit closed event', () => {
      const titleControl = component.form.get('title');
      titleControl?.setValue('Dirty');

      let closedEmitted = false;
      component.closeModal.subscribe(() => (closedEmitted = true));

      component.onClose();

      expect(titleControl?.value).toBeNull(); // Reset
      expect(closedEmitted).toBe(true);
    });
  });
});
