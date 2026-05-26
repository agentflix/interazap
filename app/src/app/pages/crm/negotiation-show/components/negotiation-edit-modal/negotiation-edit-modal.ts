import {
  type OnChanges,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ReactiveFormsModule, Validators, NonNullableFormBuilder } from '@angular/forms';
import { finalize } from 'rxjs/operators';
import { type CRMCompany } from 'src/app/core/services/crm-company.service';
import { type Contact } from 'src/app/core/models/contact.model';
import { type Funnel, type FunnelStep, FunnelService } from 'src/app/core/services/funnel.service';
import { type Negotiation, NegotiationService } from 'src/app/core/services/negotiation.service';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';
import { ModalComponent } from '@shared/components/modal/modal';
import {
  type SelectOption,
  SelectInputComponent,
  TextInputComponent,
} from '@shared/components/inputs';
import { isSameId, normalizeId } from '../../negotiation-show.utils';

/**
 * Modal de edição completa dos detalhes da negociação.
 */
@Component({
  selector: 'app-negotiation-edit-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    ModalComponent,
    ButtonComponent,
    LoadingButtonComponent,
    TextInputComponent,
    SelectInputComponent,
  ],
  templateUrl: './negotiation-edit-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationEditModalComponent implements OnChanges {
  private readonly negotiationService = inject(NegotiationService);
  private readonly funnelService = inject(FunnelService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly isOpen = input(false);
  readonly negotiation = input<Negotiation | null>(null);
  readonly funnels = input<Funnel[]>([]);
  readonly contacts = input<Contact[]>([]);
  readonly companies = input<CRMCompany[]>([]);

  readonly saved = output<Negotiation>();
  readonly closed = output<void>();

  readonly editSteps = signal<FunnelStep[]>([]);
  readonly editModalError = signal<string | null>(null);
  readonly isSavingDetails = signal(false);
  readonly selectedCompanyId = signal<string | number | null>(null);

  readonly filteredContacts = computed(() => {
    const companyId = this.selectedCompanyId();
    const allContacts = this.contacts();
    if (!companyId) return allContacts;
    return allContacts.filter((contact) => String(contact.crm_company_id) === String(companyId));
  });

  readonly editStepOptions = computed<SelectOption[]>(() =>
    this.editSteps().map((s) => ({ label: s.name, value: s.id })),
  );
  readonly editContactOptions = computed<SelectOption[]>(() =>
    this.filteredContacts().map((contact) => ({ label: contact.name, value: String(contact.id) })),
  );
  readonly editCompanyOptions = computed<SelectOption[]>(() =>
    this.companies().map((company) => ({ label: company.name, value: String(company.id) })),
  );
  readonly editFunnelOptions = computed<SelectOption[]>(() =>
    this.funnels().map((funnel) => ({ label: funnel.name, value: String(funnel.id) })),
  );

  readonly editForm = this.fb.group({
    title: this.fb.control('', Validators.required),
    contact_id: this.fb.control('', Validators.required),
    crm_company_id: this.fb.control('', Validators.required),
    funnel_id: this.fb.control('', Validators.required),
    step_id: this.fb.control('', Validators.required),
    expected_close_date: this.fb.control(''),
  });

  constructor() {
    this.editForm.controls.funnel_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        if (value !== null && value !== undefined) {
          this.onEditFunnelValueChange(value);
        }
      });

    this.editForm.controls.crm_company_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        if (value !== null && value !== undefined) {
          this.onEditCompanyValueChange(value);
        }
      });

    this.editForm.controls.contact_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        if (value !== null && value !== undefined) {
          this.onEditContactValueChange(value);
        }
      });
  }

  ngOnChanges(): void {
    const current = this.negotiation();
    const open = this.isOpen();
    if (!open || !current) return;

    this.editModalError.set(null);
    this.editSteps.set([]);
    this.editForm.reset({
      title: current.title ?? '',
      contact_id: current.contact_id ? String(current.contact_id) : '',
      crm_company_id: current.crm_company_id ? String(current.crm_company_id) : '',
      funnel_id: current.funnel_id ? String(current.funnel_id) : '',
      step_id: current.step_id ? String(current.step_id) : '',
      expected_close_date: current.expected_close_date
        ? this.formatDateInput(current.expected_close_date)
        : '',
    });
    this.selectedCompanyId.set(current.crm_company_id ?? null);

    if (current.funnel_id) {
      this.loadEditSteps(current.funnel_id);
    }
  }

  close(): void {
    this.closed.emit();
  }

  submitEdit(): void {
    const current = this.negotiation();
    if (!current || this.editForm.invalid || this.isSavingDetails()) {
      this.editForm.markAllAsTouched();
      return;
    }

    const value = this.editForm.getRawValue();
    this.isSavingDetails.set(true);
    this.editModalError.set(null);

    this.negotiationService
      .update(current.id, {
        title: value.title,
        contact_id: normalizeId(value.contact_id)!,
        crm_company_id: normalizeId(value.crm_company_id)!,
        funnel_id: normalizeId(value.funnel_id)!,
        step_id: normalizeId(value.step_id)!,
        expected_close_date: value.expected_close_date || undefined,
        notes: current.notes ?? undefined,
        status: current.status,
      })
      .pipe(
        finalize(() => this.isSavingDetails.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const payload = response.data as Negotiation | { negotiation: Negotiation };
          const updated = 'negotiation' in payload ? payload.negotiation : payload;
          this.saved.emit({ ...current, ...updated });
        },
        error: () => {
          this.editModalError.set('Não foi possível atualizar a negociação.');
        },
      });
  }

  onEditFunnelChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (!target) return;
    this.onEditFunnelValueChange(target.value);
  }

  onEditFunnelValueChange(value: string): void {
    const funnelId = normalizeId(value);
    if (!funnelId) {
      this.editSteps.set([]);
      this.editForm.controls.step_id.setValue('');
      return;
    }

    this.loadEditSteps(funnelId);
  }

  onEditCompanyChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (!target) return;
    this.onEditCompanyValueChange(target.value);
  }

  onEditCompanyValueChange(value: string): void {
    const companyId = normalizeId(value);
    this.selectedCompanyId.set(companyId);

    if (!companyId) {
      this.editForm.controls.contact_id.setValue('');
      return;
    }

    const contacts = this.filteredContacts();
    if (contacts.length === 1) {
      const singleContactId = String(contacts[0].id);
      if (this.editForm.controls.contact_id.value !== singleContactId) {
        this.editForm.controls.contact_id.setValue(singleContactId);
      }
      return;
    }

    const currentContactId = normalizeId(this.editForm.controls.contact_id.value);
    if (currentContactId && !contacts.some((contact) => isSameId(contact.id, currentContactId))) {
      this.editForm.controls.contact_id.setValue('');
    }
  }

  onEditContactChange(event: Event): void {
    const target = event.target as HTMLSelectElement | null;
    if (!target) return;
    this.onEditContactValueChange(target.value);
  }

  onEditContactValueChange(value: string): void {
    const contactId = normalizeId(value);
    if (!contactId) return;

    const contact = this.contacts().find((item) => isSameId(item.id, contactId));
    if (contact?.crm_company_id) {
      this.selectedCompanyId.set(contact.crm_company_id);
      const companyId = String(contact.crm_company_id);
      if (this.editForm.controls.crm_company_id.value !== companyId) {
        this.editForm.controls.crm_company_id.setValue(companyId);
      }
    }
  }

  private loadEditSteps(funnelId: string | number): void {
    this.funnelService
      .listSteps(funnelId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const steps = response.data.steps ?? [];
          this.editSteps.set(steps);
          if (!steps.some((step) => isSameId(step.id, this.editForm.controls.step_id.value))) {
            this.editForm.controls.step_id.setValue('');
          }
        },
        error: () => {
          this.editSteps.set([]);
        },
      });
  }

  private formatDateInput(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toISOString().split('T')[0];
  }
}
