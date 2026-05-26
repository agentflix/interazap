import {
  type OnChanges,
  type Signal,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  input,
  output,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import {
  type FormArray,
  type FormControl,
  type FormGroup,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  CurrencyInputComponent,
  TextInputComponent,
  TextareaInputComponent,
} from 'src/app/shared/components/inputs';
import { ButtonComponent } from '@shared/components/button/button';
import { IconButtonComponent } from 'src/app/shared/components/icon-button/icon-button';
import { LoadingButtonComponent } from 'src/app/shared/components/loading-button/loading-button';
import {
  type Proposal,
  type ProposalItem,
  CRMProposalService,
} from '../../services/crm-proposal.service';

/**
 * Formulário de criação e edição de propostas comerciais do CRM.
 */
@Component({
  selector: 'app-proposal-form',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    LucideAngularModule,
    TextInputComponent,
    CurrencyInputComponent,
    TextareaInputComponent,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
  ],
  templateUrl: './proposal-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProposalFormComponent implements OnChanges {
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);
  private readonly proposalService = inject(CRMProposalService);

  readonly negotiationId = input<string | number>();
  readonly proposal = input<Proposal | null>(null);
  readonly prefillItems = input<ProposalItem[]>([]);

  readonly saved = output<Proposal>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly variableTags = [
    { label: '{{nome}}', value: '{{nome}}' },
    { label: '{{empresa}}', value: '{{empresa}}' },
    { label: '{{produto}}', value: '{{produto}}' },
    { label: '{{total}}', value: '{{total}}' },
    { label: '{{validade}}', value: '{{validade}}' },
  ];

  readonly form = this.fb.group({
    title: ['', [Validators.required, Validators.maxLength(255)]],
    number: this.fb.control<number | null>(null),
    valid_until: [''],
    notes: [''],
    items: this.fb.array<FormGroup>([]),
  });

  readonly total: Signal<number> = computed(() =>
    this.items.controls.reduce((sum, control) => {
      const quantity = Number(control.get('quantity')?.value ?? 0);
      const unitPrice = Number(control.get('unit_price')?.value ?? 0);
      const discount = Number(control.get('discount')?.value ?? 0);
      return sum + Math.max(0, quantity * unitPrice - discount);
    }, 0),
  );

  get items(): FormArray<FormGroup> {
    return this.form.get('items') as FormArray<FormGroup>;
  }

  ngOnChanges(): void {
    const currentProposal = this.proposal();
    if (currentProposal) {
      this.form.patchValue({
        title: currentProposal.title,
        number: currentProposal.number ?? null,
        valid_until: currentProposal.valid_until ?? '',
        notes: currentProposal.notes ?? '',
      });
      this.setItems(currentProposal.items ?? []);
      return;
    }

    this.form.patchValue({
      title: '',
      number: null,
      valid_until: '',
      notes: '',
    });

    const initialItems = this.prefillItems();
    if (initialItems.length > 0) {
      this.setItems(initialItems);
      return;
    }

    this.setItems([]);
  }

  addItem(item?: ProposalItem): void {
    this.items.push(
      this.fb.group({
        name: [item?.name ?? '', [Validators.required, Validators.maxLength(255)]],
        quantity: [item?.quantity ?? 1, [Validators.required, Validators.min(1)]],
        unit_price: [item?.unit_price ?? 0, [Validators.required, Validators.min(0)]],
        discount: [item?.discount ?? 0, [Validators.min(0)]],
        crm_product_id: [item?.crm_product_id ?? null],
        position: [item?.position ?? this.items.length + 1],
      }),
    );
  }

  removeItem(index: number): void {
    if (this.items.length <= 1) return;
    this.items.removeAt(index);
  }

  trackItem(index: number): number {
    return index;
  }

  getItemControl(item: FormGroup, controlName: string): FormControl {
    return item.get(controlName) as FormControl;
  }

  submit(): void {
    const currentNegotiationId = this.negotiationId();
    if (this.form.invalid || !currentNegotiationId) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = {
      ...this.form.value,
      items: this.items.controls.map((control, index) => ({
        ...control.value,
        position: Number(control.get('position')?.value ?? index + 1),
      })),
    } as unknown as Proposal;

    this.isSaving.set(true);
    this.errorMessage.set(null);

    const currentProposal = this.proposal();
    const request$ = currentProposal
      ? this.proposalService.update(currentProposal.id, payload)
      : this.proposalService.create(currentNegotiationId, payload);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data.proposal);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar a proposta.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.patchValue({
      title: '',
      number: null,
      valid_until: '',
      notes: '',
    });
    this.setItems([]);
    this.errorMessage.set(null);
  }

  insertVariable(variable: string): void {
    const current = this.form.controls.notes.value ?? '';
    this.form.controls.notes.setValue(current ? `${current} ${variable}` : variable);
  }

  private setItems(items: ProposalItem[]): void {
    this.items.clear();
    if (items.length === 0) {
      this.addItem();
      return;
    }

    items.forEach((item) => this.addItem(item));
  }
}
