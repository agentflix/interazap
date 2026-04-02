import {
  type OnInit,
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
import { LucideAngularModule } from 'lucide-angular';
import { toast } from 'ngx-sonner';
import { type Contact } from 'src/app/core/models/contact.model';
import {
  type NegotiationContactLink,
  NegotiationContactService,
} from 'src/app/core/services/negotiation-contact.service';
import {
  ButtonComponent,
  IconButtonComponent,
  LoadingButtonComponent,
} from '@shared/components/buttons';
import { ConfirmModalComponent } from '@shared/components/confirm-modal/confirm-modal';
import { ModalComponent } from '@shared/components/modal/modal';
import {
  type SelectOption,
  SelectInputComponent,
  SwitchInputComponent,
  TextareaInputComponent,
} from '@shared/components/inputs';

/**
 * Tab content responsible for negotiation contacts links CRUD.
 */
@Component({
  selector: 'app-negotiation-contacts-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
    ModalComponent,
    ConfirmModalComponent,
    SelectInputComponent,
    SwitchInputComponent,
    TextareaInputComponent,
  ],
  templateUrl: './negotiation-contacts-tab.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationContactsTabComponent implements OnInit {
  private readonly contactService = inject(NegotiationContactService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly negotiationId = input.required<string | number>();
  readonly availableContacts = input<Contact[]>([]);
  readonly contactsChanged = output<void>();
  readonly failed = output<string>();

  readonly contactLinks = signal<NegotiationContactLink[]>([]);
  readonly isContactsLoading = signal(false);
  readonly isContactModalOpen = signal(false);
  readonly isContactSaving = signal(false);
  readonly contactModalError = signal<string | null>(null);
  readonly editingContactLink = signal<NegotiationContactLink | null>(null);
  readonly deletingContactLink = signal<NegotiationContactLink | null>(null);

  readonly contactForm = this.fb.group({
    contact_id: this.fb.control('', Validators.required),
    role: this.fb.control('other'),
    is_primary: this.fb.control(false),
    notes: this.fb.control(''),
  });

  readonly contactRoleOptions = [
    { id: 'decision_maker', label: 'Decisor' },
    { id: 'influencer', label: 'Influenciador' },
    { id: 'buyer', label: 'Comprador' },
    { id: 'user', label: 'Usuário' },
    { id: 'technical', label: 'Técnico' },
    { id: 'other', label: 'Outro' },
  ];

  readonly contactRoleSelectOptions: SelectOption[] = this.contactRoleOptions.map((opt) => ({
    label: opt.label,
    value: opt.id,
  }));

  readonly availableContactOptions = computed(() => {
    const linkedIds = new Set(this.contactLinks().map((link) => String(link.contact_id)));
    const editing = this.editingContactLink();
    return this.availableContacts().filter((contact) => {
      if (editing && String(editing.contact_id) === String(contact.id)) {
        return true;
      }
      return !linkedIds.has(String(contact.id));
    });
  });

  readonly availableContactSelectOptions = computed<SelectOption[]>(() => {
    const options = this.availableContactOptions().map((contact) => ({
      label: contact.name,
      value: String(contact.id),
    }));

    const editing = this.editingContactLink();
    if (!editing) return options;

    const hasEditingOption = options.some(
      (option) => String(option.value) === String(editing.contact_id),
    );

    if (hasEditingOption) return options;

    const fallbackLabel = editing.contact?.name ?? this.getContactName(editing);
    if (!fallbackLabel || fallbackLabel === '-') return options;

    return [{ label: fallbackLabel, value: String(editing.contact_id) }, ...options];
  });

  ngOnInit(): void {
    this.loadContactLinks();
  }

  loadContactLinks(): void {
    this.isContactsLoading.set(true);
    this.contactService
      .list(this.negotiationId())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.contactLinks.set(response.data.contacts ?? []);
          this.isContactsLoading.set(false);
        },
        error: () => {
          this.contactLinks.set([]);
          this.isContactsLoading.set(false);
          this.failed.emit('Não foi possível carregar os contatos da negociação.');
        },
      });
  }

  openContactModal(link?: NegotiationContactLink): void {
    this.editingContactLink.set(link ?? null);
    this.contactModalError.set(null);
    this.isContactModalOpen.set(true);

    this.contactForm.reset({
      contact_id: link ? String(link.contact_id) : '',
      role: link?.role ?? 'other',
      is_primary: link?.is_primary ?? false,
      notes: link?.notes ?? '',
    });

    if (link) {
      this.contactForm.controls.contact_id.disable();
    } else {
      this.contactForm.controls.contact_id.enable();
    }
  }

  closeContactModal(): void {
    this.isContactModalOpen.set(false);
    this.editingContactLink.set(null);
    this.isContactSaving.set(false);
    this.contactForm.controls.contact_id.enable();
  }

  saveContact(): void {
    if (this.contactForm.invalid) {
      this.contactForm.markAllAsTouched();
      return;
    }

    const value = this.contactForm.getRawValue();
    const editing = this.editingContactLink();
    const payload = {
      role: value.role,
      is_primary: value.is_primary,
      notes: value.notes || null,
    };

    const request = editing
      ? this.contactService.update(this.negotiationId(), editing.id, payload)
      : this.contactService.create(this.negotiationId(), {
          contact_id: value.contact_id ? String(value.contact_id) : undefined,
          ...payload,
        });

    this.isContactSaving.set(true);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isContactSaving.set(false);
        toast.success(editing ? 'Contato atualizado.' : 'Contato vinculado.');
        this.closeContactModal();
        this.loadContactLinks();
        this.contactsChanged.emit();
      },
      error: () => {
        this.isContactSaving.set(false);
        this.contactModalError.set('Não foi possível salvar o contato.');
      },
    });
  }

  confirmDeleteContact(link: NegotiationContactLink): void {
    this.deletingContactLink.set(link);
  }

  cancelDeleteContact(): void {
    this.deletingContactLink.set(null);
  }

  deleteContact(): void {
    const link = this.deletingContactLink();
    if (!link) return;

    this.contactService
      .delete(this.negotiationId(), link.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          toast.success('Contato removido.');
          this.deletingContactLink.set(null);
          this.loadContactLinks();
          this.contactsChanged.emit();
        },
        error: () => {
          this.failed.emit('Não foi possível remover o contato.');
        },
      });
  }

  getContactRoleLabel(role?: string | null): string {
    if (!role) return 'Outro';
    return this.contactRoleOptions.find((option) => option.id === role)?.label ?? 'Outro';
  }

  getContactName(link: NegotiationContactLink): string {
    if (link.contact?.name) return link.contact.name;
    return (
      this.availableContacts().find((contact) => String(contact.id) === String(link.contact_id))
        ?.name ?? '-'
    );
  }

  getContactEmail(link: NegotiationContactLink): string {
    if (link.contact?.email) return link.contact.email;
    return (
      this.availableContacts().find((contact) => String(contact.id) === String(link.contact_id))
        ?.email ?? '-'
    );
  }

  getContactPhone(link: NegotiationContactLink): string {
    const directPhone = link.contact?.phone || link.contact?.whatsapp;
    if (directPhone) return directPhone;

    const contact = this.availableContacts().find(
      (item) => String(item.id) === String(link.contact_id),
    );
    return contact?.phone || contact?.whatsapp || '-';
  }
}
