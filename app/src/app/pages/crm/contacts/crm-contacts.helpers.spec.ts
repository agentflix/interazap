import {
  areAllPageContactsSelected,
  buildContactFilters,
  buildDeleteConfirmationMessage,
  computeNextSelectedIds,
  computePageSelectionIds,
  mapStatusToIsActive,
  pruneSelectionToVisibleContacts,
} from './crm-contacts.helpers';
import type { Contact } from '@core/models/contact.model';

describe('crm-contacts.helpers', () => {
  const contacts: Contact[] = [
    {
      id: 'c1',
      name: 'Alice Doe',
      is_active: true,
      company_id: 'cmp-1',
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    },
    {
      id: 'c2',
      name: 'Bob Doe',
      is_active: false,
      company_id: 'cmp-1',
      created_at: '2026-01-02T00:00:00Z',
      updated_at: '2026-01-02T00:00:00Z',
    },
  ];

  it('maps status filter to API values', () => {
    expect(mapStatusToIsActive('all')).toBeUndefined();
    expect(mapStatusToIsActive('active')).toBe(true);
    expect(mapStatusToIsActive('inactive')).toBe(false);
  });

  it('builds list filters preserving API contract', () => {
    expect(
      buildContactFilters({
        searchTerm: '  Alice ',
        page: 2,
        sortBy: 'name',
        sortDir: 'desc',
        status: 'active',
      }),
    ).toEqual({
      search: '  Alice ',
      page: 2,
      per_page: 15,
      sort_by: 'name',
      sort_dir: 'desc',
      is_active: true,
    });
  });

  it('computes next selected ids from row toggle', () => {
    expect(computeNextSelectedIds([], 'c1', true)).toEqual(['c1']);
    expect(computeNextSelectedIds(['c1', 'c2'], 'c1', false)).toEqual(['c2']);
  });

  it('computes select-all ids for current page', () => {
    expect(computePageSelectionIds(contacts, true)).toEqual(['c1', 'c2']);
    expect(computePageSelectionIds(contacts, false)).toEqual([]);
  });

  it('prunes stale selected ids against visible contacts', () => {
    expect(pruneSelectionToVisibleContacts(contacts, ['c1', 'gone'])).toEqual(['c1']);
  });

  it('checks all rows selected state', () => {
    expect(areAllPageContactsSelected(contacts, ['c1', 'c2'])).toBe(true);
    expect(areAllPageContactsSelected(contacts, ['c1'])).toBe(false);
    expect(areAllPageContactsSelected([], ['c1'])).toBe(false);
  });

  it('builds proper delete confirmation messages', () => {
    expect(buildDeleteConfirmationMessage(contacts[0], 0)).toContain('Alice Doe');
    expect(buildDeleteConfirmationMessage(null, 2)).toContain('2 contatos selecionados');
    expect(buildDeleteConfirmationMessage(null, 0)).toBe(
      'Tem certeza que deseja excluir este contato?',
    );
  });
});
