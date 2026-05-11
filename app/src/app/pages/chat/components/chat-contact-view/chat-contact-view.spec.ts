import { describe, it, expect, beforeEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { of, throwError } from 'rxjs';
import { toast } from 'ngx-sonner';
import { ChatContactView } from './chat-contact-view';
import { type Contact } from 'src/app/core/models/contact.model';
import { ContactService } from 'src/app/core/services/contact.service';
import { CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { type CalledContactSummary } from 'src/app/core/services/called.service';

class ContactServiceStub {
  find = vi.fn();
  update = vi.fn();
}

class CRMCompanyServiceStub {
  list = vi.fn();
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

describe('ChatContactView', (): void => {
  let component: ChatContactView;
  let contactService: ContactServiceStub;
  let crmCompanyService: CRMCompanyServiceStub;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      imports: [ReactiveFormsModule],
      providers: [
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: CRMCompanyService, useClass: CRMCompanyServiceStub },
      ],
    });

    contactService = TestBed.inject(ContactService) as unknown as ContactServiceStub;
    crmCompanyService = TestBed.inject(CRMCompanyService) as unknown as CRMCompanyServiceStub;

    crmCompanyService.list.mockReturnValue(of({ data: [] }));
    contactService.find.mockReturnValue(of({ data: { contact: createContact() } }));

    component = TestBed.runInInjectionContext(() => new ChatContactView());
  });

  it('resets state when contact input is null', (): void => {
    const summary: CalledContactSummary = {
      id: 'contact-1',
      name: 'Ana',
      email: 'ana@interazap.test',
      phone: '11999990000',
      whatsapp: '+5511999990000',
    };

    component.contact = summary;
    component.contact = null;

    expect(component.contactId()).toBeNull();
    expect(component.contactSignal()).toBeNull();
    expect(component.attemptedSubmit()).toBe(false);
    expect(component.form.getRawValue().name).toBe('');
  });

  it('loads full contact details and patches the form', async (): Promise<void> => {
    const summary: CalledContactSummary = {
      id: 'contact-2',
      name: 'Bia',
      email: 'bia@interazap.test',
      phone: '11999990000',
      whatsapp: '+5511999990000',
    };

    const fullContact = createContact({
      id: 'contact-2',
      name: 'Beatriz',
      email: 'bia@interazap.test',
      whatsapp: '+5511999990000',
      custom_fields: { role: 'Support', notes: 'VIP' },
    });

    contactService.find.mockReturnValue(of({ data: { contact: fullContact } }));

    component.contact = summary;
    await Promise.resolve();

    expect(component.isLoading()).toBe(false);
    expect(component.contactSignal()).toEqual(fullContact);
    expect(component.form.controls.name.value).toBe('Beatriz');
    expect(component.form.controls.email.value).toBe('bia@interazap.test');
    expect(component.form.controls.role.value).toBe('Support');
  });

  it('prevents save when form is invalid', (): void => {
    vi.spyOn(toast, 'error').mockImplementation(() => '' as never);

    contactService.find.mockReturnValue(
      of({ data: { contact: createContact({ name: '', whatsapp: '' }) } }),
    );

    const summary: CalledContactSummary = {
      id: 'contact-3',
      name: '',
      email: null,
      phone: null,
      whatsapp: undefined,
    };

    component.contact = summary;
    component.save();

    expect(contactService.update).not.toHaveBeenCalled();
    expect(toast.error).toHaveBeenCalled();
    expect(component.isSaving()).toBe(false);
  });

  it('saves contact updates and emits changes', async (): Promise<void> => {
    vi.spyOn(toast, 'success').mockImplementation(() => '' as never);

    const summary: CalledContactSummary = {
      id: 'contact-4',
      name: 'Carlos',
      email: 'carlos@interazap.test',
      phone: '11999990000',
      whatsapp: undefined,
    };

    const existingContact = createContact({
      id: 'contact-4',
      name: 'Carlos',
      whatsapp: undefined,
      custom_fields: { role: 'Old role', notes: 'Old notes' },
    });

    const updatedContact = createContact({
      id: 'contact-4',
      name: 'Carlos Silva',
      whatsapp: '+5511999990000',
      custom_fields: { role: 'Support', notes: 'Priority' },
    });

    contactService.update.mockReturnValue(of({ data: { contact: updatedContact } }));

    contactService.find.mockReturnValue(of({ data: { contact: existingContact } }));
    component.contactSignal.set(existingContact);
    component.contact = summary;
    await Promise.resolve();

    component.form.patchValue({
      name: 'Carlos Silva',
      whatsapp: '11 99999-0000',
      email: 'carlos@interazap.test',
      role: 'Support',
      notes: 'Priority',
    });

    const emitSpy = vi.spyOn(component.contactUpdated, 'emit');

    component.save();

    const [id, payload] = contactService.update.mock.calls[
      contactService.update.mock.calls.length - 1
    ] as [string | number, Partial<Contact>];

    expect(id).toBe('contact-4');
    expect(payload.whatsapp).toBe('+5511999990000');
    expect(payload.custom_fields).toEqual({ role: 'Support', notes: 'Priority' });
    expect(component.isSaving()).toBe(false);
    expect(emitSpy).toHaveBeenCalledWith(updatedContact);
    expect(toast.success).toHaveBeenCalled();
  });

  it('handles update failure with toast feedback', (): void => {
    vi.spyOn(toast, 'error').mockImplementation(() => '' as never);

    const summary: CalledContactSummary = {
      id: 'contact-5',
      name: 'Dani',
      email: 'dani@interazap.test',
      phone: '11999990000',
      whatsapp: '+5511999990000',
    };

    contactService.update.mockReturnValue(throwError((): Error => new Error('fail')));

    contactService.find.mockReturnValue(
      of({ data: { contact: createContact({ id: 'contact-5' }) } }),
    );
    component.contact = summary;
    component.form.patchValue({
      name: 'Dani',
      whatsapp: '11 99999-0000',
    });

    component.save();

    expect(component.isSaving()).toBe(false);
    expect(toast.error).toHaveBeenCalled();
  });
});
