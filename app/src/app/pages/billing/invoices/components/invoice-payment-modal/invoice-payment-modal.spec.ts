import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  type BillingInvoice,
  type BillingInvoicePaymentResponse,
  BillingInvoiceService,
} from '@core/services/billing-invoice.service';
import { ToastService } from '@core/services/toast.service';
import { InvoicePaymentModalComponent } from './invoice-payment-modal';

function buildInvoice(): BillingInvoice {
  return {
    id: 'inv-123',
    tenant_id: 'tenant-123',
    reference_month: '2026-03',
    amount: 19900,
    status: 'pending',
    due_date: '2026-03-20',
    created_at: '2026-03-01T00:00:00Z',
    updated_at: '2026-03-01T00:00:00Z',
  };
}

function buildPaymentResponse(invoice: BillingInvoice): BillingInvoicePaymentResponse {
  return {
    data: {
      method: 'CREDIT_CARD',
      invoice,
      status: 'paid',
    },
  };
}

describe('InvoicePaymentModalComponent', () => {
  let fixture: ComponentFixture<InvoicePaymentModalComponent>;
  let component: InvoicePaymentModalComponent;
  let billingServiceMock: { pay: ReturnType<typeof vi.fn> };

  const toastMock = {
    error: vi.fn(),
    success: vi.fn(),
  };

  beforeEach(async () => {
    const invoice = buildInvoice();

    billingServiceMock = {
      pay: vi.fn().mockReturnValue(of(buildPaymentResponse(invoice))),
    };

    await TestBed.configureTestingModule({
      imports: [InvoicePaymentModalComponent],
      providers: [
        provideRouter([]),
        { provide: BillingInvoiceService, useValue: billingServiceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(InvoicePaymentModalComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('invoice', invoice);
    fixture.componentRef.setInput('isOpen', true);
    fixture.detectChanges();
  });

  it('bloqueia submit e mostra mensagens de pattern para cartao invalido', () => {
    component.paymentMethodControl.setValue('CREDIT_CARD');
    component.cardForm.setValue({
      holder_name: 'Joao Silva',
      number: '1234',
      expiry_month: '13',
      expiry_year: '28',
      cvv: '12',
    });
    component.holderForm.setValue({
      name: 'Joao Silva',
      email: 'joao@empresa.com',
      cpf_cnpj: '12345678900',
      postal_code: '01310100',
      address_number: '123',
      phone: '11999999999',
    });

    component.submitPayment();
    fixture.detectChanges();

    expect(billingServiceMock.pay).not.toHaveBeenCalled();
    expect(component.cardForm.controls.number.touched).toBe(true);
    expect(component.cardForm.controls.number.hasError('pattern')).toBe(true);
    expect(component.cardForm.controls.expiry_month.hasError('pattern')).toBe(true);
    expect(component.cardForm.controls.expiry_year.hasError('pattern')).toBe(true);
    expect(component.cardForm.controls.cvv.hasError('pattern')).toBe(true);
    expect(fixture.nativeElement.textContent).toContain(
      'Informe um numero de cartao valido com 13 a 19 digitos.',
    );
    expect(fixture.nativeElement.textContent).toContain('Informe um mes valido entre 01 e 12.');
    expect(fixture.nativeElement.textContent).toContain('Informe um ano valido com 4 digitos.');
    expect(fixture.nativeElement.textContent).toContain(
      'Informe um CVV valido com 3 ou 4 digitos.',
    );
  });

  it('aceita cartao valido com espacos e envia payload normalizado', () => {
    component.paymentMethodControl.setValue('CREDIT_CARD');
    component.cardForm.setValue({
      holder_name: 'Joao Silva',
      number: '4111 1111 1111 1111',
      expiry_month: '12',
      expiry_year: '2028',
      cvv: '123',
    });
    component.holderForm.setValue({
      name: 'Joao Silva',
      email: 'joao@empresa.com',
      cpf_cnpj: '12345678900',
      postal_code: '01310100',
      address_number: '123',
      phone: '11999999999',
    });

    component.submitPayment();

    expect(billingServiceMock.pay).toHaveBeenCalledWith(
      'inv-123',
      expect.objectContaining({
        method: 'CREDIT_CARD',
        card: {
          holder_name: 'Joao Silva',
          number: '4111111111111111',
          expiry_month: '12',
          expiry_year: '2028',
          cvv: '123',
        },
      }),
    );
  });
});
