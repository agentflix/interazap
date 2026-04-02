import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { importProvidersFrom } from '@angular/core';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of, throwError } from 'rxjs';
import { PlatformInvoices } from './platform-invoices';
import {
  type PlatformBillingInvoice,
  PlatformBillingInvoiceService,
} from '@core/services/platform-billing-invoice.service';
import { CompanyService } from '@core/services/company.service';
import { PlatformPlanService } from '@platform/services/platform-plan.service';

describe('PlatformInvoices', () => {
  let component: PlatformInvoices;
  let fixture: ComponentFixture<PlatformInvoices>;

  let invoiceServiceMock: {
    list: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
    cancel: ReturnType<typeof vi.fn>;
  };

  let companyServiceMock: {
    list: ReturnType<typeof vi.fn>;
  };

  let planServiceMock: {
    list: ReturnType<typeof vi.fn>;
  };

  const mockInvoices: PlatformBillingInvoice[] = [
    {
      id: 'inv-1',
      tenant_id: 'tenant-1',
      reference_month: '2026/01',
      amount: 19900,
      due_date: '2026-01-15',
      created_at: '2026-01-01T00:00:00+00:00',
      updated_at: '2026-01-01T00:00:00+00:00',
      paid_at: null,
      status: 'pending',
      status_label: 'Pendente',
      status_color: 'warning',
      payment_method: null,
      payment_url: 'https://pay.example.com/inv-1',
      plan: { id: 'plan-1', name: 'Pro' },
      tenant: { id: 'tenant-1', name: 'Acme Corp', tenant_code: 'ACME01' },
    },
    {
      id: 'inv-2',
      tenant_id: 'tenant-2',
      reference_month: '2026/02',
      amount: 29900,
      due_date: '2026-02-15',
      created_at: '2026-02-01T00:00:00+00:00',
      updated_at: '2026-02-01T00:00:00+00:00',
      paid_at: '2026-02-10T10:00:00+00:00',
      status: 'paid',
      status_label: 'Pago',
      status_color: 'success',
      payment_method: 'pix',
      payment_url: null,
      plan: { id: 'plan-2', name: 'Enterprise' },
      tenant: { id: 'tenant-2', name: 'Beta Inc', tenant_code: 'BETA02' },
    },
    {
      id: 'inv-3',
      tenant_id: 'tenant-1',
      reference_month: '2026/03',
      amount: 9900,
      due_date: '2026-03-15',
      created_at: '2026-03-01T00:00:00+00:00',
      updated_at: '2026-03-01T00:00:00+00:00',
      paid_at: null,
      status: 'cancelled',
      status_label: 'Cancelado',
      status_color: 'default',
      payment_method: null,
      payment_url: null,
      plan: undefined,
      tenant: { id: 'tenant-1', name: 'Acme Corp', tenant_code: 'ACME01' },
    },
  ];

  const mockCompanies = [
    { id: 'tenant-1', name: 'Acme Corp' },
    { id: 'tenant-2', name: 'Beta Inc' },
  ];

  const mockPlans = [
    { id: 'plan-1', name: 'Pro', slug: 'pro', price_monthly: '199.00', is_active: true },
    {
      id: 'plan-2',
      name: 'Enterprise',
      slug: 'enterprise',
      price_monthly: '299.00',
      is_active: true,
    },
  ];

  const mockPaginatedResponse = {
    data: mockInvoices,
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 3 },
  };

  beforeEach(async () => {
    invoiceServiceMock = {
      list: vi.fn().mockReturnValue(of(mockPaginatedResponse)),
      create: vi.fn().mockReturnValue(of({ data: mockInvoices[0] })),
      cancel: vi.fn().mockReturnValue(of(void 0)),
    };

    companyServiceMock = {
      list: vi.fn().mockReturnValue(
        of({
          data: mockCompanies,
          meta: { current_page: 1, last_page: 1, per_page: 200, total: 2 },
        }),
      ),
    };

    planServiceMock = {
      list: vi
        .fn()
        .mockReturnValue(
          of({ data: mockPlans, meta: { current_page: 1, last_page: 1, per_page: 200, total: 2 } }),
        ),
    };

    await TestBed.configureTestingModule({
      imports: [PlatformInvoices],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: PlatformBillingInvoiceService, useValue: invoiceServiceMock },
        { provide: CompanyService, useValue: companyServiceMock },
        { provide: PlatformPlanService, useValue: planServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PlatformInvoices);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  // ── Basic rendering ──

  it('renders invoices list on load', () => {
    expect(component).toBeTruthy();
    expect(invoiceServiceMock.list).toHaveBeenCalled();
    expect(component.invoices().length).toBe(3);
    expect(component.isLoading()).toBe(false);
    expect(component.hasError()).toBe(false);
  });

  it('renders tenant column for each invoice', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Acme Corp');
    expect(compiled.textContent).toContain('Beta Inc');
  });

  // ── Filters ──

  it('filters by tenant when company select changes', () => {
    invoiceServiceMock.list.mockClear();

    component.tenantFilterControl.setValue('tenant-1');
    fixture.detectChanges();

    expect(invoiceServiceMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ tenant_id: 'tenant-1' }),
    );
  });

  // ── Create modal ──

  it('opens create form and submits new invoice', () => {
    component.openCreateModal();
    expect(component.isCreateModalOpen()).toBe(true);

    component.createTenantControl.setValue('tenant-1');
    component.createPlanControl.setValue('plan-1');
    component.createReferenceMonthControl.setValue('2026-04');
    component.createAmountControl.setValue(15000);
    component.createDueDateControl.setValue('2026-04-15');
    component.createStatusControl.setValue('draft');

    fixture.detectChanges();

    component.submitCreate();
    fixture.detectChanges();

    expect(invoiceServiceMock.create).toHaveBeenCalledWith({
      tenant_id: 'tenant-1',
      plan_id: 'plan-1',
      reference_month: '2026-04',
      amount: 15000,
      due_date: '2026-04-15',
      status: 'draft',
    });
    expect(component.isCreateModalOpen()).toBe(false);
  });

  // ── Cancel logic ──

  it('disables cancel button for paid invoices', () => {
    const paidInvoice = mockInvoices[1];
    expect(paidInvoice.status).toBe('paid');
    expect(component.canCancel(paidInvoice)).toBe(false);

    const cancelledInvoice = mockInvoices[2];
    expect(cancelledInvoice.status).toBe('cancelled');
    expect(component.canCancel(cancelledInvoice)).toBe(false);

    const pendingInvoice = mockInvoices[0];
    expect(pendingInvoice.status).toBe('pending');
    expect(component.canCancel(pendingInvoice)).toBe(true);
  });

  it('opens confirm modal before cancelling invoice', () => {
    const invoice = mockInvoices[0];
    component.openCancelConfirm(invoice);

    expect(component.isCancelModalOpen()).toBe(true);
    expect(component.invoiceToCancel()).toBe(invoice);
    expect(component.cancelMessage()).toContain('Acme Corp');
    expect(component.cancelMessage()).toContain('2026/01');
  });

  it('cancels invoice and refreshes list on confirm', () => {
    const invoice = mockInvoices[0];
    component.openCancelConfirm(invoice);
    invoiceServiceMock.list.mockClear();

    component.confirmCancel();
    fixture.detectChanges();

    expect(invoiceServiceMock.cancel).toHaveBeenCalledWith('inv-1');
    expect(component.isCancelModalOpen()).toBe(false);
    expect(component.invoiceToCancel()).toBeNull();
    expect(invoiceServiceMock.list).toHaveBeenCalled();
  });

  // ── Empty state ──

  it('shows empty state when list is empty', () => {
    invoiceServiceMock.list.mockReturnValue(
      of({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }),
    );

    component.refresh();
    fixture.detectChanges();

    expect(component.invoices().length).toBe(0);
    expect(component.isEmpty()).toBe(true);
  });

  // ── Error state ──

  it('shows error state and allows retry on fetch failure', () => {
    invoiceServiceMock.list.mockReturnValue(throwError(() => new Error('Network error')));

    component.retry();
    fixture.detectChanges();

    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Erro ao carregar faturas');

    invoiceServiceMock.list.mockReturnValue(of(mockPaginatedResponse));
    component.retry();
    fixture.detectChanges();

    expect(component.hasError()).toBe(false);
    expect(component.invoices().length).toBe(3);
  });
});
