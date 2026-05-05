import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  AfCardComponent,
  AfCurrencyInputComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type LimitMode,
  type PlatformPlan,
  type PlatformPlanPayload,
} from '@platform/models/platform-plan.model';
import { PlatformPlanService } from '@platform/services/platform-plan.service';

@Component({
  selector: 'app-plan-crud-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfCardComponent,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfCurrencyInputComponent,
    AfSwitchInputComponent,
/**
 * Plan crud form component for the Platform module.
 * @selector app-plan-crud-form
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './plan-crud-form.html',
})
export class PlanCrudFormComponent {
  private readonly plansService = inject(PlatformPlanService);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);
  private readonly manualSlug = signal(false);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly plan = input<PlatformPlan | null>(null);
  readonly saved = output<PlatformPlan>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);

  readonly limitModeOptions: AfSelectOption[] = [
    { label: 'Ilimitado', value: 'UNLIMITED' },
    { label: 'Limitado', value: 'LIMITED' },
  ];

  readonly booleanOptions: AfSelectOption[] = [
    { label: 'Habilitado', value: 'true' },
    { label: 'Desabilitado', value: 'false' },
  ];

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    slug: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    limit_users: this.fb.control<number | null>(1, [Validators.required, Validators.min(0)]),
    storage_mode: this.fb.control<LimitMode>('LIMITED', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    storage_limit_gb: this.fb.control<number | null>(5),
    ai_enabled: this.fb.control('false', { nonNullable: true, validators: [Validators.required] }),
    token_limit_monthly: this.fb.control<number | null>(50000, [Validators.min(0)]),
    allow_overage: this.fb.control(false, { nonNullable: true }),
    overage_price_per_1k: this.fb.control<number | null>(null, [Validators.min(0)]),
    whatsapp_integrations_limit: this.fb.control<number | null>(1, [
      Validators.required,
      Validators.min(0),
    ]),
    negotiations_mode: this.fb.control<LimitMode>('LIMITED', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    negotiations_limit: this.fb.control<number | null>(100),
    price_monthly: this.fb.control<number | null>(99, [Validators.required, Validators.min(0)]),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.plan();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        this.manualSlug.set(true);
        this.form.reset({
          name: item.name,
          slug: item.slug,
          limit_users: item.limit_users,
          storage_mode: item.storage_mode,
          storage_limit_gb: item.storage_limit_gb ?? this.toGb(item.storage_limit_bytes),
          ai_enabled: item.ai_enabled ? 'true' : 'false',
          token_limit_monthly: item.token_limit_monthly ?? null,
          allow_overage: item.allow_overage,
          overage_price_per_1k:
            item.overage_price_per_1k !== null ? Number(item.overage_price_per_1k) : null,
          whatsapp_integrations_limit: item.whatsapp_integrations_limit,
          negotiations_mode: item.negotiations_mode,
          negotiations_limit: item.negotiations_limit ?? 0,
          price_monthly: Number(item.price_monthly),
          is_active: item.is_active,
        });
      } else {
        this.lastLoadedId.set(null);
        this.manualSlug.set(false);
        this.resetForm();
      }
    });

    this.form.controls.name.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        if (this.manualSlug()) return;
        this.form.controls.slug.setValue(this.slugify(value ?? ''), { emitEvent: false });
      });

    this.form.controls.slug.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.manualSlug.set(true));

    this.form.controls.storage_mode.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((mode) => {
        if (mode === 'LIMITED') this.form.controls.storage_limit_gb.enable({ emitEvent: false });
        else this.form.controls.storage_limit_gb.disable({ emitEvent: false });
      });

    this.form.controls.negotiations_mode.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((mode) => {
        if (mode === 'LIMITED') this.form.controls.negotiations_limit.enable({ emitEvent: false });
        else this.form.controls.negotiations_limit.disable({ emitEvent: false });
      });

    this.form.controls.allow_overage.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((enabled) => {
        if (enabled) this.form.controls.overage_price_per_1k.enable({ emitEvent: false });
        else this.form.controls.overage_price_per_1k.disable({ emitEvent: false });
      });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.buildPayload();
    const editing = this.plan();
    const request = editing
      ? this.plansService.update(editing.id, payload)
      : this.plansService.create(payload);

    this.isSaving.set(true);
    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private buildPayload(): PlatformPlanPayload {
    const values = this.form.getRawValue();
    const storageLimitBytes =
      values.storage_mode === 'LIMITED' && values.storage_limit_gb
        ? Math.round(values.storage_limit_gb * 1024 * 1024 * 1024)
        : null;

    return {
      name: values.name.trim(),
      slug: values.slug.trim(),
      limit_users: Number(values.limit_users ?? 0),
      storage_mode: values.storage_mode,
      storage_limit_bytes: storageLimitBytes,
      ai_enabled: values.ai_enabled === 'true',
      token_limit_monthly: values.token_limit_monthly !== null ? Number(values.token_limit_monthly) : null,
      allow_overage: Boolean(values.allow_overage),
      overage_price_per_1k: values.allow_overage
        ? Number(values.overage_price_per_1k ?? 0)
        : null,
      whatsapp_integrations_limit: Number(values.whatsapp_integrations_limit ?? 0),
      negotiations_mode: values.negotiations_mode,
      negotiations_limit:
        values.negotiations_mode === 'LIMITED' ? Number(values.negotiations_limit ?? 0) : null,
      price_monthly: Number(values.price_monthly ?? 0),
      is_active: Boolean(values.is_active),
    };
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      slug: '',
      limit_users: 1,
      storage_mode: 'LIMITED',
      storage_limit_gb: 5,
      ai_enabled: 'false',
      token_limit_monthly: 50000,
      allow_overage: false,
      overage_price_per_1k: null,
      whatsapp_integrations_limit: 1,
      negotiations_mode: 'LIMITED',
      negotiations_limit: 100,
      price_monthly: 99,
      is_active: true,
    });
  }

  private slugify(value: string): string {
    return value
      .toLowerCase()
      .normalize('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)+/g, '')
      .replace(/--+/g, '-');
  }

  private toGb(bytes?: number | null): number | null {
    if (!bytes) return null;
    return Math.round(bytes / 1024 / 1024 / 1024);
  }
}
