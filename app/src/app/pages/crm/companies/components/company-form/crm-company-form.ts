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
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfDocumentInputComponent,
  AfMaskedInputComponent,
  AfPhoneInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  type CRMCompany,
  type CRMCompanyPayload,
  CRMCompanyService,
} from '@core/services/crm-company.service';
import { type CnpjData, type AddressData, UtilsService } from '@core/services/utils.service';

/**
 * Company form component — create/edit CRM companies.
 * CNPJ lookup + CEP auto-fill preserved from source.
 */
@Component({
  selector: 'app-company-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfAlertComponent,
    AfButtonComponent,
    AfTextInputComponent,
    AfDocumentInputComponent,
    AfMaskedInputComponent,
    AfPhoneInputComponent,
    AfSwitchInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-company-form.html',
})
export class CompanyFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly utilsService = inject(UtilsService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly company = input<CRMCompany | null>(null);
  readonly saved = output<CRMCompany>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);
  readonly isSearchingCnpj = signal(false);
  readonly isSearchingCep = signal(false);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    document: this.fb.control<string | null>(''),
    phone: this.fb.control('', { nonNullable: true }),
    phone_country_code: this.fb.control('+55', { nonNullable: true }),
    address: this.fb.control('', { nonNullable: true }),
    city: this.fb.control('', { nonNullable: true }),
    state: this.fb.control('', { nonNullable: true }),
    zip_code: this.fb.control('', { nonNullable: true }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    // Auto CEP lookup on value change (8+ digits)
    this.form.controls.zip_code.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((val) => {
        const clean = (val ?? '').replace(/\D/g, '');
        if (clean.length === 8) this.onCepBlur();
      });

    effect(() => {
      const item = this.company();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        const splitPhone = this.splitPhone(item.phone);
        this.form.reset({
          name: item.name,
          document: item.document || '',
          phone: splitPhone.local,
          phone_country_code: splitPhone.countryCode,
          address: item.address || '',
          city: item.city || '',
          state: item.state || '',
          zip_code: this.formatCepForInput(item.zip_code),
          is_active: item.is_active,
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
    const payload: CRMCompanyPayload = {
      name: fv.name,
      document: fv.document || undefined,
      phone: this.buildInternationalPhone(fv.phone, fv.phone_country_code) || undefined,
      address: fv.address || undefined,
      city: fv.city || undefined,
      state: fv.state || undefined,
      zip_code: fv.zip_code || undefined,
      is_active: fv.is_active,
    };

    const editing = this.company();
    const request$ = editing
      ? this.crmCompanyService.update(editing.id, payload)
      : this.crmCompanyService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar a empresa. Tente novamente.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  searchCnpj(): void {
    const doc = this.form.controls.document.value;
    if (!doc || doc.replace(/\D/g, '').length < 14) return;

    this.isSearchingCnpj.set(true);
    this.utilsService
      .lookupCnpj(doc)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.fillFromCnpj(response.data.company_data);
          this.isSearchingCnpj.set(false);
        },
        error: () => this.isSearchingCnpj.set(false),
      });
  }

  private onCepBlur(): void {
    const cep = this.form.controls.zip_code.value;
    if (!cep || cep.replace(/\D/g, '').length < 8) return;

    this.isSearchingCep.set(true);
    this.utilsService
      .lookupCep(cep)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.fillFromCep(response.data.address_data);
          this.isSearchingCep.set(false);
        },
        error: () => this.isSearchingCep.set(false),
      });
  }

  private fillFromCnpj(data: CnpjData): void {
    if (data.trade_name || data.legal_name) {
      this.form.patchValue({ name: (data.trade_name || data.legal_name) ?? '' });
    }
    if (data.phone) {
      let phone = data.phone.replace(/\D/g, '');
      // Normaliza formato antigo BR com 0 no DDI/DDD (ex: (055)3322-7091 → 5533227091)
      if (phone.startsWith('0') && phone.length >= 10) {
        phone = phone.slice(1);
      }
      this.form.patchValue({ phone });
    }
    if (data.street) {
      const address = [data.street, data.number, data.complement, data.district]
        .filter(Boolean)
        .join(', ');
      this.form.patchValue({ address });
    }
    if (data.city) this.form.patchValue({ city: data.city });
    if (data.state) this.form.patchValue({ state: data.state });
    if (data.zip) this.form.patchValue({ zip_code: this.formatCepForInput(data.zip) });
  }

  private fillFromCep(data: AddressData): void {
    if (data.street) {
      const address = [data.street, data.complement, data.district].filter(Boolean).join(', ');
      this.form.patchValue({ address });
    }
    if (data.city) this.form.patchValue({ city: data.city });
    if (data.state) this.form.patchValue({ state: data.state });
  }

  private resetForm(): void {
    this.form.reset({ is_active: true, phone_country_code: '+55' });
    this.errorMessage.set(null);
  }

  private splitPhone(value: string | undefined): { countryCode: string; local: string } {
    if (!value) return { countryCode: '+55', local: '' };
    const digits = value.replace(/\D/g, '');
    const countries = [
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
    for (const c of countries) {
      if (!digits.startsWith(c.digits)) continue;
      const local = digits.slice(c.digits.length);
      if (local.length >= 8 && local.length <= 11) return { countryCode: c.code, local };
    }
    return { countryCode: '+55', local: digits.slice(0, 11) };
  }

  private buildInternationalPhone(phone: string, countryCode: string): string {
    const local = phone.replace(/\D/g, '');
    if (!local) return '';
    return `+${countryCode.replace(/\D/g, '') || '55'}${local}`;
  }

  private formatCepForInput(value: string | undefined): string {
    if (!value) return '';

    const digits = value.replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 5) return digits;

    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
  }
}
