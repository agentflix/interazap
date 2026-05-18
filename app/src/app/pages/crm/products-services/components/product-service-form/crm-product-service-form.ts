import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  AfAlertComponent,
  AfCheckboxInputComponent,
  AfCurrencyInputComponent,
  AfNumberInputComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { ProductServiceService } from '@core/services/crm-product-service.service';
import type { ProductService } from '@core/models/product-service.model';

/**
 * Product/Service form component — create/edit.
 * Conditional stock fields for products. Business logic from source preserved.
 */
@Component({
  selector: 'app-product-service-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfAlertComponent,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSelectInputComponent,
    AfCurrencyInputComponent,
    AfNumberInputComponent,
    AfSwitchInputComponent,
    AfCheckboxInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-product-service-form.html',
})
export class ProductServiceFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly productServiceService = inject(ProductServiceService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly item = input<ProductService | null>(null);
  readonly saved = output<ProductService>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly typeOptions: AfSelectOption[] = [
    { label: 'Produto', value: 'product' },
    { label: 'Serviço', value: 'service' },
  ];

  readonly unitOptions: AfSelectOption[] = [
    { label: 'Selecione...', value: '' },
    { label: 'un (unidade)', value: 'un' },
    { label: 'pc (peça)', value: 'pc' },
    { label: 'cx (caixa)', value: 'cx' },
    { label: 'kg (quilograma)', value: 'kg' },
    { label: 'g (grama)', value: 'g' },
    { label: 'lt (litro)', value: 'lt' },
    { label: 'ml (mililitro)', value: 'ml' },
    { label: 'm (metro)', value: 'm' },
    { label: 'm² (metro quadrado)', value: 'm²' },
    { label: 'm³ (metro cúbico)', value: 'm³' },
    { label: 'par', value: 'par' },
    { label: 'kit', value: 'kit' },
    { label: 'conj (conjunto)', value: 'conj' },
    { label: 'rol (rolo)', value: 'rol' },
    { label: 'sac (saco)', value: 'sac' },
    { label: 'emb (embalagem)', value: 'emb' },
  ];

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    code: this.fb.control('', { nonNullable: true }),
    description: this.fb.control('', { nonNullable: true }),
    type: this.fb.control<'product' | 'service'>('product', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    price: this.fb.control<number | null>(null),
    cost: this.fb.control<number | null>(null),
    unit: this.fb.control('', { nonNullable: true }),
    stock_quantity: this.fb.control(0, { nonNullable: true }),
    min_stock: this.fb.control(0, { nonNullable: true }),
    is_active: this.fb.control(true, { nonNullable: true }),
    is_featured: this.fb.control(false, { nonNullable: true }),
    track_stock: this.fb.control(false, { nonNullable: true }),
  });

  readonly isProduct = computed(() => this.form.controls.type.value === 'product');

  constructor() {
    // Re-compute isProduct when type changes
    this.form.controls.type.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      // Angular computed signal will auto-update via template polling
    });

    effect(() => {
      const current = this.item();
      if (current) {
        if (this.lastLoadedId() === current.id) return;
        this.lastLoadedId.set(current.id);
        this.form.reset({
          name: current.name,
          code: current.code ?? '',
          description: current.description ?? '',
          type: current.type,
          price: current.price ?? null,
          cost: current.cost ?? null,
          unit: current.unit ?? '',
          stock_quantity: current.stock_quantity ?? 0,
          min_stock: current.min_stock ?? 0,
          is_active: current.is_active,
          is_featured: current.is_featured ?? false,
          track_stock: current.track_stock ?? false,
        });
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
      }
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const fv = this.form.getRawValue();
    const isProduct = fv.type === 'product';
    const stockQty = isProduct ? (fv.stock_quantity ?? 0) : 0;

    const payload = {
      name: fv.name,
      code: fv.code?.trim() || undefined,
      description: fv.description?.trim() || undefined,
      type: fv.type,
      price: fv.price ?? undefined,
      cost: fv.cost ?? undefined,
      unit: fv.unit?.trim() || undefined,
      stock_quantity: isProduct ? stockQty : undefined,
      min_stock: isProduct ? (fv.min_stock ?? 0) : undefined,
      track_stock: isProduct ? fv.track_stock : false,
      stock: stockQty,
      is_active: fv.is_active,
      is_featured: fv.is_featured,
    };

    const editing = this.item();
    const request$ = editing
      ? this.productServiceService.update(editing.id, payload)
      : this.productServiceService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data);
      },
      error: (err: { error?: { message?: string } }) => {
        this.isSaving.set(false);
        this.errorMessage.set(err?.error?.message || 'Não foi possível salvar. Tente novamente.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      code: '',
      description: '',
      type: 'product',
      price: null,
      cost: null,
      unit: '',
      stock_quantity: 0,
      min_stock: 0,
      is_active: true,
      is_featured: false,
      track_stock: false,
    });
    this.errorMessage.set(null);
  }
}
