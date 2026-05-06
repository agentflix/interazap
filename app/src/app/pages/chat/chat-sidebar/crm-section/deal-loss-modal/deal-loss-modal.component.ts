import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import {
  type FormControl,
  type FormGroup,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { ReasonLossService } from 'src/app/core/services/reason-loss.service';
import { type CRMNegotiation } from '../crm-negotiation.model';
import { ButtonComponent, LoadingButtonComponent } from 'src/app/shared/components/buttons';
import {
  SelectInputComponent,
  TextareaInputComponent,
  type SelectOption,
} from 'src/app/shared/components/inputs';
import { ModalComponent } from 'src/app/shared/components/modal/modal';

/**
 * Modal para marcar negociacao como perdida.
 *
 * @remarks
 * Motivo obrigatorio, observacoes opcionais.
 * Requer selecao de motivo de perda antes de confirmar.
 *
 * @example
 * ```html
 * <app-deal-loss-modal
 *   [isOpen]="isOpen"
 *   [deal]="deal"
 *   (closeModal)="onClose()"
 *   (confirmed)="onConfirmed()" />
 * ```
 */
@Component({
  selector: 'app-deal-loss-modal',
  templateUrl: './deal-loss-modal.component.html',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    ModalComponent,
    ButtonComponent,
    LoadingButtonComponent,
    SelectInputComponent,
    TextareaInputComponent,
  ],
  host: { class: 'block' },
})
export class DealLossModalComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly negotiationService = inject(NegotiationService);
  private readonly reasonLossService = inject(ReasonLossService);

  readonly isOpen = input.required<boolean>();
  readonly deal = input<CRMNegotiation | null>(null);
  readonly closeModal = output<void>();
  readonly confirmed = output<void>();

  readonly form: FormGroup<{
    reason_id: FormControl<string | number | null>;
    notes: FormControl<string | null>;
  }>;
  readonly isConfirming = signal(false);
  readonly reasons = signal<{ id: string | number; name: string }[]>([]);
  readonly reasonOptions = computed<readonly SelectOption[]>(() =>
    this.reasons().map((reason) => ({
      value: reason.id,
      label: reason.name,
    })),
  );

  constructor() {
    this.form = this.fb.group({
      reason_id: this.fb.control<string | number | null>('', Validators.required),
      notes: this.fb.control<string | null>('', Validators.maxLength(500)),
    });
  }

  ngOnInit(): void {
    this.loadReasons();
  }

  /**
   * Carregar motivos de perda.
   */
  loadReasons(): void {
    this.reasonLossService.list({ active: true }).subscribe({
      next: (response) => {
        this.reasons.set(response.data);
      },
    });
  }

  /**
   * Confirmar perda da negociação.
   */
  onConfirm(): void {
    const negotiation = this.deal();
    if (!negotiation) {
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isConfirming.set(true);
    const { reason_id, notes } = this.form.value;
    const normalizedReasonId = reason_id ? String(reason_id) : undefined;
    const normalizedNotes = notes || undefined;

    this.negotiationService
      .markAsLost(negotiation.id, normalizedReasonId, normalizedNotes)
      .subscribe({
        next: () => {
          this.isConfirming.set(false);
          this.confirmed.emit();
        },
        error: () => {
          this.isConfirming.set(false);
        },
      });
  }

  /**
   * Fechar modal.
   */
  onClose(): void {
    this.form.reset();
    this.closeModal.emit();
  }
}
