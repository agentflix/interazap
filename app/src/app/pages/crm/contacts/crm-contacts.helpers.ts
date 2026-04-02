import type { Contact, ContactFilters } from '@core/services/crm-contact.service';
import type { SortDirection } from '@shared/components';

/**
 * Supported status filter values for contacts list.
 */
export type ContactStatusFilter = 'all' | 'active' | 'inactive';

/**
 * Input state required to build list filters for contacts endpoint.
 */
export interface ContactFilterState {
  searchTerm: string;
  page: number;
  sortBy: string;
  sortDir: SortDirection;
  status: ContactStatusFilter;
}

/**
 * Maps UI status filter value to API is_active value.
 */
export function mapStatusToIsActive(status: ContactStatusFilter): boolean | undefined {
  if (status === 'active') {
    return true;
  }

  if (status === 'inactive') {
    return false;
  }

  return undefined;
}

/**
 * Builds contact list filters keeping API contract intact.
 */
export function buildContactFilters(state: ContactFilterState): ContactFilters {
  return {
    search: state.searchTerm || undefined,
    page: state.page,
    per_page: 15,
    sort_by: state.sortBy,
    sort_dir: state.sortDir,
    is_active: mapStatusToIsActive(state.status),
  };
}

/**
 * Computes selected IDs when toggling a single row checkbox.
 */
export function computeNextSelectedIds(
  currentIds: string[],
  contactId: string,
  checked: boolean,
): string[] {
  const selected = new Set(currentIds);

  if (checked) {
    selected.add(contactId);
  } else {
    selected.delete(contactId);
  }

  return [...selected];
}

/**
 * Returns selected IDs for current page based on select-all state.
 */
export function computePageSelectionIds(contacts: Contact[], checked: boolean): string[] {
  return checked ? contacts.map((contact) => String(contact.id)) : [];
}

/**
 * Removes stale selected IDs that are not part of current page.
 */
export function pruneSelectionToVisibleContacts(
  contacts: Contact[],
  selectedIds: string[],
): string[] {
  const activeIds = new Set(contacts.map((contact) => String(contact.id)));
  return selectedIds.filter((id) => activeIds.has(id));
}

/**
 * Returns true when all current page rows are selected.
 */
export function areAllPageContactsSelected(contacts: Contact[], selectedIds: string[]): boolean {
  if (contacts.length === 0) {
    return false;
  }

  const selectedSet = new Set(selectedIds);
  return contacts.every((contact) => selectedSet.has(String(contact.id)));
}

/**
 * Builds delete confirmation message for single or bulk delete.
 */
export function buildDeleteConfirmationMessage(
  contact: Contact | null,
  selectedCount: number,
): string {
  if (!contact && selectedCount > 0) {
    return `Tem certeza que deseja excluir ${selectedCount} contatos selecionados? Esta ação não pode ser desfeita.`;
  }

  if (contact) {
    return `Tem certeza que deseja excluir o contato "${contact.name}"? Esta ação não pode ser desfeita.`;
  }

  return 'Tem certeza que deseja excluir este contato?';
}
