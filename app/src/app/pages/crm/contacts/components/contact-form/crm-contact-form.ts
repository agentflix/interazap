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
import {
  type AbstractControl,
  type FormArray,
  type FormControl,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfDocumentInputComponent,
  AfIconButtonComponent,
  AfPhoneInputComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { type Contact, ContactService } from '@core/services/crm-contact.service';
import { type CRMCompany, CRMCompanyService } from '@core/services/crm-company.service';
import { UtilsService } from '@core/services/utils.service';

/**
 * Contact form component for creating and editing CRM contacts.
 * Follows Angular 20+ best practices with Signals and OnPush.
 */
@Component({
  selector: 'app-contact-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfTextInputComponent,
    AfPhoneInputComponent,
    AfDocumentInputComponent,
    AfSelectInputComponent,
    AfTextareaInputComponent,
    AfSwitchInputComponent,
    AfAlertComponent,
    AfButtonComponent,
    AfIconButtonComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-contact-form.html',
})
export class ContactFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly contactService = inject(ContactService);
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly utils = inject(UtilsService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  /** Contact to edit — null for create mode */
  readonly contact = input<Contact | null>(null);

  /** Emitted after a successful save */
  readonly saved = output<Contact>();

  /** Emitted when user cancels */
  readonly cancelled = output<void>();

  /** Save loading state — accessed by parent via viewChild */
  readonly isSaving = signal(false);

  /** Inline error message */
  readonly errorMessage = signal<string | null>(null);

  /** Companies list for select */
  readonly crmCompanies = signal<CRMCompany[]>([]);

  /** Main contact form group */
  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    phone: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    phone_country_code: this.fb.control('+55', { nonNullable: true }),
    email: this.fb.control('', { nonNullable: true, validators: [Validators.email] }),
    document: this.fb.control<string | null>(null),
    crm_company_id: this.fb.control('', { nonNullable: true }),
    notes: this.fb.control('', { nonNullable: true, validators: [Validators.maxLength(500)] }),
    is_active: this.fb.control(true, { nonNullable: true }),
    custom_fields: this.fb.array([]),
  });

  /** Getter for custom fields FormArray */
  get customFields(): FormArray {
    return this.form.controls.custom_fields;
  }

  /** Options for the company select input */
  readonly companyOptions = signal<AfSelectOption[]>([]);

  constructor() {
    effect(() => {
      const item = this.contact();
      if (item) {
        if (this.lastLoadedId() === String(item.id)) return;
        this.lastLoadedId.set(String(item.id));
        this.loadContact(item);
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
      }
    });

    this.loadCRMCompanies();
  }

  /**
   * Get a typed control from a form group in the FormArray.
   * @param control - Form control (expected to be a FormGroup).
   * @param field - Field name within the group.
   * @returns The requested FormControl.
   */
  getGroupControl(control: AbstractControl, field: string): FormControl<string> {
    const group = control as unknown as { controls: Record<string, FormControl<string>> };
    return group.controls[field];
  }

  /**
   * Submit the form — validates, builds payload, calls API.
   * Handles both create and update scenarios.
   */
  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();
    const payload: Record<string, unknown> = {
      ...formValue,
      phone:
        this.buildInternationalPhone(formValue.phone, formValue.phone_country_code) || undefined,
      document: formValue.document || undefined,
      custom_fields: this.serializeCustomFields(),
    };
    delete payload['phone_country_code'];

    const editing = this.contact();
    this.isSaving.set(true);
    this.errorMessage.set(null);

    const request = editing
      ? this.contactService.update(String(editing.id), payload)
      : this.contactService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data.contact);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar o contato. Tente novamente.');
      },
    });
  }

  /**
   * Cancels the form operation and emits cancelled event.
   */
  cancel(): void {
    this.cancelled.emit();
  }

  /**
   * Adds a new custom field to the FormArray.
   * @param key - Optional initial key name.
   * @param value - Optional initial field value.
   */
  addCustomField(key = '', value = ''): void {
    this.customFields.push(
      this.fb.group({
        key: [key, Validators.required],
        value: [value, Validators.required],
      }),
    );
  }

  /**
   * Removes a custom field from the FormArray by index.
   * @param index - The index of the field to remove.
   */
  removeCustomField(index: number): void {
    this.customFields.removeAt(index);
  }

  private loadCRMCompanies(): void {
    this.crmCompanyService
      .list({ per_page: 100, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.crmCompanies.set(response.data);
          this.companyOptions.set([
            { label: 'Selecione uma empresa', value: '' },
            ...response.data.map((c) => ({ label: c.name, value: String(c.id) })),
          ]);
        },
        error: () => this.crmCompanies.set([]),
      });
  }

  private loadContact(contact: Contact): void {
    const initialPhone = this.splitPhone(contact.phone);

    // Reset form to empty state first, then patch values to ensure clean state
    this.form.reset();

    this.form.patchValue({
      name: contact.name,
      phone: initialPhone.local,
      phone_country_code: initialPhone.countryCode,
      email: contact.email ?? '',
      document: this.normalizeDocumentForInput(contact.document),
      crm_company_id: this.normalizeCompanyId(contact),
      notes: contact.notes ?? '',
      is_active: contact.is_active,
    });

    this.customFields.clear();

    // Load full contact details
    this.contactService
      .find(String(contact.id))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const fullContact = response.data.contact;
          const normalizedPhone = this.splitPhone(fullContact.phone ?? contact.phone);

          this.form.patchValue({
            name: fullContact.name,
            email: fullContact.email ?? '',
            document: this.normalizeDocumentForInput(fullContact.document ?? contact.document),
            crm_company_id: this.normalizeCompanyId(fullContact),
            notes: fullContact.notes ?? '',
            is_active: fullContact.is_active,
            phone: normalizedPhone.local,
            phone_country_code: normalizedPhone.countryCode,
          });

          this.customFields.clear();
          const normalizedCustomFields = this.normalizeCustomFields(fullContact.custom_fields);
          Object.entries(normalizedCustomFields).forEach(([key, value]) => {
            this.addCustomField(key, value);
          });
        },
      });
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      phone: '',
      phone_country_code: '+55',
      email: '',
      document: null,
      crm_company_id: '',
      notes: '',
      is_active: true,
    });
    this.customFields.clear();
    this.errorMessage.set(null);
  }

  private serializeCustomFields(): Record<string, unknown> {
    const fields = this.customFields.getRawValue();
    const result: Record<string, unknown> = {};
    fields.forEach((field: { key: string; value: unknown }) => {
      if (field.key) result[field.key] = field.value;
    });
    return result;
  }

  private normalizeCompanyId(contact: Contact): string {
    if (contact.crm_company_id) return String(contact.crm_company_id);
    if (contact.company?.id) return String(contact.company.id);
    return '';
  }

  private normalizeCustomFields(value: unknown): Record<string, string> {
    if (!value) return {};

    if (Array.isArray(value)) {
      const result: Record<string, string> = {};
      for (const entry of value) {
        if (!entry || typeof entry !== 'object') continue;
        const record = entry as { field?: { name?: string }; value?: unknown; key?: unknown };
        const key =
          typeof record.key === 'string'
            ? record.key
            : typeof record.field?.name === 'string'
              ? record.field.name
              : '';
        if (!key) continue;
        result[key] =
          record.value === null || record.value === undefined ? '' : String(record.value);
      }
      return result;
    }

    if (typeof value === 'object') {
      const result: Record<string, string> = {};
      Object.entries(value as Record<string, unknown>).forEach(([key, fieldValue]) => {
        result[key] = fieldValue === null || fieldValue === undefined ? '' : String(fieldValue);
      });
      return result;
    }

    return {};
  }

  private splitPhone(value: string | undefined): { countryCode: string; local: string } {
    if (!value) return { countryCode: '+55', local: '' };

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

  private normalizeDocumentForInput(value: string | undefined): string | null {
    if (!value) return null;

    const digits = value.replace(/\D/g, '');
    if (digits.length === 11 || digits.length === 14) return digits;

    return value;
  }
}
