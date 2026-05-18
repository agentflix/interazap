import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  type BillingInvoice,
  BillingInvoiceService,
} from '@core/services/billing-invoice.service';
import { ToastService } from '@core/services/toast.service';
import { BillingInvoices } from './invoices';

function buildInvoice(overrides: Partial<BillingInvoice> = {}): BillingInvoice {
  return {
    id: 'inv-123',
    tenant_id: 'tenant-123',
    reference_month: '2026-03',
    amount: 19900,
    status: 'pending',
    status_color: 'warning',
    status_label: 'Pendente',
    due_date: '2026-03-20',
    created_at: '2026-03-01T00:00:00Z',
    updated_at: '2026-03-01T00:00:00Z',
    ...overrides,
  };
}

function buildServiceResponse(invoices: BillingInvoice[] = []) {
  return {
    data: invoices,
    meta: { current_page: 1, last_page: 1, per_page: 15, total: invoices.length },
  };
}

function buildServiceMock() {
  return {
    list: vi.fn().mockReturnValue(of(buildServiceResponse([buildInvoice()]))),
    pay: vi.fn().mockReturnValue(of({ data: { method: 'PIX', status: 'paid' } })),
  };
}

describe('BillingInvoices', () => {
  let fixture: ComponentFixture<BillingInvoices>;
  let component: BillingInvoices;
  let serviceMock: ReturnType<typeof buildServiceMock>;
  const toastMock = { success: vi.fn(), error: vi.fn() };

  beforeEach(async () => {
    serviceMock = buildServiceMock();

    await TestBed.configureTestingModule({
      imports: [BillingInvoices],
      providers: [
        provideRouter([]),
        { provide: BillingInvoiceService, useValue: serviceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(BillingInvoices);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('deve carregar faturas no init', () => {
    expect(serviceMock.list).toHaveBeenCalled();
    expect(component.isLoading()).toBe(false);
    expect(component.invoices().length).toBe(1);
  });

  it('deve entrar em estado de erro quando carregamento falha', () => {
    serviceMock.list.mockReturnValueOnce(throwError(() => new Error('fail')));

    component.retry();
    fixture.detectChanges();

    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);
  });

  it('deve retornar isEmpty quando nao ha faturas', () => {
    serviceMock.list.mockReturnValueOnce(of(buildServiceResponse([])));

    component.refresh();
    fixture.detectChanges();

    expect(component.isEmpty()).toBe(true);
  });

  it('deve identificar faturas pagaveis', () => {
    const draftInvoice = buildInvoice({ status: 'draft' });
    const pendingInvoice = buildInvoice({ status: 'pending' });
    const overdueInvoice = buildInvoice({ status: 'overdue' });
    const paidInvoice = buildInvoice({ status: 'paid' });

    expect(component.canPay(draftInvoice)).toBe(true);
    expect(component.canPay(pendingInvoice)).toBe(true);
    expect(component.canPay(overdueInvoice)).toBe(true);
    expect(component.canPay(paidInvoice)).toBe(false);
  });

  it('deve abrir modal de pagamento ao clicar em pagar', () => {
    const invoice = buildInvoice();

    component.openPay(invoice);

    expect(component.selectedInvoice()).toBe(invoice);
    expect(component.isPayModalOpen()).toBe(true);
  });

  it('deve fechar modal de pagamento', () => {
    component.openPay(buildInvoice());
    component.closePay();

    expect(component.isPayModalOpen()).toBe(false);
    expect(component.selectedInvoice()).toBeNull();
  });

  it('deve limpar filtros', () => {
    component.dueDateFromControl.setValue('2026-01-01');
    component.dueDateToControl.setValue('2026-12-31');
    component.invoiceStatusControl.setValue('paid');
    component.paymentMethodFilterControl.setValue('pix');
    component.searchControl.setValue('teste');

    component.clearFilters();

    expect(component.dueDateFromControl.value).toBe('');
    expect(component.dueDateToControl.value).toBe('');
    expect(component.invoiceStatusControl.value).toBe('');
    expect(component.paymentMethodFilterControl.value).toBe('');
    expect(component.searchControl.value).toBe('');
  });

  it('deve mapear cores de status para variantes do badge', () => {
    expect(component.statusVariant('success')).toBe('success');
    expect(component.statusVariant('primary')).toBe('info');
    expect(component.statusVariant('danger')).toBe('danger');
    expect(component.statusVariant('warning')).toBe('warning');
    expect(component.statusVariant('default')).toBe('default');
    expect(component.statusVariant(undefined)).toBe('default');
  });

  it('deve formatar moeda corretamente', () => {
    expect(component.formatCurrency(19900)).toContain('19.900');
  });

  it('deve emitir pagamento com sucesso e recarregar', () => {
    component.openPay(buildInvoice());
    component.onPaymentSuccess();

    expect(toastMock.success).toHaveBeenCalledWith('Cobrança gerada com sucesso!');
    expect(component.isPayModalOpen()).toBe(false);
    expect(serviceMock.list).toHaveBeenCalled();
  });
});
