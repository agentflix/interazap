import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { provideNgxMask } from 'ngx-mask';
import { of, throwError } from 'rxjs';
import { ContactSectionComponent } from './contact-section';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { TagService } from 'src/app/core/services/tag.service';
import { CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { type CalledContactSummary } from 'src/app/core/services/called.service';
import { toast } from 'ngx-sonner';

class ContactServiceStub {
  find = vi.fn();
  patch = vi.fn();
}

class TagServiceStub {
  all = vi.fn();
  create = vi.fn();
  attachToContact = vi.fn();
  detachFromContact = vi.fn();
}

class CRMCompanyServiceStub {
  list = vi.fn();
  create = vi.fn();
}

const createContact = (overrides: Partial<Contact> = {}): Contact => ({
  id: overrides.id ?? 'contact-1',
  name: overrides.name ?? 'Contato',
  is_active: overrides.is_active ?? true,
  crm_company_id: overrides.crm_company_id ?? 'tenant-1',
  created_at: overrides.created_at ?? '2026-01-22T10:00:00.000Z',
  updated_at: overrides.updated_at ?? '2026-01-22T10:00:00.000Z',
  ...overrides,
});

describe('ContactSectionComponent', (): void => {
  let component: ContactSectionComponent;
  let contactService: ContactServiceStub;

  beforeEach((): void => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      imports: [ContactSectionComponent],
      providers: [
        provideNgxMask(),
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: TagService, useClass: TagServiceStub },
        { provide: CRMCompanyService, useClass: CRMCompanyServiceStub },
      ],
    });

    contactService = TestBed.inject(ContactService) as unknown as ContactServiceStub;
    contactService.find.mockReturnValue(of({ data: { contact: createContact() } }));
    contactService.patch.mockReturnValue(
      of({ data: { contact: createContact({ name: 'João Silva' }) } }),
    );

    component = TestBed.createComponent(ContactSectionComponent).componentInstance;
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('loads full contact when input changes', async (): Promise<void> => {
    const summary: CalledContactSummary = {
      id: 'contact-1',
      name: 'João',
      email: 'joao@interazap.test',
      phone: '11999990000',
      whatsapp: '+5511999990000',
    };

    component.contact = summary;
    await vi.runAllTimersAsync();

    expect(component.contactSignal()?.name).toBe('Contato');
    expect(component.form.controls.name.value).toBe('Contato');
  });

  it('saves form and updates contact', async (): Promise<void> => {
    vi.spyOn(toast, 'success').mockImplementation(() => '' as never);

    const contact = createContact({ id: 'contact-2', name: 'João' });
    component.contactSignal.set(contact);
    component.form.patchValue({ name: 'João Silva' });

    component.save();
    await vi.runAllTimersAsync();

    expect(contactService.patch).toHaveBeenCalled();
    expect(component.contactSignal()?.name).toBe('João Silva');
    expect(toast.success).toHaveBeenCalled();
  });

  it('handles save errors', async (): Promise<void> => {
    vi.spyOn(toast, 'error').mockImplementation(() => '' as never);

    const contact = createContact({ id: 'contact-3', name: 'Ana' });
    contactService.patch.mockReturnValue(throwError(() => new Error('fail')));
    component.contactSignal.set(contact);
    component.form.patchValue({ name: 'Ana Clara' });

    component.save();
    await vi.runAllTimersAsync();

    expect(toast.error).toHaveBeenCalled();
  });
});

describe('ContactSectionComponent - Tags e Empresa', () => {
  let component: ContactSectionComponent;
  let tagService: TagServiceStub;
  let companyService: CRMCompanyServiceStub;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      imports: [ContactSectionComponent],
      providers: [
        provideNgxMask(),
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: TagService, useClass: TagServiceStub },
        { provide: CRMCompanyService, useClass: CRMCompanyServiceStub },
      ],
    });

    tagService = TestBed.inject(TagService) as unknown as TagServiceStub;
    companyService = TestBed.inject(CRMCompanyService) as unknown as CRMCompanyServiceStub;

    tagService.all.mockReturnValue(of({ data: [] }));
    companyService.list.mockReturnValue(of({ data: [] }));

    component = TestBed.createComponent(ContactSectionComponent).componentInstance;
  });

  it('deve abrir modal de tags', (): void => {
    component.isTagsModalOpen.set(false);
    component.openTagsModal();
    expect(component.isTagsModalOpen()).toBe(true);
  });

  it('deve fechar modal de tags', (): void => {
    component.isTagsModalOpen.set(true);
    component.closeTagsModal();
    expect(component.isTagsModalOpen()).toBe(false);
  });

  it('deve abrir modal de empresa', (): void => {
    component.isCompanyModalOpen.set(false);
    component.openCompanyModal();
    expect(component.isCompanyModalOpen()).toBe(true);
  });

  it('deve fechar modal de empresa', (): void => {
    component.isCompanyModalOpen.set(true);
    component.closeCompanyModal();
    expect(component.isCompanyModalOpen()).toBe(false);
  });

  it('deve exibir nome da empresa', (): void => {
    const contact = createContact({ crm_company_id: 'company-1' });
    component.contactSignal.set({
      ...contact,
      company: { id: 'company-1', name: 'Tech Corp' },
    } as unknown as Contact);

    expect(component.companyName()).toBe('Tech Corp');
  });

  it('deve exibir "Sem empresa" quando contato não tem empresa', (): void => {
    const contact = createContact({ crm_company_id: undefined });
    component.contactSignal.set(contact);

    expect(component.companyName()).toBe('Sem empresa');
  });

  it('deve renderizar tags do contato', (): void => {
    const contact: Contact = {
      ...createContact(),
      tags: [
        { id: 'tag-1', name: 'VIP' },
        { id: 'tag-2', name: 'Premium' },
      ],
    };
    component.contactSignal.set(contact);

    expect(component.contactTags().length).toBe(2);
    expect(component.contactTags()[0].name).toBe('VIP');
  });

  it('deve exibir empty state quando não há contato', (): void => {
    component.contactSignal.set(null);
    component.isLoading.set(false);

    expect(component.showEmpty()).toBe(true);
  });

  it('deve exibir loading state ao carregar contato', (): void => {
    component.isLoading.set(true);

    expect(component.showEmpty()).toBe(false);
  });

  it('deve validar CPF no formulário', (): void => {
    component.form.patchValue({ document: '123.456.789-0' });
    const documentControl = component.form.get('document');

    expect(documentControl?.valid).toBeTruthy();
  });

  it('deve limpar estado ao resetar contato', (): void => {
    component.contactSignal.set(createContact());
    component.isTagsModalOpen.set(true);
    component.isCompanyModalOpen.set(true);

    component.contact = null;

    expect(component.contactSignal()).toBeNull();
    expect(component.isLoading()).toBe(false);
  });

  it('deve emitir contactUpdated ao atualizar contato', async (): Promise<void> => {
    vi.useFakeTimers();
    vi.spyOn(component.contactUpdated, 'emit');

    const contact = createContact({ id: 'contact-4' });
    component.contactSignal.set(contact);

    const contactService = TestBed.inject(ContactService) as unknown as ContactServiceStub;
    contactService.patch.mockReturnValue(
      of({ data: { contact: { ...contact, name: 'Updated' } } }),
    );

    component.form.patchValue({ name: 'Updated' });
    component.save();
    await vi.runAllTimersAsync();

    expect(component.contactUpdated.emit).toHaveBeenCalled();
    vi.useRealTimers();
  });
});
