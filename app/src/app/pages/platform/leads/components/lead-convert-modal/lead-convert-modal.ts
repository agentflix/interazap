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
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { type PlatformLead, PlatformLeadService } from '@core/services/platform-lead.service';
import { PlatformPlanService } from '@platform/services/platform-plan.service';
import { applyServerErrors } from '@core/utils/form-server-errors.util';

@Component({
  selector: 'app-lead-convert-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfModalComponent,
    AfAlertComponent,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './lead-convert-modal.html',
})
export class LeadConvertModalComponent {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(PlatformLeadService);
  private readonly planService = inject(PlatformPlanService);
  private readonly destroyRef = inject(DestroyRef);

  readonly open = input(false);
  readonly lead = input<PlatformLead | null>(null);

  readonly converted = output<PlatformLead>();
  readonly cancelled = output<void>();

  readonly isSubmitting = signal(false);
  readonly isLoadingPlans = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly planOptions = signal<AfSelectOption[]>([{ label: 'Plano padrão', value: '' }]);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    email: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    phone: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    document: this.fb.control('', { nonNullable: true }),
    plan_id: this.fb.control('', { nonNullable: true }),
  });

  constructor() {
    this.loadPlans();

    effect(() => {
      const isOpen = this.open();
      const lead = this.lead();

      if (!isOpen || !lead) {
        return;
      }

      this.errorMessage.set(null);
      this.form.reset({
        name: lead.company?.trim() ? lead.company : lead.name,
        email: lead.email,
        phone: lead.phone,
        document: '',
        plan_id: '',
      });
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSubmitting()) {
      this.form.markAllAsTouched();
      return;
    }

    const lead = this.lead();
    if (!lead) {
      return;
    }

    const formValue = this.form.getRawValue();

    this.errorMessage.set(null);
    this.isSubmitting.set(true);

    this.service
      .convert(lead.id, {
        name: formValue.name.trim(),
        email: formValue.email.trim(),
        phone: formValue.phone.trim(),
        document: formValue.document.trim() || null,
        plan_id: formValue.plan_id || null,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSubmitting.set(false);
          this.converted.emit(response.data);
        },
        error: (error: { status?: number; error?: { message?: string; errors?: Record<string, string[]> } }) => {
          this.isSubmitting.set(false);
          if (error.status === 422 && error.error?.errors) {
            applyServerErrors(this.form, error.error.errors);
            return;
          }
          this.errorMessage.set(
            error?.error?.message ?? 'Não foi possível converter o lead. Tente novamente.',
          );
        },
      });
  }

  close(): void {
    if (this.isSubmitting()) {
      return;
    }

    this.cancelled.emit();
  }

  private loadPlans(): void {
    this.isLoadingPlans.set(true);

    this.planService
      .list({ per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const options = response.data.map((plan) => ({
            label: plan.name,
            value: plan.id,
          }));

          this.planOptions.set([{ label: 'Plano padrão', value: '' }, ...options]);
          this.isLoadingPlans.set(false);
        },
        error: () => {
          this.isLoadingPlans.set(false);
        },
      });
  }
}
