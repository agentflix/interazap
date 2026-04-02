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
import { type Observable, forkJoin } from 'rxjs';
import { AfTextInputComponent } from '@shared/components';
import {
  type CreateUazapiInstancePayload,
  type UazapiInstance,
  UazapiInstancesService,
} from '@core/services/uazapi-instances.service';

/**
 * Uazapi instance form component for the Platform module.
 * @selector app-uazapi-instance-form
 */
@Component({
  selector: 'app-uazapi-instance-form',
  standalone: true,
  imports: [ReactiveFormsModule, AfTextInputComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './uazapi-instance-form.html',
})
export class UazapiInstanceFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(UazapiInstancesService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly instance = input<UazapiInstance | null>(null);
  readonly saved = output<UazapiInstance>();
  readonly cancelled = output<undefined>();

  readonly isSaving = signal(false);
  readonly isEditing = computed(() => this.instance() !== null);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    system_name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    config_field_01: this.fb.control(''),
    config_field_02: this.fb.control(''),
  });

  constructor() {
    effect(() => {
      const current = this.instance();
      if (current) {
        if (this.lastLoadedId() === current.id) return;
        this.lastLoadedId.set(current.id);

        this.form.reset({
          name: current.name ?? '',
          system_name: current.system_name ?? '',
          config_field_01: current.config?.['admin_field_01'] ?? '',
          config_field_02: current.config?.['admin_field_02'] ?? '',
        });
        this.form.controls.system_name.disable({ emitEvent: false });
      } else {
        this.lastLoadedId.set(null);
        this.form.enable({ emitEvent: false });
        this.resetForm();
      }
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const value = this.form.getRawValue();
    const configPayload: Record<string, string | null> = {
      admin_field_01: this.normalizeOptional(value.config_field_01),
      admin_field_02: this.normalizeOptional(value.config_field_02),
    };

    const payload: CreateUazapiInstancePayload = {
      name: value.name.trim(),
      system_name: value.system_name.trim(),
      config: configPayload,
    };

    const editing = this.instance();
    this.isSaving.set(true);

    if (editing === null) {
      this.createInstance(payload);
      return;
    }

    this.updateInstance(editing, payload, configPayload);
  }

  cancel(): void {
    this.cancelled.emit(undefined);
  }

  private createInstance(payload: CreateUazapiInstancePayload): void {
    this.service
      .create(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSaving.set(false);
          this.saved.emit(response);
        },
        error: () => {
          this.isSaving.set(false);
        },
      });
  }

  private updateInstance(
    editing: UazapiInstance,
    payload: CreateUazapiInstancePayload,
    configPayload: Record<string, string | null>,
  ): void {
    const currentConfig = editing.config ?? {};
    const updates = this.buildUpdateRequests(editing, payload, configPayload, currentConfig);

    if (updates.length === 0) {
      this.finishEdit(editing, payload.name, currentConfig, configPayload);
      return;
    }

    forkJoin(updates)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.finishEdit(editing, payload.name, currentConfig, configPayload);
        },
        error: () => {
          this.isSaving.set(false);
        },
      });
  }

  private buildUpdateRequests(
    editing: UazapiInstance,
    payload: CreateUazapiInstancePayload,
    configPayload: Record<string, string | null>,
    currentConfig: Record<string, string | null>,
  ): Observable<UazapiInstance>[] {
    const updates: Observable<UazapiInstance>[] = [];

    if ((editing.name ?? '') !== payload.name) {
      updates.push(this.service.updateName(editing.id, payload.name));
    }

    if (
      (currentConfig['admin_field_01'] ?? null) !== configPayload['admin_field_01'] ||
      (currentConfig['admin_field_02'] ?? null) !== configPayload['admin_field_02']
    ) {
      updates.push(
        this.service.updateAdminFields(editing.id, {
          config: configPayload,
        }),
      );
    }

    return updates;
  }

  private finishEdit(
    editing: UazapiInstance,
    name: string,
    currentConfig: Record<string, string | null>,
    configPayload: Record<string, string | null>,
  ): void {
    this.isSaving.set(false);
    this.saved.emit({
      ...editing,
      name,
      config: { ...currentConfig, ...configPayload },
    });
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      system_name: '',
      config_field_01: '',
      config_field_02: '',
    });
  }

  private normalizeOptional(value?: string | null): string | null {
    const trimmed = value?.trim() ?? '';
    return trimmed === '' ? null : trimmed;
  }
}
