import type { Contact, ContactFilters } from '@core/models/contact.model';
import type { ContactFilterState, ContactStatusFilter } from '@crm/models/contact-filter.model';

export type { ContactFilterState, ContactStatusFilter } from '@crm/models/contact-filter.model';

/**
 * Mapeia o valor de filtro de status da UI para o valor is_active da API.
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
 * Valida se o contato possui pelo menos um canal de comunicação.
 * @param contact Dados do contato
 * @returns True se possui email ou telefone
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
 * Calcula os IDs selecionados ao alternar o checkbox de uma linha.
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
 * Retorna os IDs selecionados para a página atual com base no estado de seleção em massa.
 */
export function computePageSelectionIds(contacts: Contact[], checked: boolean): string[] {
  return checked ? contacts.map((contact) => String(contact.id)) : [];
}

/**
 * Remove IDs selecionados que não fazem parte da página atual.
 */
export function pruneSelectionToVisibleContacts(
  contacts: Contact[],
  selectedIds: string[],
): string[] {
  const activeIds = new Set(contacts.map((contact) => String(contact.id)));
  return selectedIds.filter((id) => activeIds.has(id));
}

/**
 * Retorna true quando todas as linhas da página atual estão selecionadas.
 */
export function areAllPageContactsSelected(contacts: Contact[], selectedIds: string[]): boolean {
  if (contacts.length === 0) {
    return false;
  }

  const selectedSet = new Set(selectedIds);
  return contacts.every((contact) => selectedSet.has(String(contact.id)));
}

/**
 * Constrói mensagem de confirmação para exclusão única ou em massa.
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
