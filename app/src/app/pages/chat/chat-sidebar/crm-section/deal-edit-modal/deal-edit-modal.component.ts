import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  output,
  signal,
  computed,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type FormGroup, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { FunnelService } from 'src/app/core/services/funnel.service';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { UserService } from 'src/app/core/services/user.service';
import { type CRMNegotiation } from '../crm-negotiation.model';
import { TextInputComponent } from 'src/app/shared/components/text-input/text-input';
import {
  type SelectOption,
  SelectInputComponent,
} from 'src/app/shared/components/select-input/select-input';
import { TextareaInputComponent } from 'src/app/shared/components/textarea-input/textarea-input';
import { ButtonComponent, LoadingButtonComponent } from 'src/app/shared/components/buttons';
import { ModalComponent } from 'src/app/shared/components/modal/modal';

/**
 * Modal para criar ou editar negociacao.
 *
 * @remarks
 * Formulario reativo com validacao client-side.
 * Gerencia funis, etapas, responsavel e data prevista de fechamento.
 *
 * @example
 * ```html
 * <app-deal-edit-modal
 *   [isOpen]="isOpen"
 *   [deal]="deal"
 *   [contactId]="contactId"
 *   (closeModal)="onClose()"
 *   (dealSaved)="onSaved($event)" />
 * ```
 */
@Component({
  selector: 'app-deal-edit-modal',
  templateUrl: './deal-edit-modal.component.html',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ReactiveFormsModule,
    ModalComponent,
    TextInputComponent,
    SelectInputComponent,
    TextareaInputComponent,
    ButtonComponent,
    LoadingButtonComponent,
  ],
  host: { class: 'block' },
})
export class DealEditModalComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly negotiationService = inject(NegotiationService);
  private readonly funnelService = inject(FunnelService);
  private readonly userService = inject(UserService);
  private readonly destroyRef = inject(DestroyRef);

  readonly isOpen = input.required<boolean>();
  readonly deal = input<CRMNegotiation | null>(null);
  readonly contactId = input.required<string>();
  readonly closeModal = output<void>();
  readonly dealSaved = output<CRMNegotiation>();

  readonly form: FormGroup;
  readonly isSaving = signal(false);
  readonly selectedFunnelId = signal<string>('');
  readonly funnels = signal<
    {
      id: string | number;
      name: string;
      steps?: { id: string | number; name: string }[];
    }[]
  >([]);
  readonly users = signal<{ id: string; name: string }[]>([]);

  constructor() {
    this.form = this.fb.group({
      title: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(255)]],
      funnel_id: ['', Validators.required],
      step_id: ['', Validators.required],
      responsible_id: [''],
      expected_close: [''],
      notes: ['', Validators.maxLength(1000)],
    });

    // Sincronizar funnel_id do formulário com signal reativo
    this.form
      .get('funnel_id')!
      .valueChanges.pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((funnelId: string) => {
        this.selectedFunnelId.set(funnelId);
        // Resetar etapa ao trocar funil
        const funnel = this.funnels().find(
          (f) => f.id === funnelId || f.id.toString() === funnelId,
        );
        const firstStepId = funnel?.steps?.[0]?.id ?? '';
        this.form.get('step_id')!.setValue(firstStepId.toString());
      });
  }

  ngOnInit(): void {
    this.loadFunnels();
    this.loadUsers();

    // Preencher formulário se edição
    if (this.deal()) {
      const d = this.deal()!;
      const funnelId = (d.funnel_id ?? d.funnel?.id ?? '').toString();
      this.form.patchValue({
        title: d.title,
        funnel_id: funnelId,
        step_id: (d.step?.id ?? '').toString(),
        responsible_id: d.user?.id ?? '',
        expected_close: d.expected_close ?? d.expected_close_date ?? '',
      });
      this.selectedFunnelId.set(funnelId);
    }
  }

  /**
   * Carregar funis disponíveis.
   */
  loadFunnels(): void {
    this.funnelService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const items = response.data ?? [];
          this.funnels.set(items);
          if (items.length > 0 && !this.deal()) {
            const firstFunnelId = items[0].id.toString();
            const firstStepId = (items[0].steps?.[0]?.id ?? '').toString();
            this.form.patchValue({ funnel_id: firstFunnelId, step_id: firstStepId });
            this.selectedFunnelId.set(firstFunnelId);
          } else if (this.deal()) {
            // Garantir que o signal está sincronizado com funil da edição
            this.selectedFunnelId.set(
              (this.deal()!.funnel_id ?? this.deal()!.funnel?.id ?? '').toString(),
            );
          }
        },
      });
  }

  /**
   * Carregar usuários para responsável.
   */
  loadUsers(): void {
    this.userService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.users.set(response.data ?? []);
        },
      });
  }

  /**
   * Opções de funis formatadas para SelectInputComponent.
   */
  readonly funnelOptions = computed<SelectOption[]>(() =>
    this.funnels().map((f) => ({ value: f.id.toString(), label: f.name })),
  );

  /**
   * Opções de etapas formatadas para SelectInputComponent.
   * Recalcula automaticamente ao trocar de funil via selectedFunnelId signal.
   */
  readonly stepOptions = computed<SelectOption[]>(() => {
    const funnelId = this.selectedFunnelId();
    const funnel = this.funnels().find((f) => f.id === funnelId || f.id.toString() === funnelId);
    const steps = funnel?.steps ?? [];
    return steps.map((s) => ({ value: s.id.toString(), label: s.name }));
  });

  /**
   * Opções de usuários formatadas para SelectInputComponent.
   */
  readonly userOptions = computed<SelectOption[]>(() =>
    this.users().map((u) => ({ value: u.id, label: u.name })),
  );

  /**
   * Salvar negociação (criar ou atualizar).
   */
  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    const payload = {
      title: this.form.value.title,
      funnel_id: this.form.value.funnel_id,
      step_id: this.form.value.step_id,
      contact_id: this.contactId(),
      crm_company_id: '',
      user_id: this.form.value.responsible_id,
      expected_close_date: this.form.value.expected_close,
      notes: this.form.value.notes,
    };

    const save$ = this.deal()
      ? this.negotiationService.update(this.deal()!.id, payload)
      : this.negotiationService.create(payload);

    save$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.dealSaved.emit(response.data.negotiation as CRMNegotiation);
      },
      error: () => {
        this.isSaving.set(false);
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

  /**
   * Verificar se campo tem erro.
   */
  hasError(field: string): boolean {
    const control = this.form.get(field);
    return !!(control && control.invalid && control.touched);
  }

  /**
   * Mensagem de erro do campo.
   */
  getErrorMessage(field: string): string {
    const control = this.form.get(field);
    if (!control || !control.errors) return '';

    const errors = control.errors;
    if (errors['required']) return 'Campo obrigatório';
    if (errors['minlength']) return `Mínimo ${errors['minlength'].requiredLength} caracteres`;
    if (errors['maxlength']) return `Máximo ${errors['maxlength'].requiredLength} caracteres`;

    return 'Campo inválido';
  }
}
