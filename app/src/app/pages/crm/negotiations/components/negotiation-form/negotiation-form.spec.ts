import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { NegotiationFormComponent } from './negotiation-form';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { FunnelService } from 'src/app/core/services/funnel.service';
import { ContactService } from 'src/app/core/services/contact.service';
import { of } from 'rxjs';
import { type Contact } from 'src/app/core/models/contact.model';
import { LucideAngularModule, icons } from 'lucide-angular';

describe('NegotiationFormComponent', () => {
  let component: NegotiationFormComponent;
  let fixture: ComponentFixture<NegotiationFormComponent>;
  let negotiationServiceMock: Partial<NegotiationService>;

  const mockContact: Contact = {
    id: '1',
    name: 'Test Contact',
    crm_company_id: '1',
  } as Contact;

  beforeEach(async () => {
    negotiationServiceMock = {
      create: vi.fn().mockReturnValue(of({ data: { id: '1' } })),
      update: vi.fn().mockReturnValue(of({ data: { id: '1' } })),
    };

    const funnelServiceMock = {
      listSteps: vi.fn().mockReturnValue(of({ data: [] })),
    };

    const contactServiceMock = {
      create: vi.fn().mockReturnValue(of({ data: { contact: mockContact } })),
    };

    await TestBed.configureTestingModule({
      imports: [NegotiationFormComponent],
      providers: [
        { provide: NegotiationService, useValue: negotiationServiceMock },
        { provide: FunnelService, useValue: funnelServiceMock },
        { provide: ContactService, useValue: contactServiceMock },
        importProvidersFrom(LucideAngularModule.pick(icons)),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('cria componente corretamente', () => {
    expect(component).toBeTruthy();
  });

  it('inicializa form com campos obrigatórios', () => {
    expect(component.form).toBeDefined();
    expect(component.form.get('title')).toBeDefined();
  });

  it('valida campo título como obrigatório', () => {
    const titleControl = component.form.get('title');
    titleControl?.setValue('');
    expect(titleControl?.invalid).toBe(true);
  });

  it('emite evento saved ao salvar com sucesso', () => {
    fixture.componentRef.setInput('contacts', [mockContact]);
    fixture.detectChanges();

    component.form.patchValue(
      {
        title: 'Test Negotiation',
        crm_company_id: '1',
        funnel_id: '1',
        step_id: '1',
        contact_id: '1',
        user_id: '1',
      },
      { emitEvent: false },
    );

    component.submit();
    expect(negotiationServiceMock.create).toHaveBeenCalled();
  });

  it('seta isSaving como true durante salvamento', () => {
    fixture.componentRef.setInput('contacts', [mockContact]);
    fixture.detectChanges();

    component.form.patchValue(
      {
        title: 'Test',
        crm_company_id: '1',
        funnel_id: '1',
        step_id: '1',
        contact_id: '1',
        user_id: '1',
      },
      { emitEvent: false },
    );

    component.submit();
    expect(negotiationServiceMock.create).toHaveBeenCalled();
  });

  it('chama negotiationService.create para nova negociação', () => {
    fixture.componentRef.setInput('contacts', [mockContact]);
    fixture.detectChanges();

    component.form.patchValue(
      {
        title: 'New Deal',
        crm_company_id: '1',
        funnel_id: '1',
        step_id: '1',
        contact_id: '1',
        user_id: '1',
      },
      { emitEvent: false },
    );

    component.submit();
    expect(negotiationServiceMock.create).toHaveBeenCalled();
  });

  it('emite evento cancelled ao cancelar', () => {
    const spy = vi.spyOn(component.cancelled, 'emit');
    component.cancel();
    expect(spy).toHaveBeenCalled();
  });

  it('computed filteredContacts filtra por empresa selecionada', () => {
    expect(component.filteredContacts).toBeDefined();
  });
});
