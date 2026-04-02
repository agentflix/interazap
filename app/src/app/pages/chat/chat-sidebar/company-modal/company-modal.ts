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
import { type CRMCompany, CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { ModalComponent } from 'src/app/shared/components/modal/modal';
import { SearchInputComponent } from 'src/app/shared/components/search-input/search-input';
import { TextInputComponent } from 'src/app/shared/components/text-input/text-input';
import { MaskedInputComponent } from 'src/app/shared/components/masked-input/masked-input';
import { ButtonComponent } from 'src/app/shared/components/button/button';
import { IconButtonComponent } from 'src/app/shared/components/icon-button/icon-button';
import { LoadingButtonComponent } from 'src/app/shared/components/loading-button/loading-button';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';

/**
 * Modal para selecionar ou criar empresa vinculada a um contato.
 *
 * @remarks
 * Empresas so sao buscadas quando o usuario digita no campo de busca (lazy load).
 * O botao "+" abre uma sub-modal para criar nova empresa e auto-selecionar.
 *
 * @example
 * ```html
 * <app-company-modal
 *   [isOpen]="isOpen"
 *   [contactId]="contactId"
 *   [selectedCompanyId]="companyId"
 *   (closed)="onClosed()"
 *   (companyUpdated)="onCompanyUpdated($event)" />
 * ```
 */
@Component({
  selector: 'app-company-modal',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    NgIcon,
    ModalComponent,
    SearchInputComponent,
    TextInputComponent,
    MaskedInputComponent,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
    AfScrollAreaComponent,
  ],
  templateUrl: './company-modal.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CompanyModalComponent {
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly contactService = inject(ContactService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly isOpenSignal = signal(false);
  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly isCreateModalOpen = signal(false);
  readonly hasSearched = signal(false);
  readonly companies = signal<CRMCompany[]>([]);
  readonly searchTerm = signal('');
  readonly searchControl = new FormControl<string>('', { nonNullable: true });
  readonly selectedCompanyIdSignal = signal<string | number | null>(null);

  readonly createForm = this.fb.group({
    name: this.fb.control('', [Validators.required, Validators.minLength(3)]),
    document: this.fb.control(''),
  });

  /** Indica se não há resultados após uma busca */
  readonly showEmpty = computed(
    () => this.hasSearched() && !this.isLoading() && this.companies().length === 0,
  );

  /** Indica se deve exibir o placeholder (antes de qualquer busca) */
  readonly showPlaceholder = computed(
    () => !this.hasSearched() && !this.isLoading() && this.companies().length === 0,
  );

  private searchTimeout?: number;

  constructor() {
    this.searchControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onSearchInput(value));
  }

  @Input() set isOpen(value: boolean) {
    this.isOpenSignal.set(value);
    if (value) {
      this.resetState();
    }
  }

  @Input() contactId: string | number | null = null;
  @Input() ticketId?: string | number;

  @Input() set selectedCompanyId(value: string | number | null) {
    this.selectedCompanyIdSignal.set(value);
  }

  @Output() readonly closed = new EventEmitter<void>();
  @Output() readonly companyUpdated = new EventEmitter<Contact>();

  close(): void {
    this.closed.emit();
  }

  openCreateModal(): void {
    this.createForm.reset();
    this.isCreateModalOpen.set(true);
  }

  closeCreateModal(): void {
    this.isCreateModalOpen.set(false);
  }

  selectCompany(company: CRMCompany): void {
    if (this.contactId === null) return;
    this.isSaving.set(true);

    this.contactService
      .patch(this.contactId, {
        crm_company_id: String(company.id),
        ticket_id: this.ticketId ?? undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSaving.set(false);
          this.selectedCompanyIdSignal.set(company.id);
          const contact = this.extractContact(response.data);
          if (contact) {
            this.companyUpdated.emit(contact);
          }
          toast.success('Empresa atualizada.');
          this.close();
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível atualizar a empresa.');
        },
      });
  }

  createCompany(): void {
    if (this.createForm.invalid || this.isSaving()) {
      this.createForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    const payload = this.createForm.getRawValue();

    this.crmCompanyService
      .create({
        name: payload.name ?? '',
        document: payload.document || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const company = response.data;
          this.companies.update((list) => [company, ...list]);
          this.createForm.reset();
          this.closeCreateModal();
          this.selectCompany(company);
        },
        error: () => {
          this.isSaving.set(false);
          toast.error('Não foi possível criar a empresa.');
        },
      });
  }

  /**
   * Extrai o Contact do payload da response, independente do formato.
   */
  private extractContact(data: unknown): Contact | null {
    if (data === null || data === undefined) return null;
    const payload = data as { contact?: Contact } | Contact;
    if ('contact' in payload && payload.contact) {
      return payload.contact;
    }
    if ('id' in payload && 'name' in payload) {
      return payload as Contact;
    }
    return null;
  }

  /** Busca no input com debounce — só faz request se houver termo */
  private onSearchInput(value: string): void {
    this.searchTerm.set(value);
    if (this.searchTimeout !== undefined) {
      window.clearTimeout(this.searchTimeout);
    }

    const trimmed = value.trim();
    if (!trimmed) {
      this.companies.set([]);
      this.hasSearched.set(false);
      return;
    }

    this.searchTimeout = window.setTimeout(() => this.searchCompanies(trimmed), 300);
  }

  /** Busca empresas na API pelo termo digitado */
  private searchCompanies(term: string): void {
    this.isLoading.set(true);
    this.hasSearched.set(true);
    this.crmCompanyService
      .list({ search: term, per_page: 50, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.companies.set(response.data ?? []);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          toast.error('Não foi possível carregar as empresas.');
        },
      });
  }

  /** Reseta o estado ao abrir o modal */
  private resetState(): void {
    this.searchControl.setValue('');
    this.searchTerm.set('');
    this.hasSearched.set(false);
    this.isCreateModalOpen.set(false);
    this.companies.set([]);
  }
}
