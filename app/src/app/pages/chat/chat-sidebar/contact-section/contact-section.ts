import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  EventEmitter,
  Input,
  Output,
  computed,
  inject,
  signal,
} from '@angular/core';
import {
  FormControl,
  NonNullableFormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NgIcon } from '@ng-icons/core';
import { toast } from 'ngx-sonner';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { type CalledContactSummary } from 'src/app/core/services/called.service';
import { type ContactTag, TagsModalComponent } from '../tags-modal/tags-modal';
import { CompanyModalComponent } from '../company-modal/company-modal';
import { IconButtonComponent, LoadingButtonComponent } from 'src/app/shared/components/buttons';
import { TextInputComponent } from 'src/app/shared/components/text-input/text-input';
import { AfPhoneInputComponent } from 'src/app/shared/components/phone-input/phone-input';
import { AfDocumentInputComponent } from 'src/app/shared/components/document-input/document-input';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';
import { AfEmptyStateComponent } from 'src/app/shared/components/empty-state/empty-state';

/**
 * Secao de contato no sidebar do chat.
 *
 * @remarks
 * Exibe e permite edicao dos dados do contato selecionado.
 * Gerencia tags, empresa vinculada e validacao de formulario.
 *
 * @example
 * ```html
 * <app-chat-contact-section
 *   [contact]="contact"
 *   [ticketId]="ticketId"
 *   (contactUpdated)="onContactUpdated($event)" />
 * ```
 */
@Component({
  selector: 'app-chat-contact-section',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    NgIcon,
    IconButtonComponent,
    LoadingButtonComponent,
    TextInputComponent,
    AfPhoneInputComponent,
    AfDocumentInputComponent,
    AfScrollAreaComponent,
    AfEmptyStateComponent,
    TagsModalComponent,
    CompanyModalComponent,
  ],
  templateUrl: './contact-section.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full',
  },
})
export class ContactSectionComponent {
  private readonly contactService = inject(ContactService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly contactSignal = signal<Contact | null>(null);
  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly isTagsModalOpen = signal(false);
  readonly isCompanyModalOpen = signal(false);

  readonly form = this.fb.group({
    name: this.fb.control('', [Validators.required, Validators.minLength(3)]),
    phone: this.fb.control(''),
    phone_country_code: this.fb.control('+55'),
    email: this.fb.control('', [Validators.email]),
    document: new FormControl<string | null>(null),
  });

  readonly skeletonRows = Array.from({ length: 5 }, (_, index) => index);

  readonly contactTags = computed<ContactTag[]>(() => {
    const contact = this.contactSignal();
    if (!contact?.tags) return [];

    return (contact.tags as (ContactTag | string)[]).map((tag) => {
      if (typeof tag === 'string') {
        return { id: tag, name: tag };
      }

      return { id: tag.id, name: tag.name };
    });
  });

  readonly companyName = computed(() => {
    const company = this.contactSignal()?.company;
    return company?.name ?? 'Sem empresa';
  });

  readonly showEmpty = computed(() => !this.isLoading() && !this.contactSignal());

  /** Indica se o form tem alterações pendentes em relação ao contato salvo. */
  readonly isDirty = computed(() => {
    const contact = this.contactSignal();
    if (!contact) return false;
    const v = this.form.getRawValue();
    return (
      v.name !== (contact.name ?? '') ||
      v.email !== (contact.email ?? '') ||
      v.phone !== (contact.phone ?? contact.whatsapp ?? '') ||
      (v.document ?? '') !== (contact.document ?? '')
    );
  });

  @Output() readonly contactUpdated = new EventEmitter<Contact>();

  @Input() ticketId?: string | number;

  @Input() set contact(value: CalledContactSummary | null) {
    if (!value) {
      this.resetState();
      return;
    }

    this.isLoading.set(true);
    this.patchFormFromSummary(value);

    this.contactService
      .find(value.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const contact = this.extractContact(response.data);

          if (!contact) {
            this.contactSignal.set(null);
            this.isLoading.set(false);
            return;
          }

          this.contactSignal.set(contact);
          this.patchFormFromContact(contact);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          toast.error('Não foi possível carregar o contato.');
        },
      });
  }

  /** Salva todas as alterações do formulário de uma vez. */
  save(): void {
    const contact = this.contactSignal();
    if (!contact) return;

    this.form.markAllAsTouched();
    if (this.form.invalid) {
      toast.error('Corrija os campos antes de salvar.');
      return;
    }

    const values = this.form.getRawValue();
    const payload: Record<string, unknown> = {
      name: values.name,
      email: values.email || null,
      phone: values.phone || null,
      document: values.document || null,
      ticket_id: this.ticketId ?? undefined,
    };

    this.isSaving.set(true);

    this.contactService
      .patch(contact.id, payload as Partial<Contact> & { ticket_id?: string | number })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const updated = this.extractContact(response.data);
          if (updated) {
            this.contactSignal.set(updated);
            this.patchFormFromContact(updated);
            this.contactUpdated.emit(updated);
          }
          this.isSaving.set(false);
          toast.success('Contato atualizado.');
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível salvar as alterações.');
        },
      });
  }

  onCompanyUpdated(updatedContact: Contact): void {
    if (updatedContact) {
      this.contactSignal.set(updatedContact);
      this.patchFormFromContact(updatedContact);
      this.contactUpdated.emit(updatedContact);
    }
  }

  onTagsUpdated(tags: ContactTag[]): void {
    const contact = this.contactSignal();
    if (!contact) return;
    this.contactSignal.set({
      ...contact,
      tags,
    });
  }

  openTagsModal(): void {
    this.isTagsModalOpen.set(true);
  }

  closeTagsModal(): void {
    this.isTagsModalOpen.set(false);
  }

  openCompanyModal(): void {
    this.isCompanyModalOpen.set(true);
  }

  closeCompanyModal(): void {
    this.isCompanyModalOpen.set(false);
  }

  /**
   * Extrai o Contact do payload da response, independente do formato.
   * Suporta `{ contact: Contact }` e `Contact` diretamente.
   */
  private extractContact(data: unknown): Contact | null {
    if (!data) return null;
    const payload = data as { contact?: Contact } | Contact;
    if ('contact' in payload && payload.contact) {
      return payload.contact;
    }
    if ('id' in payload && 'name' in payload) {
      return payload as Contact;
    }
    return null;
  }

  private patchFormFromSummary(value: CalledContactSummary): void {
    this.form.patchValue({
      name: value.name ?? '',
      email: value.email ?? '',
      phone: value.phone ?? value.whatsapp ?? '',
      document: null,
    });
  }

  private patchFormFromContact(contact: Contact): void {
    this.form.patchValue({
      name: contact.name ?? '',
      email: contact.email ?? '',
      phone: contact.phone ?? contact.whatsapp ?? '',
      document: contact.document ?? null,
    });
  }

  private resetState(): void {
    this.contactSignal.set(null);
    this.isLoading.set(false);
    this.form.reset();
  }
}
