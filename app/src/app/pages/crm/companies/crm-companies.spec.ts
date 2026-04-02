import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { Companies } from './crm-companies';
import { CRMCompanyService, type CRMCompanyListResponse } from '@core/services/crm-company.service';
import { ToastService } from '@core/services/toast.service';

describe('Companies', () => {
  let fixture: ComponentFixture<Companies>;
  let component: Companies;

  const listResponse: CRMCompanyListResponse = {
    success: true,
    data: [
      {
        id: '1',
        name: 'Acme Corp',
        document: '12345678000190',
        phone: '5511999990000',
        is_active: true,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
      {
        id: '2',
        name: 'Globex Inc',
        document: '98765432000110',
        phone: '5521988880000',
        is_active: false,
        created_at: '2026-01-02T00:00:00Z',
        updated_at: '2026-01-02T00:00:00Z',
      },
    ],
    meta: {
      current_page: 1,
      from: 1,
      last_page: 1,
      per_page: 15,
      to: 2,
      total: 2,
    },
  };

  const emptyListResponse: CRMCompanyListResponse = {
    success: true,
    data: [],
    meta: {
      current_page: 1,
      from: 0,
      last_page: 1,
      per_page: 15,
      to: 0,
      total: 0,
    },
  };

  const companyServiceMock = {
    list: vi.fn().mockReturnValue(of(listResponse)),
    delete: vi.fn().mockReturnValue(of(null)),
    get: vi.fn(),
  };

  const toastMock = {
    success: vi.fn(),
  };

  const routerMock = {
    navigate: vi.fn().mockResolvedValue(true),
  };

  beforeEach(async () => {
    companyServiceMock.list.mockReturnValue(of(listResponse));
    companyServiceMock.list.mockClear();
    companyServiceMock.delete.mockClear();
    toastMock.success.mockClear();

    await TestBed.configureTestingModule({
      imports: [Companies],
      providers: [
        provideRouter([]),
        provideZonelessChangeDetection(),
        {
          provide: ActivatedRoute,
          useValue: { queryParamMap: of(convertToParamMap({})) },
        },
        { provide: CRMCompanyService, useValue: companyServiceMock },
        { provide: ToastService, useValue: toastMock },
        { provide: Router, useValue: routerMock },
      ],
    })
      .overrideComponent(Companies, {
        set: {
          template: '',
        },
      })
      .compileComponents();

    fixture = TestBed.createComponent(Companies);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('creates and loads initial list', () => {
    expect(component).toBeTruthy();
    expect(companyServiceMock.list).toHaveBeenCalledTimes(1);
    expect(component.companies().length).toBe(2);
    expect(component.isLoading()).toBe(false);
    expect(component.hasError()).toBe(false);
  });

  it('shows empty state when no companies', () => {
    companyServiceMock.list.mockReturnValue(of(emptyListResponse));
    component.retry();
    expect(component.companies().length).toBe(0);
    expect(component.isEmpty()).toBe(true);
  });

  it('shows error state on load failure', () => {
    companyServiceMock.list.mockReturnValue(throwError(() => new Error('fail')));
    component.retry();
    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);
  });

  it('searches companies', () => {
    component.onSearch('acme');
    expect(companyServiceMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ search: 'acme', page: 1 }),
    );
  });

  it('paginates companies', () => {
    component.loadPage(2);
    expect(companyServiceMock.list).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }));
  });

  it('opens create modal', () => {
    component.openCreate();
    expect(component.showFormModal()).toBe(true);
    expect(component.selectedCompany()).toBeNull();
    expect(component.formModalTitle()).toBe('Nova empresa');
  });

  it('opens edit modal with selected company', () => {
    const company = listResponse.data[0];
    component.openEdit(company);
    expect(component.showFormModal()).toBe(true);
    expect(component.selectedCompany()).toBe(company);
    expect(component.formModalTitle()).toBe('Editar empresa');
  });

  it('opens single delete confirmation', () => {
    const company = listResponse.data[0];
    component.openDelete(company);
    expect(component.showDeleteModal()).toBe(true);
    expect(component.companyToDelete()).toBe(company);
  });

  it('opens bulk delete only when there is a selection', () => {
    component.openBulkDelete();
    expect(component.showDeleteModal()).toBe(false);

    component.selectedCompanyIds.set(['1']);
    component.openBulkDelete();
    expect(component.showDeleteModal()).toBe(true);
    expect(component.companyToDelete()).toBeNull();
  });

  it('deletes a single company', () => {
    const company = listResponse.data[0];
    component.companyToDelete.set(company);
    component.showDeleteModal.set(true);

    component.handleDeleteConfirmed();

    expect(companyServiceMock.delete).toHaveBeenCalledTimes(1);
    expect(companyServiceMock.delete).toHaveBeenCalledWith('1');
    expect(component.showDeleteModal()).toBe(false);
    expect(toastMock.success).toHaveBeenCalledWith('Empresa excluída com sucesso.');
  });

  it('deletes selected companies in bulk and clears selection', () => {
    component.selectedCompanyIds.set(['1', '2']);
    component.showDeleteModal.set(true);

    component.handleDeleteConfirmed();

    expect(companyServiceMock.delete).toHaveBeenCalledTimes(2);
    expect(companyServiceMock.delete).toHaveBeenCalledWith('1');
    expect(companyServiceMock.delete).toHaveBeenCalledWith('2');
    expect(component.selectedCompanyIds()).toEqual([]);
    expect(component.showDeleteModal()).toBe(false);
    expect(toastMock.success).toHaveBeenCalledWith('Empresas excluídas com sucesso.');
  });

  it('cancels form modal and clears selection', () => {
    component.selectedCompany.set(listResponse.data[0]);
    component.showFormModal.set(true);

    component.handleFormCancelled();

    expect(component.showFormModal()).toBe(false);
    expect(component.selectedCompany()).toBeNull();
  });

  it('handles form saved and reloads list', () => {
    companyServiceMock.list.mockClear();
    component.showFormModal.set(true);

    component.handleFormSaved(listResponse.data[0]);

    expect(component.showFormModal()).toBe(false);
    expect(component.selectedCompany()).toBeNull();
    expect(toastMock.success).toHaveBeenCalledWith('Empresa salva com sucesso.');
    expect(companyServiceMock.list).toHaveBeenCalledTimes(1);
  });

  it('opens and closes detail panel', () => {
    const company = listResponse.data[0];
    component.openDetails(company);
    expect(component.isDetailsOpen()).toBe(true);
    expect(component.detailCompany()).toBe(company);

    component.closeDetails();
    expect(component.isDetailsOpen()).toBe(false);
  });

  it('sorts companies', () => {
    component.onSort({ field: 'name', dir: 'desc' });
    expect(companyServiceMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ sort_by: 'name', sort_dir: 'desc', page: 1 }),
    );
  });
});
