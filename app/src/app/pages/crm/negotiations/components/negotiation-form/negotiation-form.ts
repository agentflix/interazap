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
import { catchError, distinctUntilChanged, of, switchMap, tap } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  type SelectOption,
  SelectInputComponent,
  TextInputComponent,
  TextareaInputComponent,
} from '@shared/components/inputs';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';
import { ModalComponent } from '@shared/components/modal/modal';
import {
  type Negotiation,
  type NegotiationPayload,
  type NegotiationStatus,
  NegotiationService,
} from 'src/app/core/services/negotiation.service';
import { type Funnel, type FunnelStep, FunnelService } from 'src/app/core/services/funnel.service';
import { type Contact } from 'src/app/core/models/contact.model';
import { type CRMCompany } from 'src/app/core/services/crm-company.service';
import { type User } from '@core/models/user.model';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { ContactService } from 'src/app/core/services/contact.service';

@Component({
  selector: 'app-negotiation-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    SelectInputComponent,
    TextInputComponent,
    TextareaInputComponent,
    ButtonComponent,
    LoadingButtonComponent,
    ModalComponent,
/**
 * Negotiation form component for the Crm module.
 * @selector app-negotiation-form
 */
  ],
  templateUrl: './negotiation-form.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly negotiationService = inject(NegotiationService);
  private readonly funnelService = inject(FunnelService);
  private readonly authStore = inject(AuthStoreService);
  private readonly contactService = inject(ContactService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | number | null>(null);

  readonly negotiation = input<Negotiation | null>(null);
  readonly funnels = input<Funnel[]>([]);
  readonly contacts = input<Contact[]>([]);
  readonly companies = input<CRMCompany[]>([]);
  readonly users = input<User[]>([]);
  readonly initialFunnelId = input<string | number | null>(null);

  readonly saved = output<Negotiation>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly steps = signal<FunnelStep[]>([]);
  readonly selectedCompanyId = signal<string | number | null>(null);
  readonly createdContacts = signal<Contact[]>([]);
  readonly companyContacts = signal<Contact[]>([]);
  readonly isCreateContactModalOpen = signal(false);
  readonly isCreatingContact = signal(false);
  readonly createContactError = signal<string | null>(null);
  readonly currentUserId = computed(() => this.authStore.user()?.id ?? null);
  readonly canCreateContact = computed(() => Boolean(this.selectedCompanyId()));

  readonly allContacts = computed<Contact[]>(() => {
    const baseContacts = this.contacts();
    const created = this.createdContacts();

    if (created.length === 0) {
      return baseContacts;
    }

    const byId = new Map<string, Contact>();
    for (const item of [...baseContacts, ...created]) {
      byId.set(String(item.id), item);
    }

    return Array.from(byId.values());
  });

  readonly filteredContacts = computed(() => {
    const companyId = this.selectedCompanyId();
    if (!companyId) return [];

    return this.companyContacts().filter(
      (contact) => String(this.resolveContactCompanyId(contact) ?? '') === String(companyId),
    );
  });

  readonly contactOptions = computed<SelectOption[]>(() =>
    this.filteredContacts().map((contact) => ({ value: contact.id, label: contact.name })),
  );

  readonly companyOptions = computed<SelectOption[]>(() =>
    this.companies().map((company) => ({ value: company.id, label: company.name })),
  );

  readonly userOptions = computed<SelectOption[]>(() =>
    this.users().map((user) => ({ value: user.id, label: user.name })),
  );

  readonly funnelOptions = computed<SelectOption[]>(() =>
    this.funnels().map((funnel) => ({ value: funnel.id, label: funnel.name })),
  );

  readonly stepOptions = computed<SelectOption[]>(() =>
    this.steps().map((step) => ({ value: step.id, label: step.name })),
  );

  readonly formStatusOptions: SelectOption[] = [
    { label: 'Aberta', value: 'open' },
    { label: 'Ganha', value: 'won' },
    { label: 'Perdida', value: 'lost' },
  ];

  readonly form = this.fb.group({
    title: this.fb.control('', Validators.required),
    contact_id: this.fb.control<string | number | null>(null, Validators.required),
    user_id: this.fb.control<string | number | null>(null, Validators.required),
    crm_company_id: this.fb.control<string | number | null>(null, Validators.required),
    funnel_id: this.fb.control<string | number | null>(null, Validators.required),
    step_id: this.fb.control<string | number | null>(null, Validators.required),
    expected_close_date: this.fb.control(''),
    status: this.fb.control<NegotiationStatus>('open'),
    notes: this.fb.control(''),
  });

  readonly createContactForm = this.fb.group({
    name: this.fb.control('', Validators.required),
    phone: this.fb.control('', Validators.required),
    email: this.fb.control('', Validators.email),
  });

  constructor() {
    effect(() => {
      const item = this.negotiation();
      if (item) {
        if (this.lastLoadedId() === item.id) {
          return;
        }
        this.lastLoadedId.set(item.id);
        this.form.reset({
          title: item.title,
          contact_id: item.contact_id ?? null,
          user_id: item.user_id ?? item.auth_user_id ?? null,
          crm_company_id: item.crm_company_id ?? null,
          funnel_id: item.funnel_id ?? null,
          step_id: item.step_id ?? null,
          expected_close_date: item.expected_close_date ?? '',
          status: item.status ?? 'open',
          notes: item.notes ?? '',
        });
        this.selectedCompanyId.set(item.crm_company_id ?? null);
        this.setContactFieldState(item.crm_company_id ?? null);

        if (item.funnel_id) {
          this.loadStepsForFunnel(item.funnel_id, item.step_id ?? null);
        }
        return;
      }

      this.lastLoadedId.set(null);
      this.resetForm();
    });

    this.form.controls.funnel_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((funnelId) => {
        if (funnelId) {
          this.loadStepsForFunnel(this.normalizeId(funnelId)!, null);
        } else {
          this.steps.set([]);
          this.form.controls.step_id.setValue(null);
        }
      });

    this.form.controls.crm_company_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .pipe(
        tap((companyId) => {
          const normalizedCompanyId = this.normalizeId(companyId ?? null);
          this.selectedCompanyId.set(normalizedCompanyId);
          this.setContactFieldState(normalizedCompanyId);

          if (!normalizedCompanyId) {
            this.companyContacts.set([]);
            this.form.controls.contact_id.setValue(null);
          }
        }),
        distinctUntilChanged(),
        switchMap((companyId) => {
          const normalizedCompanyId = this.normalizeId(companyId ?? null);
          if (!normalizedCompanyId) {
            return of([]);
          }

          return this.contactService
            .list({
              crm_company_id: String(normalizedCompanyId),
              per_page: 100,
              is_active: true,
            })
            .pipe(
              catchError(() => of({ data: [] })),
              switchMap((response) => of(response.data ?? [])),
            );
        }),
      )
      .subscribe((contacts) => {
        this.companyContacts.set(contacts);
        const currentContactId = this.normalizeId(this.form.controls.contact_id.value);
        if (
          currentContactId &&
          !contacts.some((contact) => String(contact.id) === String(currentContactId))
        ) {
          this.form.controls.contact_id.setValue(null);
        }

        if (this.selectedCompanyId() && contacts.length === 0) {
          this.openCreateContactModal();
        }
      });

    this.form.controls.contact_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((contactId) => {
        const normalizedContactId = this.normalizeId(contactId ?? null);
        if (!normalizedContactId) {
          return;
        }

        const contact =
          this.companyContacts().find((item) => String(item.id) === String(normalizedContactId)) ??
          this.allContacts().find((item) => String(item.id) === String(normalizedContactId));
        const companyId = this.selectedCompanyId();
        if (!companyId || !contact) {
          this.form.controls.contact_id.setValue(null, { emitEvent: false });
          return;
        }

        const linkedCompanyId = this.resolveContactCompanyId(contact);
        if (!linkedCompanyId || String(linkedCompanyId) !== String(companyId)) {
          this.form.controls.contact_id.setValue(null, { emitEvent: false });
        }
      });
  }

  openCreateContactModal(): void {
    if (!this.selectedCompanyId()) {
      return;
    }

    this.createContactError.set(null);
    this.createContactForm.reset({
      name: '',
      phone: '',
      email: '',
    });
    this.isCreateContactModalOpen.set(true);
  }

  closeCreateContactModal(): void {
    this.isCreateContactModalOpen.set(false);
    this.isCreatingContact.set(false);
    this.createContactError.set(null);
  }

  saveNewContact(): void {
    if (this.createContactForm.invalid || this.isCreatingContact()) {
      this.createContactForm.markAllAsTouched();
      return;
    }

    const companyId = this.selectedCompanyId();
    if (!companyId) {
      this.createContactError.set('Selecione uma empresa antes de cadastrar um contato.');
      return;
    }

    const value = this.createContactForm.getRawValue();
    const payload: Partial<Contact> = {
      name: value.name?.trim() ?? '',
      phone: value.phone?.trim() ?? '',
      email: value.email?.trim() || undefined,
      crm_company_id: String(companyId),
      is_active: true,
    };

    this.isCreatingContact.set(true);
    this.createContactError.set(null);

    this.contactService
      .create(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const createdContact = response.data;
          this.createdContacts.update((items) => {
            const next = items.filter((item) => String(item.id) !== String(createdContact.id));
            next.push(createdContact);
            return next;
          });
          this.form.controls.contact_id.setValue(createdContact.id);
          this.isCreatingContact.set(false);
          this.closeCreateContactModal();
        },
        error: () => {
          this.isCreatingContact.set(false);
          this.createContactError.set('Não foi possível cadastrar o contato.');
        },
      });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();
    const payload: NegotiationPayload = {
      title: formValue.title ?? '',
      contact_id: formValue.contact_id!,
      user_id: formValue.user_id!,
      crm_company_id: formValue.crm_company_id!,
      funnel_id: formValue.funnel_id!,
      step_id: formValue.step_id!,
      expected_close_date: formValue.expected_close_date || undefined,
      status: formValue.status || 'open',
      notes: formValue.notes || undefined,
    };

    const editing = this.negotiation();
    this.isSaving.set(true);

    const request = editing
      ? this.negotiationService.update(editing.id, payload)
      : this.negotiationService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data.negotiation);
      },
      error: () => {
        this.isSaving.set(false);
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private loadStepsForFunnel(
    funnelId: string | number,
    selectedStepId: string | number | null = null,
  ): void {
    this.funnelService
      .listSteps(this.normalizeId(funnelId)!)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const steps = response.data?.steps ?? [];
          this.steps.set(steps);
          if (selectedStepId) {
            this.form.controls.step_id.setValue(selectedStepId);
          } else if (steps.length) {
            this.form.controls.step_id.setValue(steps[0].id);
          }
        },
      });
  }

  private resetForm(): void {
    const initialFunnelId = this.normalizeId(this.initialFunnelId());
    this.form.reset({
      title: '',
      contact_id: null,
      user_id: this.currentUserId(),
      crm_company_id: null,
      funnel_id: initialFunnelId,
      step_id: null,
      expected_close_date: '',
      status: 'open',
      notes: '',
    });
    this.selectedCompanyId.set(null);
    this.companyContacts.set([]);
    this.setContactFieldState(null);

    if (initialFunnelId) {
      this.loadStepsForFunnel(initialFunnelId);
    } else {
      this.steps.set([]);
    }
  }

  private normalizeId(value: string | number | null): string | number | null {
    if (value === null || value === '') return null;
    if (typeof value === 'string') {
      const numeric = Number(value);
      return Number.isNaN(numeric) ? value : numeric;
    }
    return value;
  }

  private resolveContactCompanyId(contact?: Contact): string | number | null {
    if (!contact) {
      return null;
    }

    return contact.crm_company_id ?? contact.company?.id ?? null;
  }

  private setContactFieldState(companyId: string | number | null): void {
    const contactControl = this.form.controls.contact_id;

    if (!companyId) {
      contactControl.setValue(null, { emitEvent: false });
      contactControl.disable();
      return;
    }

    contactControl.enable();
  }
}
