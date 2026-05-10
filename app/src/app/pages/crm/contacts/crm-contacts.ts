import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  viewChild,
  untracked,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed, toObservable, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin, of } from 'rxjs';
import { catchError, startWith, switchMap, tap } from 'rxjs/operators';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfCrudPageComponent,
  AfConfirmModalComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfSortableHeaderComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  type SortDirection,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type Contact, ContactService } from '@core/services/crm-contact.service';
import { PaginationMeta } from '@shared/models/pagination.model';
import { UtilsService } from '@core/services/utils.service';
import { ContactFormComponent } from './components/contact-form/crm-contact-form';
import { ContactExportComponent } from './components/contact-export/crm-contact-export';
import { ContactImportComponent } from './components/contact-import/crm-contact-import';
import { getInitials } from '@shared/utils/string.utils';
import {
  areAllPageContactsSelected,
  buildContactFilters,
  buildDeleteConfirmationMessage,
  computeNextSelectedIds,
  computePageSelectionIds,
  pruneSelectionToVisibleContacts,
  type ContactStatusFilter,
} from './crm-contacts.helpers';

/**
 * Contacts CRM page — CRUD for contacts with import/export.
 * Follows Angular 20+ best practices with Signals and OnPush.
 */
@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfCheckboxInputComponent,
    AfLoadingButtonComponent,
    AfSelectInputComponent,
    AfDataTableComponent,
    AfDrawerComponent,
    AfSortableHeaderComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    ContactFormComponent,
    ContactExportComponent,
    ContactImportComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-contacts.html',
})
export class Contacts implements OnInit {
  private readonly contactService = inject(ContactService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  protected readonly utils = inject(UtilsService);
  private routeHandled = false;

  /** Reference to the contact form component */
  readonly contactFormRef = viewChild<ContactFormComponent>('contactForm');
  /** Reference to the contact import component */
  readonly contactImportRef = viewChild<ContactImportComponent>('contactImportComponent');

  /** Whether the contact form is currently saving */
  readonly isFormSaving = computed(() => this.contactFormRef()?.isSaving() ?? false);

  // ─── Filter drawer ─────────────────────────────────────────────────────────
  readonly isFilterOpen = signal(false);

  readonly activeFiltersCount = computed(() =>
    this.filterStatus() !== 'all' ? 1 : 0,
  );

  openFilter(): void { this.isFilterOpen.set(true); }
  closeFilter(): void { this.isFilterOpen.set(false); }

  clearFilter(): void {
    this.filterStatusControl.setValue('all');
  }

  applyFilter(): void {
    this.closeFilter();
  }

  // ─── Filter signals ────────────────────────────────────────────────────────
  /** Current search term for the contact list */
  readonly searchTerm = signal('');
  /** Current page number for pagination */
  private readonly pageNumber = signal(1);
  /** Current field to sort by */
  readonly sortBy = signal<string>('name');
  /** Current sorting direction */
  readonly sortDir = signal<SortDirection>('asc');

  /** Trigger signal to force reactivity refreshes */
  private readonly refreshTrigger = signal(0);

  /** Control for filtering contacts by status */
  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });

  /** Signal for the status filter value */
  readonly filterStatus = toSignal(
    this.filterStatusControl.valueChanges.pipe(startWith(this.filterStatusControl.value)),
    { initialValue: 'all' },
  );

  /** Combined filter signal for API requests */
  private readonly filters = computed(() => ({
    ...buildContactFilters({
      searchTerm: this.searchTerm(),
      page: this.pageNumber(),
      sortBy: this.sortBy(),
      sortDir: this.sortDir(),
      status: this.filterStatus() as ContactStatusFilter,
    }),
    _refresh: this.refreshTrigger(),
  }));

  // ─── List state (Refactored to Signal-based data fetching) ─────────────────
  /** Data resource for contacts list, automatically re-fetches when filters change */
  private readonly contactsResource = toSignal(
    toObservable(this.filters).pipe(
      tap(() => {
        this.isLoading.set(true);
        this.hasError.set(false);
      }),
      switchMap((f) =>
        this.contactService.list(f).pipe(
          catchError(() => {
            this.hasError.set(true);
            return of(null);
          }),
        ),
      ),
      tap((response) => {
        this.isLoading.set(false);
        if (response) {
          untracked(() => this.syncSelectionControls(response.data));
        }
      }),
    ),
  );

  /** List of contacts for the current page */
  readonly contacts = computed(() => this.contactsResource()?.data ?? []);
  /** Whether the contact list is currently loading */
  readonly isLoading = signal(true);
  /** Whether an error occurred while loading contacts */
  readonly hasError = signal(false);
  /** Pagination metadata */
  readonly meta = computed(
    () =>
      this.contactsResource()?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 },
  );

  /** Whether the contact list is empty and not loading */
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.contacts().length === 0,
  );

  /** Options for the status filter select input */
  readonly filterStatusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Ativo', value: 'active' },
    { label: 'Inativo', value: 'inactive' },
  ];

  // ─── Modal state ───────────────────────────────────────────────────────────
  /** Whether the create/edit modal is open */
  readonly showFormModal = signal(false);
  /** The contact currently being edited */
  readonly selectedContact = signal<Contact | null>(null);
  /** Dynamic title for the form modal */
  readonly formModalTitle = computed(() =>
    this.selectedContact() ? 'Editar contato' : 'Novo contato',
  );

  /** Whether the delete confirmation modal is open */
  readonly showDeleteModal = signal(false);
  /** The contact targeted for deletion */
  readonly contactToDelete = signal<Contact | null>(null);
  /** Whether a deletion process is in progress */
  readonly isDeleting = signal(false);

  /** Control for \"select all\" checkbox in the table header */
  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  /** List of IDs of currently selected contacts */
  readonly selectedContactIds = signal<string[]>([]);
  /** Number of selected contacts */
  readonly selectedCount = computed(() => this.selectedContactIds().length);
  /** Whether any contact is selected */
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  /** Dynamic title for the delete confirmation modal */
  readonly deleteModalTitle = computed(() =>
    this.contactToDelete() ? 'Excluir contato' : 'Excluir contatos',
  );
  /** Dynamic label for the delete confirmation button */
  readonly deleteConfirmLabel = computed(() =>
    this.contactToDelete() ? 'Excluir' : 'Excluir selecionados',
  );
  /** Dynamic message for the delete confirmation modal */
  readonly deleteMessage = computed(() => {
    return buildDeleteConfirmationMessage(this.contactToDelete(), this.selectedCount());
  });

  // ─── Import state ──────────────────────────────────────────────────────────
  /** Whether the import modal is open */
  readonly isImportOpen = signal(false);

  constructor() {
    this.setupFilterStatusSubscription();
    this.setupSelectAllSubscription();
  }

  ngOnInit(): void {
    this.handleRouteContact();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────
  /** Opens the modal to create a new contact */
  openCreate(): void {
    this.selectedContact.set(null);
    this.showFormModal.set(true);
  }

  /** Opens the modal to edit an existing contact */
  openEdit(contact: Contact): void {
    this.selectedContact.set(contact);
    this.showFormModal.set(true);
  }

  /** Opens the confirmation modal to delete a contact */
  openDelete(contact: Contact): void {
    this.contactToDelete.set(contact);
    this.showDeleteModal.set(true);
  }

  /** Opens the confirmation modal to delete all selected contacts */
  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.contactToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  /** Handles the event when a contact is successfully saved */
  handleFormSaved(_contact: Contact): void {
    this.showFormModal.set(false);
    this.selectedContact.set(null);
    this.toast.success('Contato salvo com sucesso.');
    this.refresh();
  }

  /** Handles the event when the form modal is cancelled */
  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedContact.set(null);
  }

  /** Handles confirmed deletion of one or more contacts */
  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;
    const ids = this.getDeleteTargetIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.contactService.delete(id))).subscribe({
      next: () => {
        this.finishDeleteSuccess(ids);
      },
      error: () => {
        this.finishDeleteError();
      },
    });
  }

  /** Triggers a search with the provided term */
  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
  }

  /** Navigates to a specific page of contacts */
  loadPage(page: number): void {
    this.pageNumber.set(page);
  }

  /** Handles sorting changes from the table header */
  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
  }

  /** Retries loading contacts after an error */
  retry(): void {
    this.refresh();
  }

  /** Forces a refresh of the contact list */
  refresh(): void {
    // Increment the trigger to force a new emission from the filters signal
    this.refreshTrigger.update((v) => v + 1);
  }

  // ─── Import ────────────────────────────────────────────────────────────────
  /** Opens the import modal */
  openImport(): void {
    this.contactImportRef()?.reset();
    this.isImportOpen.set(true);
  }

  /** Closes the import modal */
  closeImport(): void {
    this.isImportOpen.set(false);
  }

  /** Handles successful import completion */
  handleImported(): void {
    this.refresh();
  }

  /** Handles import error */
  handleImportError(_message: string): void {
    // Error is shown inline in the import component
  }

  /** Hides img element on load error so initials fallback shows through. */
  handleImageError(event: Event): void {
    (event.target as HTMLImageElement).style.display = 'none';
  }

  /** Returns up to 2 uppercase initials from a contact name */
  getInitials(name: string): string {
    return getInitials(name);
  }

  // ─── Data loading ──────────────────────────────────────────────────────────
  private handleRouteContact(): void {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const contactId = params.get('contact_id');
      if (!contactId || this.routeHandled) return;
      this.routeHandled = true;
      this.contactService
        .find(contactId)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (response) => {
            this.openEdit(response.data);
            void this.router.navigate([], {
              queryParams: { contact_id: null },
              queryParamsHandling: 'merge',
              replaceUrl: true,
            });
          },
          error: () => {
            this.routeHandled = false;
            this.toast.error('Não foi possível carregar o contato.');
          },
        });
    });
  }

  /**
   * Retrieves or creates a FormControl for row selection in the table.
   * @param id - Contact ID.
   * @returns The checkbox FormControl.
   */
  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const nextSelectedIds = computeNextSelectedIds(this.selectedContactIds(), id, checked);
      this.selectedContactIds.set(nextSelectedIds);
      this.selectAllControl.setValue(areAllPageContactsSelected(this.contacts(), nextSelectedIds), {
        emitEvent: false,
      });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(contacts: Contact[]): void {
    const visibleIds = new Set(contacts.map((contact) => String(contact.id)));
    for (const key of this.rowSelectionControls.keys()) {
      if (!visibleIds.has(key)) this.rowSelectionControls.delete(key);
    }

    const selectedIds = pruneSelectionToVisibleContacts(contacts, this.selectedContactIds());
    this.selectedContactIds.set(selectedIds);

    for (const contact of contacts) {
      const contactId = String(contact.id);
      this.getRowSelectionControl(contactId).setValue(selectedIds.includes(contactId));
    }

    this.selectAllControl.setValue(areAllPageContactsSelected(contacts, selectedIds), {
      emitEvent: false,
    });
  }

  private setupFilterStatusSubscription(): void {
    toObservable(this.filterStatus)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.pageNumber.set(1));
  }

  private setupSelectAllSubscription(): void {
    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const nextSelectedIds = computePageSelectionIds(this.contacts(), checked);
        this.selectedContactIds.set(nextSelectedIds);

        for (const contact of this.contacts()) {
          this.getRowSelectionControl(String(contact.id)).setValue(checked);
        }
      });
  }

  private getDeleteTargetIds(): string[] {
    const contact = this.contactToDelete();
    if (contact) {
      return [String(contact.id)];
    }

    return this.selectedContactIds();
  }

  private finishDeleteSuccess(ids: string[]): void {
    this.isDeleting.set(false);
    this.showDeleteModal.set(false);
    this.contactToDelete.set(null);
    this.clearSelection();
    this.toast.success(
      ids.length > 1 ? 'Contatos excluídos com sucesso.' : 'Contato excluído com sucesso.',
    );
    this.refresh();
  }

  private finishDeleteError(): void {
    this.isDeleting.set(false);
    this.showDeleteModal.set(false);
  }

  private clearSelection(): void {
    this.selectedContactIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}

export default Contacts;
