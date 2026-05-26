import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  FormControl,
  NonNullableFormBuilder,
  ReactiveFormsModule,
  type AbstractControl,
  type ValidationErrors,
  type ValidatorFn,
  Validators,
} from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfRadioInputComponent,
  AfTextInputComponent,
  type AfRadioOption,
} from '@shared/components';
import {
  type BillingInvoice,
  type BillingInvoicePaymentMethod,
  BillingInvoiceService,
} from '@core/services/billing-invoice.service';
import { Router } from '@angular/router';
import { ToastService } from '@core/services/toast.service';

const CARD_NUMBER_PATTERN = /^\d{13,19}$/;
const EXPIRY_MONTH_PATTERN = /^(0[1-9]|1[0-2])$/;
const EXPIRY_YEAR_PATTERN = /^\d{4}$/;
const CVV_PATTERN = /^\d{3,4}$/;

type CardFieldName = 'number' | 'expiry_month' | 'expiry_year' | 'cvv';

const CARD_FIELD_MESSAGES: Record<CardFieldName, { required: string; pattern: string }> = {
  number: {
    required: 'Informe o numero do cartao.',
    pattern: 'Informe um numero de cartao valido com 13 a 19 digitos.',
  },
  expiry_month: {
    required: 'Informe o mes de expiracao.',
    pattern: 'Informe um mes valido entre 01 e 12.',
  },
  expiry_year: {
    required: 'Informe o ano de expiracao.',
    pattern: 'Informe um ano valido com 4 digitos.',
  },
  cvv: {
    required: 'Informe o CVV.',
    pattern: 'Informe um CVV valido com 3 ou 4 digitos.',
  },
};

function trimValue(value: string | null | undefined): string {
  return value?.trim() ?? '';
}

function normalizeCardNumber(value: string | null | undefined): string {
  return trimValue(value).replace(/[\s-]+/g, '');
}

function trimmedPatternValidator(pattern: RegExp): ValidatorFn {
  return (control: AbstractControl<string | null>): ValidationErrors | null => {
    const normalizedValue = trimValue(control.value);
    if (normalizedValue.length === 0) return null;

    return pattern.test(normalizedValue)
      ? null
      : { pattern: { requiredPattern: pattern.source, actualValue: control.value } };
  };
}

function cardNumberPatternValidator(): ValidatorFn {
  return (control: AbstractControl<string | null>): ValidationErrors | null => {
    const normalizedValue = normalizeCardNumber(control.value);
    if (normalizedValue.length === 0) return null;

    return CARD_NUMBER_PATTERN.test(normalizedValue)
      ? null
      : { pattern: { requiredPattern: CARD_NUMBER_PATTERN.source, actualValue: control.value } };
  };
}

/**
 * Modal de pagamento de fatura que suporta PIX e cartão de crédito.
 * O PAN do cartão nunca trafega para o backend — apenas o token emitido pelo gateway.
 */
@Component({
  selector: 'app-invoice-payment-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfModalComponent,
    AfRadioInputComponent,
    AfTextInputComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './invoice-payment-modal.html',
})
export class InvoicePaymentModalComponent {
  private readonly billingService = inject(BillingInvoiceService);
  private readonly toast = inject(ToastService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly invoice = input<BillingInvoice | null>(null);
  readonly isOpen = input(false);
  readonly closed = output<void>();
  readonly paymentSuccess = output<void>();

  readonly paymentMethodControl = new FormControl<string>('PIX', { nonNullable: true });
  readonly paymentMethod = signal<BillingInvoicePaymentMethod>('PIX');
  readonly paymentMethodOptions: AfRadioOption[] = [
    { value: 'PIX', label: 'PIX' },
    { value: 'CREDIT_CARD', label: 'Cartão de crédito' },
  ];

  readonly paymentStage = signal<'select' | 'pix'>('select');
  readonly isPaying = signal(false);
  readonly pixPayload = signal<string | null>(null);
  readonly pixQrCode = signal<string | null>(null);
  readonly pixExpiresAt = signal<string | null>(null);

  readonly cardForm = this.fb.group({
    holder_name: this.fb.control('', Validators.required),
    number: this.fb.control('', [Validators.required, cardNumberPatternValidator()]),
    expiry_month: this.fb.control('', [
      Validators.required,
      trimmedPatternValidator(EXPIRY_MONTH_PATTERN),
    ]),
    expiry_year: this.fb.control('', [
      Validators.required,
      trimmedPatternValidator(EXPIRY_YEAR_PATTERN),
    ]),
    cvv: this.fb.control('', [Validators.required, trimmedPatternValidator(CVV_PATTERN)]),
  });

  readonly holderForm = this.fb.group({
    name: this.fb.control('', Validators.required),
    email: this.fb.control('', [Validators.required, Validators.email]),
    cpf_cnpj: this.fb.control('', Validators.required),
    postal_code: this.fb.control('', Validators.required),
    address_number: this.fb.control('', Validators.required),
    phone: this.fb.control('', Validators.required),
  });

  readonly pixQrCodeDataUrl = computed(() => {
    const qr = this.pixQrCode();
    return qr ? `data:image/png;base64,${qr}` : null;
  });

  constructor() {
    this.paymentMethodControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.paymentMethod.set(value as BillingInvoicePaymentMethod));
  }

  /** Processa o pagamento via PIX (gera QR Code) ou cartão de crédito (tokenizado). */
  submitPayment(): void {
    const currentInvoice = this.invoice();
    if (!currentInvoice || this.isPaying()) return;

    const method = this.paymentMethod();
    this.isPaying.set(true);

    if (method === 'CREDIT_CARD' && (this.cardForm.invalid || this.holderForm.invalid)) {
      this.cardForm.markAllAsTouched();
      this.holderForm.markAllAsTouched();
      this.isPaying.set(false);
      return;
    }

    const payload = {
      method,
      card: method === 'CREDIT_CARD' ? this.buildCardPayload() : undefined,
      holder_info: method === 'CREDIT_CARD' ? this.holderForm.getRawValue() : undefined,
    } as const;

    this.billingService
      .pay(currentInvoice.id, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (method === 'PIX') {
            const qr = response.data.qr_code_base64 ?? null;
            const base64 = qr?.replace('data:image/png;base64,', '') ?? null;
            this.pixQrCode.set(base64);
            this.pixPayload.set(response.data.pix_copy_paste ?? null);
            this.pixExpiresAt.set(response.data.expires_at ?? null);
            this.paymentStage.set('pix');
            this.isPaying.set(false);
            return;
          }

          this.isPaying.set(false);
          this.paymentSuccess.emit();
        },
        error: (error: { error?: { message?: string } }) => {
          this.isPaying.set(false);
          const message = error?.error?.message || 'Erro ao gerar cobrança.';

          if (message.includes('CPF') || message.includes('CNPJ') || message.includes('documento')) {
            this.toast.error(message);
            this.close();
            void this.router.navigate(['/settings/tenant']);
            return;
          }

          this.toast.error(message);
        },
      });
  }

  /** Copia o código PIX copia-e-cola para a área de transferência. */
  copyPixPayload(): void {
    const payload = this.pixPayload();
    if (!payload) return;
    void navigator.clipboard.writeText(payload);
    this.toast.success('Código PIX copiado.');
  }

  /** Fecha o modal e redefine todos os estados internos. */
  close(): void {
    this.resetState();
    this.closed.emit();
  }

  /**
   * Retorna a mensagem de erro adequada para um campo do formulário de cartão.
   * @param field Nome do campo do cartão
   * @returns Mensagem de erro de padrão ou de campo obrigatório
   */
  protected getCardFieldErrorMessage(field: CardFieldName): string {
    const control = this.cardForm.controls[field];

    if (control.hasError('pattern')) {
      return CARD_FIELD_MESSAGES[field].pattern;
    }

    return CARD_FIELD_MESSAGES[field].required;
  }

  private resetState(): void {
    this.isPaying.set(false);
    this.paymentStage.set('select');
    this.paymentMethod.set('PIX');
    this.paymentMethodControl.setValue('PIX', { emitEvent: false });
    this.pixPayload.set(null);
    this.pixQrCode.set(null);
    this.pixExpiresAt.set(null);
    this.cardForm.reset();
    this.holderForm.reset();
  }

  private buildCardPayload(): {
    holder_name: string;
    number: string;
    expiry_month: string;
    expiry_year: string;
    cvv: string;
  } {
    const card = this.cardForm.getRawValue();

    return {
      holder_name: trimValue(card.holder_name),
      number: normalizeCardNumber(card.number),
      expiry_month: trimValue(card.expiry_month),
      expiry_year: trimValue(card.expiry_year),
      cvv: trimValue(card.cvv),
    };
  }
}

export default InvoicePaymentModalComponent;
