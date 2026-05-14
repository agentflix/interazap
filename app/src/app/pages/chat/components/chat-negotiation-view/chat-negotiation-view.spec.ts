import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { ChatNegotiationView } from './chat-negotiation-view';
import { NegotiationService, type Negotiation } from 'src/app/core/services/negotiation.service';
import { NegotiationTaskService } from 'src/app/core/services/negotiation-task.service';
import {
  NegotiationProductService,
  type NegotiationProductItem,
} from 'src/app/core/services/negotiation-product.service';
import { FunnelService } from 'src/app/core/services/funnel.service';
import { ProductServiceService } from 'src/app/core/services/product-service.service';
import { ChatRealtimeService } from 'src/app/core/services/chat-realtime.service';
import { Subject, of, throwError } from 'rxjs';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideZonelessChangeDetection, signal } from '@angular/core';
import { type Contact } from 'src/app/core/models/contact.model';

describe('ChatNegotiationView', () => {
  let component: ChatNegotiationView;
  let fixture: ComponentFixture<ChatNegotiationView>;

  const negotiationDetail = {
    id: 'neg-1',
    title: 'Negociação ativa',
    status: 'open',
    funnel_id: 'funnel-1',
    step_id: 'step-1',
  } as Negotiation;

  const negotiationProduct = {
    id: 'item-1',
    negotiation_id: 'neg-1',
    product_id: 'product-1',
    quantity: 2,
    price: 150,
    total: 300,
    product: {
      id: 'product-1',
      name: 'Plano Plus',
      price: 150,
    },
  } satisfies NegotiationProductItem;

  const negotiationServiceMock = {
    list: vi.fn(),
    create: vi.fn(),
    get: vi.fn(),
    update: vi.fn(),
  };

  const negotiationTaskServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: { tasks: [] } })),
    create: vi.fn(),
    toggle: vi.fn(),
  };

  const negotiationProductServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: { products: [], total: 0 } })),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
  };

  const funnelServiceMock = {
    all: vi.fn().mockReturnValue(of({ data: { funnels: [] } })),
    listSteps: vi.fn().mockReturnValue(of({ data: { steps: [] } })),
  };

  const productServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: [] })),
  };

  const chatRealtimeServiceMock = {
    connected: signal(false),
    dealUpdated: signal({ event: null, version: 0 }),
    contactUpdated: signal({ event: null, version: 0 }),
    newMessage: signal({ event: null, version: 0 }),
    messageStatus: signal({ event: null, version: 0 }),
    typing: signal({ event: null, version: 0 }),
    delete: signal({ event: null, version: 0 }),
    newTicket: signal({ event: null, version: 0 }),
    ticketUpdated: signal({ event: null, version: 0 }),
    reaction: signal({ event: null, version: 0 }),
    edit: signal({ event: null, version: 0 }),
    activity: signal({ event: null, version: 0 }),
    connect: vi.fn(),
    disconnect: vi.fn(),
    joinTicket: vi.fn(),
    leaveTicket: vi.fn(),
  };

  beforeEach(async () => {
    negotiationServiceMock.list.mockReset();
    negotiationServiceMock.create.mockReset();
    negotiationServiceMock.get.mockReset();
    negotiationServiceMock.update.mockReset();

    negotiationTaskServiceMock.list.mockReset();
    negotiationTaskServiceMock.create.mockReset();
    negotiationTaskServiceMock.toggle.mockReset();

    negotiationProductServiceMock.list.mockReset();
    negotiationProductServiceMock.create.mockReset();
    negotiationProductServiceMock.update.mockReset();
    negotiationProductServiceMock.delete.mockReset();

    funnelServiceMock.all.mockReset();
    funnelServiceMock.listSteps.mockReset();
    productServiceMock.list.mockReset();

    negotiationServiceMock.list.mockReturnValue(of({ data: [] }));
    negotiationTaskServiceMock.list.mockReturnValue(of({ data: { tasks: [] } }));
    negotiationProductServiceMock.list.mockReturnValue(of({ data: { products: [], total: 0 } }));
    funnelServiceMock.all.mockReturnValue(of({ data: { funnels: [] } }));
    funnelServiceMock.listSteps.mockReturnValue(of({ data: { steps: [] } }));
    productServiceMock.list.mockReturnValue(of({ data: [] }));

    await TestBed.configureTestingModule({
      imports: [ChatNegotiationView],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideZonelessChangeDetection(),
        { provide: NegotiationService, useValue: negotiationServiceMock },
        { provide: NegotiationTaskService, useValue: negotiationTaskServiceMock },
        { provide: NegotiationProductService, useValue: negotiationProductServiceMock },
        { provide: FunnelService, useValue: funnelServiceMock },
        { provide: ProductServiceService, useValue: productServiceMock },
        { provide: ChatRealtimeService, useValue: chatRealtimeServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatNegotiationView);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
    expect(funnelServiceMock.all).toHaveBeenCalled();
  });

  it('should format date correctly', () => {
    // Append T12:00:00 to avoid timezone shift to previous day
    expect(component.formatDate('2023-10-01T12:00:00')).toEqual('01/10/2023');
    expect(component.formatDate(null)).toEqual('-');
    expect(component.formatDate('invalid-date')).toEqual('-');
  });

  it('should return correct status tone', () => {
    expect(component.statusTone('won')).toContain('bg-success');
    expect(component.statusTone('lost')).toContain('bg-danger');
    expect(component.statusTone('open')).toContain('bg-primary');
    expect(component.statusTone(null)).toContain('bg-primary');
  });

  it('should handle input contact change', () => {
    const contact: Contact = {
      id: 1,
      name: 'Test',
      crm_company_id: '10',
      is_active: true,
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };
    negotiationServiceMock.list.mockReturnValue(of({ data: { negotiations: [] } }));

    component.contact = contact;

    expect(component.currentContact()).toEqual(contact);
    expect(negotiationServiceMock.list).toHaveBeenCalledWith({ contact_id: 1, per_page: 50 });
  });

  it('should allow creation only when form is valid', () => {
    component.contact = {
      id: 1,
      name: 'Test',
      is_active: true,
      crm_company_id: 'company-1',
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    expect(component.canCreateNegotiation()).toBe(false);

    component.onCreateFieldChange('title', 'Deal 1');
    component.onCreateFieldChange('funnelId', '1');
    component.onCreateFieldChange('stepId', '2');
    component.onCreateFieldChange('companyId', '10');

    expect(component.canCreateNegotiation()).toBe(true);
  });

  it('clears stale detail state and exposes a persistent detail error when selection load fails', () => {
    negotiationServiceMock.list.mockReturnValue(
      of({
        data: [
          negotiationDetail,
          { ...negotiationDetail, id: 'neg-2', title: 'Negociação com erro' },
        ],
      }),
    );
    negotiationServiceMock.get
      .mockReturnValueOnce(of({ data: { negotiation: negotiationDetail } }))
      .mockReturnValueOnce(throwError(() => new Error('detail failed')));
    negotiationProductServiceMock.list.mockReturnValue(
      of({ data: { products: [negotiationProduct], total: 300 } }),
    );
    negotiationTaskServiceMock.list.mockReturnValue(
      of({
        data: {
          tasks: [
            {
              id: 'task-1',
              negotiation_id: 'neg-1',
              title: 'Ligar para cliente',
              is_completed: false,
            },
          ],
        },
      }),
    );

    component.contact = {
      id: 1,
      name: 'Contato',
      crm_company_id: '10',
      is_active: true,
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    expect(component.selectedNegotiation()?.id).toBe('neg-1');
    expect(component.products()).toEqual([negotiationProduct]);
    expect(component.tasks()).toHaveLength(1);

    component.selectNegotiation('neg-2');

    expect(component.selectedNegotiationId()).toBe('neg-2');
    expect(component.selectedNegotiation()).toBeNull();
    expect(component.products()).toEqual([]);
    expect(component.tasks()).toEqual([]);
    expect(component.detailError()).toBe(
      'Não foi possível abrir os detalhes da negociação selecionada.',
    );
  });

  it('keeps detail loading bound to the latest request during rapid negotiation switches', () => {
    const firstDetail$ = new Subject<{ data: { negotiation: Negotiation } }>();
    const secondDetail$ = new Subject<{ data: { negotiation: Negotiation } }>();

    negotiationServiceMock.list.mockReturnValue(
      of({
        data: [
          negotiationDetail,
          { ...negotiationDetail, id: 'neg-2', title: 'Segunda negociação' },
        ],
      }),
    );
    negotiationServiceMock.get
      .mockReturnValueOnce(firstDetail$.asObservable())
      .mockReturnValueOnce(secondDetail$.asObservable());

    component.contact = {
      id: 1,
      name: 'Contato',
      crm_company_id: '10',
      is_active: true,
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    expect(component.isLoadingDetail()).toBe(true);

    component.selectNegotiation('neg-2');

    firstDetail$.next({ data: { negotiation: negotiationDetail } });
    firstDetail$.complete();

    expect(component.selectedNegotiation()).toBeNull();
    expect(component.isLoadingDetail()).toBe(true);

    secondDetail$.error(new Error('detail failed'));

    expect(component.isLoadingDetail()).toBe(false);
    expect(component.detailError()).toBe(
      'Não foi possível abrir os detalhes da negociação selecionada.',
    );
  });

  it('resets loading flags when contact context is cleared while detail is in flight', () => {
    const pendingDetail$ = new Subject<{ data: { negotiation: Negotiation } }>();

    negotiationServiceMock.list.mockReturnValue(of({ data: [negotiationDetail] }));
    negotiationServiceMock.get.mockReturnValue(pendingDetail$.asObservable());

    component.contact = {
      id: 1,
      name: 'Contato',
      crm_company_id: '10',
      is_active: true,
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    expect(component.isLoadingDetail()).toBe(true);

    component.contact = null;

    expect(component.isLoadingList()).toBe(false);
    expect(component.isLoadingDetail()).toBe(false);
    expect(component.isProductsLoading()).toBe(false);
    expect(component.isTasksLoading()).toBe(false);
    expect(component.selectedNegotiation()).toBeNull();

    pendingDetail$.next({ data: { negotiation: negotiationDetail } });
    pendingDetail$.complete();

    expect(component.selectedNegotiation()).toBeNull();
  });

  it('exposes persistent product and task errors when child collections fail to load', () => {
    negotiationServiceMock.list.mockReturnValue(of({ data: [negotiationDetail] }));
    negotiationServiceMock.get.mockReturnValue(of({ data: { negotiation: negotiationDetail } }));
    negotiationProductServiceMock.list.mockReturnValue(
      throwError(() => new Error('products failed')),
    );
    negotiationTaskServiceMock.list.mockReturnValue(throwError(() => new Error('tasks failed')));

    component.contact = {
      id: 1,
      name: 'Contato',
      crm_company_id: '10',
      is_active: true,
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    expect(component.products()).toEqual([]);
    expect(component.tasks()).toEqual([]);
    expect(component.productsError()).toBe('Não foi possível carregar os produtos da negociação.');
    expect(component.tasksError()).toBe('Não foi possível carregar as tarefas da negociação.');
  });

  it('bridges the existing item price FormControl to updateProduct through getItemPriceControlFn', () => {
    const selectedNegotiation = {
      ...negotiationDetail,
      id: 'neg-bridge',
    } as Negotiation;
    const item = {
      ...negotiationProduct,
      id: 'item-bridge',
      negotiation_id: 'neg-bridge',
      price: 180,
    } satisfies NegotiationProductItem;

    component.selectedNegotiation.set(selectedNegotiation);
    negotiationProductServiceMock.update.mockReturnValue(of({ data: item }));
    negotiationProductServiceMock.list.mockReturnValue(
      of({ data: { products: [item], total: 180 } }),
    );

    const control = component.getItemPriceControlFn(item);

    expect(control).toBe(component.getItemPriceControl(item));

    control.setValue(275);

    expect(negotiationProductServiceMock.update).toHaveBeenCalledWith('neg-bridge', 'item-bridge', {
      price: 275,
    });
  });

  it('accepts only valid notifyChannel values in task field updates', () => {
    component.onTaskFieldChange({
      field: 'notifyChannel',
      value: 'email',
    });

    expect(component.taskForm().notifyChannel).toBe('email');

    component.onTaskFieldChange({
      field: 'notifyChannel',
      value: 'sms' as never,
    });

    expect(component.taskForm().notifyChannel).toBe('none');
  });
});
