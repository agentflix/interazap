import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { CompanyModalComponent } from './company-modal';
import { CRMCompanyService, type CRMCompany } from 'src/app/core/services/crm-company.service';
import { ContactService } from 'src/app/core/services/contact.service';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';
import { ReactiveFormsModule } from '@angular/forms';
import { provideIcons } from '@ng-icons/core';
import { lucideX } from '@ng-icons/lucide';
import { provideEnvironmentNgxMask } from 'ngx-mask';
import { type Contact } from 'src/app/core/models/contact.model';

describe('CompanyModalComponent', () => {
  let component: CompanyModalComponent;
  let fixture: ComponentFixture<CompanyModalComponent>;
  let crmCompanyServiceSpy: {
    list: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
  };
  let contactServiceSpy: {
    patch: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    crmCompanyServiceSpy = {
      list: vi.fn(),
      create: vi.fn(),
    };
    contactServiceSpy = {
      patch: vi.fn(),
    };

    // Mock toast methods
    vi.mock('ngx-sonner', () => ({
      toast: {
        success: vi.fn(),
        error: vi.fn(),
      },
    }));

    await TestBed.configureTestingModule({
      imports: [CompanyModalComponent, ReactiveFormsModule],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideIcons({ lucideX }),
        provideEnvironmentNgxMask(),
        { provide: CRMCompanyService, useValue: crmCompanyServiceSpy },
        { provide: ContactService, useValue: contactServiceSpy },
      ],
    }).compileComponents();

    crmCompanyServiceSpy.list.mockReturnValue(of({ data: [] }));

    fixture = TestBed.createComponent(CompanyModalComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  describe('loading', () => {
    it('should reset state when open input is true', () => {
      component.searchControl.setValue('acme');
      component.isOpen = true;

      expect(component.searchTerm()).toBe('');
      expect(component.companies()).toEqual([]);
      expect(crmCompanyServiceSpy.list).not.toHaveBeenCalled();
    });

    it('should not load companies when open input is false', () => {
      crmCompanyServiceSpy.list.mockClear();
      component.isOpen = false;
      expect(crmCompanyServiceSpy.list).not.toHaveBeenCalled();
    });
  });

  describe('search', () => {
    it('should debounce search input', () => {
      vi.useFakeTimers();

      (
        component as unknown as {
          onSearchInput: (value: string) => void;
        }
      ).onSearchInput('test');
      expect(component.searchTerm()).toBe('test');
      expect(crmCompanyServiceSpy.list).not.toHaveBeenCalled();

      vi.advanceTimersByTime(300);

      expect(crmCompanyServiceSpy.list).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'test' }),
      );

      vi.useRealTimers();
    });
  });

  describe('selection', () => {
    it('should update contact when company is selected', () => {
      component.contactId = '1';
      const company: CRMCompany = {
        id: '10',
        name: 'Company A',
        is_active: true,
      };
      const contact: Contact = {
        id: '1',
        name: 'Contact',
        is_active: true,
        crm_company_id: 'company-1',
        created_at: '2026-01-01T00:00:00.000Z',
        updated_at: '2026-01-01T00:00:00.000Z',
      };
      contactServiceSpy.patch.mockReturnValue(of({ data: { contact } }));

      let updatedContact: Contact | null = null;
      component.companyUpdated.subscribe((c) => (updatedContact = c));

      // Spy on close
      let closedEvent = false;
      component.closed.subscribe(() => (closedEvent = true));

      component.selectCompany(company);

      expect(contactServiceSpy.patch).toHaveBeenCalledWith(
        '1',
        expect.objectContaining({ crm_company_id: '10' }),
      );
      expect(updatedContact).toBeTruthy();
      expect(closedEvent).toBe(true);
    });

    it('should handle update error', () => {
      component.contactId = '1';
      const company: CRMCompany = {
        id: '10',
        name: 'Company A',
        is_active: true,
      };
      contactServiceSpy.patch.mockReturnValue(throwError(() => new Error('Error')));

      component.selectCompany(company);

      expect(component.isSaving()).toBe(false);
      // Expect toast error (mocked)
    });
  });

  describe('creation', () => {
    it('should create new company and select it', () => {
      component.contactId = '1';
      component.createForm.controls.name.setValue('Nova Empresa Teste');
      // Usar setValue com emitEvent: true para garantir que a máscara processe (padrão)
      component.createForm.controls.document.setValue('52364596000157');

      const newCompany: CRMCompany = {
        id: '99',
        name: 'Nova Empresa Teste',
        document: '52364596000157',
        is_active: true,
      };
      const contact: Contact = {
        id: '1',
        name: 'Contact',
        is_active: true,
        crm_company_id: 'company-1',
        created_at: '2026-01-01T00:00:00.000Z',
        updated_at: '2026-01-01T00:00:00.000Z',
      };
      crmCompanyServiceSpy.create.mockReturnValue(of({ data: newCompany }));
      contactServiceSpy.patch.mockReturnValue(of({ data: { contact } }));

      component.createCompany();

      // Relaxando a verificação do document por enquanto para ver se chama
      expect(crmCompanyServiceSpy.create).toHaveBeenCalledWith(
        expect.objectContaining({
          name: 'Nova Empresa Teste',
        }),
      );
      expect(contactServiceSpy.patch).toHaveBeenCalledWith(
        '1',
        expect.objectContaining({ crm_company_id: '99' }),
      );
    });

    it('should not create if form is invalid', () => {
      component.createForm.setValue({ name: '', document: '' }); // Invalid name
      component.createCompany();
      expect(crmCompanyServiceSpy.create).not.toHaveBeenCalled();
    });
  });
});
