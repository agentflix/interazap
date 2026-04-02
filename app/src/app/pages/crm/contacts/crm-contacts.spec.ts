import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { of } from 'rxjs';
import { Contacts } from './crm-contacts';
import { ContactService, type ContactListResponse } from '@core/services/crm-contact.service';
import { ToastService } from '@core/services/toast.service';
import { UtilsService } from '@core/services/utils.service';

describe('Contacts', () => {
  let fixture: ComponentFixture<Contacts>;
  let component: Contacts;

  const listResponse: ContactListResponse = {
    success: true,
    data: [
      {
        id: '1',
        name: 'Alice Doe',
        is_active: true,
        company_id: 'cmp-1',
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
      },
      {
        id: '2',
        name: 'Bob Doe',
        is_active: false,
        company_id: 'cmp-1',
        created_at: '2026-01-02T00:00:00Z',
        updated_at: '2026-01-02T00:00:00Z',
      },
    ],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 2,
    },
  };

  const contactServiceMock = {
    list: vi.fn().mockReturnValue(of(listResponse)),
    delete: vi.fn().mockReturnValue(of(null)),
    find: vi.fn(),
  };

  const toastMock = {
    success: vi.fn(),
  };

  const utilsMock = {
    formatDocument: vi.fn().mockReturnValue('doc'),
    formatPhone: vi.fn().mockReturnValue('phone'),
  };

  const routerMock = {
    navigate: vi.fn().mockResolvedValue(true),
  };

  beforeEach(async () => {
    contactServiceMock.list.mockClear();
    contactServiceMock.delete.mockClear();
    toastMock.success.mockClear();

    await TestBed.configureTestingModule({
      imports: [Contacts],
      providers: [
        provideRouter([]),
        provideZonelessChangeDetection(),
        {
          provide: ActivatedRoute,
          useValue: { queryParamMap: of(convertToParamMap({})) },
        },
        { provide: ContactService, useValue: contactServiceMock },
        { provide: ToastService, useValue: toastMock },
        { provide: UtilsService, useValue: utilsMock },
        { provide: Router, useValue: routerMock },
      ],
    })
      .overrideComponent(Contacts, {
        set: {
          template: '',
        },
      })
      .compileComponents();

    fixture = TestBed.createComponent(Contacts);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('creates and loads initial list', () => {
    expect(component).toBeTruthy();
    expect(contactServiceMock.list).toHaveBeenCalledTimes(1);
    expect(component.contacts().length).toBe(2);
    expect(component.isLoading()).toBe(false);
    expect(component.hasError()).toBe(false);
  });

  it('opens bulk delete only when there is a selection', () => {
    component.openBulkDelete();
    expect(component.showDeleteModal()).toBe(false);

    component.selectedContactIds.set(['1']);
    component.openBulkDelete();
    expect(component.showDeleteModal()).toBe(true);
    expect(component.contactToDelete()).toBeNull();
  });

  it('deletes selected contacts and clears selection', () => {
    component.selectedContactIds.set(['1', '2']);
    component.showDeleteModal.set(true);

    component.handleDeleteConfirmed();

    expect(contactServiceMock.delete).toHaveBeenCalledTimes(2);
    expect(contactServiceMock.delete).toHaveBeenCalledWith('1');
    expect(contactServiceMock.delete).toHaveBeenCalledWith('2');
    expect(component.selectedContactIds()).toEqual([]);
    expect(component.showDeleteModal()).toBe(false);
    expect(toastMock.success).toHaveBeenCalledWith('Contatos excluídos com sucesso.');
  });
});
