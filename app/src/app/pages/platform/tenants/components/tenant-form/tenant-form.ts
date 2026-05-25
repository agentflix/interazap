import {
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import { catchError, finalize, startWith, switchMap, tap } from 'rxjs/operators';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfDocumentInputComponent,
  AfMaskedInputComponent,
  AfPhoneInputComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { CompanyService } from '@core/services/company.service';
import type { Company } from '@core/models/company.model';
import { PlatformPlanService } from '@platform/services/platform-plan.service';
import { type AddressData, type CnpjData, UtilsService } from '@core/services/utils.service';
import { AiGovernanceService } from '@core/services/ai-governance.service';
import { ToastService } from '@core/services/toast.service';
import { buildAddressLine, digitsOnly, formatCepForForm, normalizeUf } from './tenant-form.utils';
import { applyServerErrors } from '@core/utils/form-server-errors.util';

@Component({
  selector: 'app-tenant-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfTextInputComponent,
    AfDocumentInputComponent,
    AfMaskedInputComponent,
    AfPhoneInputComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
    AfButtonComponent,
    AfAlertComponent,
/**
 * Tenant form component for the Platform module.
 * @selector app-tenant-form
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tenant-form.html',
})
export class TenantFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly companyService = inject(CompanyService);
  private readonly platformPlanService = inject(PlatformPlanService);
  private readonly utilsService = inject(UtilsService);
  private readonly aiGovernanceService = inject(AiGovernanceService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | number | null>(null);

  readonly tenant = input<Company | null>(null);
  readonly saved = output<Company>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly isSearchingCnpj = signal(false);
  readonly isSearchingCep = signal(false);
  readonly isCreateMode = computed(() => this.tenant() === null);
  readonly lookupFeedback = signal<string | null>(null);
  readonly segmentOptions = signal<AfSelectOption[]>([]);
  readonly planOptions = signal<AfSelectOption[]>([]);
  readonly stateOptions = signal<AfSelectOption[]>([
    { value: 'AC', label: 'Acre' },
    { value: 'AL', label: 'Alagoas' },
    { value: 'AP', label: 'Amapá' },
    { value: 'AM', label: 'Amazonas' },
    { value: 'BA', label: 'Bahia' },
    { value: 'CE', label: 'Ceará' },
    { value: 'DF', label: 'Distrito Federal' },
    { value: 'ES', label: 'Espírito Santo' },
    { value: 'GO', label: 'Goiás' },
    { value: 'MA', label: 'Maranhão' },
    { value: 'MT', label: 'Mato Grosso' },
    { value: 'MS', label: 'Mato Grosso do Sul' },
    { value: 'MG', label: 'Minas Gerais' },
    { value: 'PA', label: 'Pará' },
    { value: 'PB', label: 'Paraíba' },
    { value: 'PR', label: 'Paraná' },
    { value: 'PE', label: 'Pernambuco' },
    { value: 'PI', label: 'Piauí' },
    { value: 'RJ', label: 'Rio de Janeiro' },
    { value: 'RN', label: 'Rio Grande do Norte' },
    { value: 'RS', label: 'Rio Grande do Sul' },
    { value: 'RO', label: 'Rondônia' },
    { value: 'RR', label: 'Roraima' },
    { value: 'SC', label: 'Santa Catarina' },
    { value: 'SP', label: 'São Paulo' },
    { value: 'SE', label: 'Sergipe' },
    { value: 'TO', label: 'Tocantins' },
  ]);
  readonly hasBasicValidData = signal(false);

  private readonly lastAutoLookupCnpj = signal<string | null>(null);
  private readonly lastAutoLookupCep = signal<string | null>(null);
  private isAutoPatching = false;
  private readonly manualEditedFields = signal<Record<string, boolean>>({
    name: false,
    phone: false,
    street: false,
    number: false,
    complement: false,
    district: false,
    city: false,
    state: false,
    zip_code: false,
  });

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    document: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    email: this.fb.control('', { nonNullable: true, validators: [Validators.email] }),
    phone: this.fb.control('', { nonNullable: true }),
    phone_country_code: this.fb.control('+55', { nonNullable: true }),
    street: this.fb.control('', { nonNullable: true }),
    number: this.fb.control('', { nonNullable: true }),
    complement: this.fb.control('', { nonNullable: true }),
    district: this.fb.control('', { nonNullable: true }),
    city: this.fb.control('', { nonNullable: true }),
    state: this.fb.control('', { nonNullable: true }),
    zip_code: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.pattern(/^\d{8}$|^\d{5}-\d{3}$/)],
    }),
    segment_id: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    plan_id: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    this.trackManualEdits();
    this.hasBasicValidData.set(this.form.valid);

    this.form.statusChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.hasBasicValidData.set(this.form.valid);
    });

    this.form.controls.document.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        const sanitized = digitsOnly(value);
        if (sanitized.length !== 14) return;
        if (!this.form.controls.document.dirty || this.lastAutoLookupCnpj() === sanitized) return;
        this.searchCnpj(true);
      });

    this.form.controls.zip_code.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        const cep = digitsOnly(value);
        if (cep.length === 8 && this.lastAutoLookupCep() !== cep) {
          this.lookupCep(cep);
        }
      });

    effect(() => {
      const item = this.tenant();
      if (!item) {
        this.resetForm();
        return;
      }

      if (this.lastLoadedId() === item.id) return;
      this.lastLoadedId.set(item.id);

      const normalizedPhone = this.splitPhone(item.phone);

      this.form.reset({
        name: item.name,
        document: item.document ?? '',
        email: item.email ?? '',
        phone: normalizedPhone.local,
        phone_country_code: normalizedPhone.countryCode,
        street: item.street ?? '',
        number: item.number ?? '',
        complement: item.complement ?? '',
        district: item.district ?? '',
        city: item.city ?? '',
        state: item.state ?? '',
        zip_code: item.zip ?? item.zip_code ?? item.zipcode ?? '',
        segment_id: item.segment_id ?? '',
        plan_id: item.plan_id ?? '',
        is_active: item.is_active,
      });

      this.form.controls.plan_id.disable();
      this.form.controls.segment_id.removeValidators(Validators.required);
      this.form.controls.segment_id.updateValueAndValidity();
      this.resetAutofillState();
      this.form.markAsPristine();
    });

    this.aiGovernanceService
      .listSegments()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.segmentOptions.set(
            response.data
              .filter((segment) => segment.is_active)
              .map((segment) => ({ value: segment.id, label: segment.name })),
          );
        },
        error: () => this.segmentOptions.set([]),
      });

    this.platformPlanService
      .list({ per_page: 100 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.planOptions.set(
            response.data
              .filter((plan) => plan.is_active)
              .map((plan) => ({ value: plan.id, label: plan.name })),
          );
        },
        error: () => this.planOptions.set([]),
      });
  }

  searchCnpj(isAutomatic = false): void {
    const document = digitsOnly(this.form.controls.document.value);
    if (document.length !== 14 || this.isSearchingCnpj()) return;

    this.lookupFeedback.set(null);
    this.isSearchingCnpj.set(true);
    this.lastAutoLookupCnpj.set(document);

    this.utilsService
      .lookupCnpj(document)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSearchingCnpj.set(false);
          const data = response.data.company_data;
          if (!data) return;

          this.fillFromCnpj(data);
          if (isAutomatic) {
            this.lookupFeedback.set('Dados do CNPJ preenchidos automaticamente.');
          }
        },
        error: () => {
          this.isSearchingCnpj.set(false);
        },
      });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();
    const editing = this.tenant();

    const payload = {
      name: formValue.name,
      document: digitsOnly(formValue.document) || undefined,
      email: formValue.email.trim() || undefined,
      phone:
        this.buildInternationalPhone(formValue.phone, formValue.phone_country_code) || undefined,
      street: formValue.street.trim() || undefined,
      number: formValue.number.trim() || undefined,
      complement: formValue.complement.trim() || undefined,
      district: formValue.district.trim() || undefined,
      city: formValue.city.trim() || undefined,
      state: normalizeUf(formValue.state) || undefined,
      zip: digitsOnly(formValue.zip_code) || undefined,
      zip_code: digitsOnly(formValue.zip_code) || undefined,
      segment_id: formValue.segment_id || undefined,
      plan_id: formValue.plan_id || undefined,
      address:
        buildAddressLine({
          street: formValue.street,
          number: formValue.number,
          complement: formValue.complement,
          district: formValue.district,
        }) ?? undefined,
      is_active: formValue.is_active,
    };

    this.isSaving.set(true);

    const request = editing
      ? this.companyService.update(editing.id, payload)
      : this.companyService.create(payload);

    request
      .pipe(
        finalize(() => this.isSaving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const segmentId = formValue.segment_id;
          if (segmentId) {
            this.aiGovernanceService
              .assignSegment(String(response.data.id), segmentId)
              .pipe(takeUntilDestroyed(this.destroyRef))
              .subscribe({
                next: () => {
                  this.saved.emit(response.data);
                },
                error: () => {
                  this.saved.emit(response.data);
                },
              });
            return;
          }

          this.saved.emit(response.data);
        },
        error: (error: { status?: number; error?: { message?: string; errors?: Record<string, string[]> } }) => {
          if (error.status === 422 && error.error?.errors) {
            applyServerErrors(this.form, error.error.errors);
            return;
          }
          this.toast.error(
            error.error?.message || 'Não foi possível salvar o inquilino. Tente novamente.',
          );
        },
      });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private lookupCep(cep: string): void {
    if (this.isSearchingCep()) return;

    this.lookupFeedback.set(null);
    this.isSearchingCep.set(true);
    this.lastAutoLookupCep.set(cep);

    this.utilsService
      .lookupCep(cep)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSearchingCep.set(false);
          const data = response.data.address_data;
          if (data) {
            this.fillFromCep(data);
          }
        },
        error: () => {
          this.isSearchingCep.set(false);
          this.lookupFeedback.set(
            'Não foi possível consultar CEP no momento. Continue o preenchimento manual.',
          );
        },
      });
  }

  private fillFromCnpj(data: CnpjData): void {
    this.patchIfNotManual('name', data.trade_name || data.legal_name || '');
    this.patchIfNotManual('street', data.street || '');
    this.patchIfNotManual('number', data.number || '');
    this.patchIfNotManual('complement', data.complement || '');
    this.patchIfNotManual('district', data.district || '');
    this.patchIfNotManual('city', data.city || '');
    this.patchIfNotManual('state', normalizeUf(data.state || ''));
    this.patchIfNotManual('zip_code', formatCepForForm(data.zip || ''));
    const normalizedPhone = this.splitPhone(data.phone ?? undefined);
    this.patchIfNotManual('phone', normalizedPhone.local);

    if (!this.manualEditedFields()['phone']) {
      this.form.controls.phone_country_code.setValue(normalizedPhone.countryCode, {
        emitEvent: false,
      });
    }

    if (!this.form.controls.email.value && data.email) {
      this.form.controls.email.setValue(data.email, { emitEvent: false });
    }
  }

  private fillFromCep(data: AddressData): void {
    this.patchIfNotManual('street', data.street || '');
    this.patchIfNotManual('complement', data.complement || '');
    this.patchIfNotManual('district', data.district || '');
    this.patchIfNotManual('city', data.city || '');
    this.patchIfNotManual('state', normalizeUf(data.state || ''));
  }

  private patchIfNotManual(field: keyof typeof this.form.controls, value: string): void {
    if (!value || this.manualEditedFields()[field as string]) return;

    this.isAutoPatching = true;
    this.form.controls[field].setValue(value as never, { emitEvent: true });
    this.isAutoPatching = false;
  }

  private trackManualEdits(): void {
    const fields: (keyof typeof this.form.controls)[] = [
      'name',
      'phone',
      'street',
      'number',
      'complement',
      'district',
      'city',
      'state',
      'zip_code',
    ];

    this.form.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      if (this.isAutoPatching) return;

      this.manualEditedFields.update((current) => {
        const nextState = { ...current };
        for (const field of fields) {
          if (this.form.controls[field].dirty) {
            nextState[field as string] = true;
          }
        }
        return nextState;
      });
    });
  }

  private resetForm(): void {
    this.lastLoadedId.set(null);
    this.form.reset({
      name: '',
      document: '',
      email: '',
      phone: '',
      phone_country_code: '+55',
      street: '',
      number: '',
      complement: '',
      district: '',
      city: '',
      state: '',
      zip_code: '',
      segment_id: '',
      plan_id: '',
      is_active: true,
    });

    this.form.controls.plan_id.enable();
    this.form.controls.segment_id.setValidators([Validators.required]);
    this.form.controls.segment_id.updateValueAndValidity();
    this.resetAutofillState();
    this.form.markAsPristine();
  }

  private resetAutofillState(): void {
    this.manualEditedFields.set({
      name: false,
      phone: false,
      street: false,
      number: false,
      complement: false,
      district: false,
      city: false,
      state: false,
      zip_code: false,
    });
    this.lastAutoLookupCnpj.set(null);
    this.lastAutoLookupCep.set(null);
    this.lookupFeedback.set(null);
  }

  private splitPhone(value: string | undefined): { countryCode: string; local: string } {
    if (value === undefined || value === null || value.trim() === '') {
      return { countryCode: '+55', local: '' };
    }

    const digits = value.replace(/\D/g, '');
    const countryCodes = [
      { code: '+351', digits: '351' },
      { code: '+55', digits: '55' },
      { code: '+57', digits: '57' },
      { code: '+56', digits: '56' },
      { code: '+54', digits: '54' },
      { code: '+52', digits: '52' },
      { code: '+49', digits: '49' },
      { code: '+44', digits: '44' },
      { code: '+34', digits: '34' },
      { code: '+1', digits: '1' },
    ];

    for (const country of countryCodes) {
      if (!digits.startsWith(country.digits)) continue;

      const local = digits.slice(country.digits.length);
      if (local.length >= 8 && local.length <= 11) {
        return { countryCode: country.code, local };
      }
    }

    return { countryCode: '+55', local: digits.slice(0, 11) };
  }

  private buildInternationalPhone(phone: string, countryCode: string): string {
    const localDigits = phone.replace(/\D/g, '');
    if (!localDigits) return '';

    const dialDigits = countryCode.replace(/\D/g, '') || '55';
    return `+${dialDigits}${localDigits}`;
  }
}
