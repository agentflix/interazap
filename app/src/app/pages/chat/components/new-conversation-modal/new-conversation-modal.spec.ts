import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { Router } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { of, Subject, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { NewConversationModalComponent } from './new-conversation-modal';
import { ContactService } from 'src/app/core/services/contact.service';
import { CalledService } from 'src/app/core/services/called.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { InstanceService, type Instance } from 'src/app/core/services/instance.service';
import { WindowVerificationService } from './services/window-verification.service';
import { type WindowStatus } from 'src/app/core/models/window-status.model';
import { environment } from '@env/environment';
import type { Contact } from 'src/app/core/models/contact.model';
import type { TemplateSelectedEvent } from '@shared/components/template-selector/template-selector';

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

const makeContact = (overrides: Partial<Contact> = {}): Contact =>
  ({
    id: '1',
    name: 'Rafael Amor',
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }) as unknown as Contact;

const makeInstance = (overrides: Partial<Instance> = {}): Instance =>
  ({
    id: '1',
    name: 'Principal',
    connection_status: 'connected',
    ...overrides,
  }) as Instance;

const makeCalledList = (data: object[] = []) =>
  of({ data, meta: { current_page: 1, total: data.length, per_page: 1, last_page: 1 } });

class ContactServiceStub {
  list = vi.fn().mockReturnValue(of({ data: [makeContact()] }));
}

class InstanceServiceStub {
  list = vi.fn().mockReturnValue(of({ data: [makeInstance()] }));
}

class CalledServiceStub {
  list = vi.fn().mockReturnValue(makeCalledList([]));
  create = vi.fn().mockReturnValue(of({ data: { id: '99' } }));
  open = vi.fn().mockReturnValue(of({ data: { id: '99' } }));
}

class CalledMessageServiceStub {
  send = vi.fn().mockReturnValue(of({}));
}

class RouterStub {
  navigate = vi.fn().mockResolvedValue(true);
}

// ---------------------------------------------------------------------------
// Suite
// ---------------------------------------------------------------------------

describe('NewConversationModalComponent', () => {
  let component: NewConversationModalComponent;
  let fixture: ComponentFixture<NewConversationModalComponent>;
  let calledService: CalledServiceStub;
  let messageService: CalledMessageServiceStub;
  let router: RouterStub;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    localStorage.clear();

    await TestBed.configureTestingModule({
      imports: [NewConversationModalComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: InstanceService, useClass: InstanceServiceStub },
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: CalledMessageService, useClass: CalledMessageServiceStub },
        { provide: Router, useClass: RouterStub },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NewConversationModalComponent);
    component = fixture.componentInstance;
    calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;
    messageService = TestBed.inject(CalledMessageService) as unknown as CalledMessageServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;
    httpMock = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    httpMock.verify();
  });

  // -------------------------------------------------------------------------
  // open() / close()
  // -------------------------------------------------------------------------

  it('open() sets isOpen to true', () => {
    component.open();
    expect(component.isOpen()).toBe(true);
  });

  it('open() resets previous form state', () => {
    component.selectedContactId.set('existing');
    component.startMessage.set('msg');
    component.startError.set('err');
    component.open();
    expect(component.selectedContactId()).toBe('');
    expect(component.startMessage()).toBe('');
    expect(component.startError()).toBeNull();
  });

  it('open() loads instances', () => {
    const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    component.open();
    expect(instanceService.list).toHaveBeenCalled();
  });

  it('close() sets isOpen to false and resets state', () => {
    component.open();
    component.selectContact('1');
    component.close();
    expect(component.isOpen()).toBe(false);
    expect(component.selectedContactId()).toBe('');
    expect(component.contacts()).toEqual([]);
  });

  // -------------------------------------------------------------------------
  // selectContact()
  // -------------------------------------------------------------------------

  it('selectContact() stores contact id as string', () => {
    component.selectContact(42);
    expect(component.selectedContactId()).toBe('42');
  });

  // -------------------------------------------------------------------------
  // Instance loading
  // -------------------------------------------------------------------------

  it('loads only connected instances', () => {
    const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    instanceService.list.mockReturnValue(
      of({
        data: [
          makeInstance({ id: '1', connection_status: 'connected' }),
          makeInstance({ id: '2', connection_status: 'disconnected' }),
        ],
      }),
    );
    component.open();
    expect(component.instances().length).toBe(1);
    expect(component.instances()[0].id).toBe('1');
  });

  it('sets instancesError on instance load failure', () => {
    const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    instanceService.list.mockReturnValue(throwError(() => new Error('network')));
    component.open();
    expect(component.instancesError()).toBeTruthy();
  });

  it('auto-selects single instance', () => {
    component.open();
    expect(component.selectedInstanceId()).toBe('1');
  });

  it('showInstanceEmptyState is true when no connected instances', () => {
    const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    instanceService.list.mockReturnValue(of({ data: [] }));
    component.open();
    expect(component.showInstanceEmptyState()).toBe(true);
  });

  // -------------------------------------------------------------------------
  // Contact search
  // -------------------------------------------------------------------------

  it('isContactSearchReady is false for short terms', () => {
    component.contactSearchControl.setValue('ab');
    expect(component.isContactSearchReady()).toBe(false);
  });

  it('isContactSearchReady is true for 3+ char terms', () => {
    component.contactSearchControl.setValue('raf');
    expect(component.isContactSearchReady()).toBe(true);
  });

  it('contact search clears results for short terms', async () => {
    component.contacts.set([makeContact()]);
    component.contactSearchControl.setValue('ab');
    await new Promise((resolve) => setTimeout(resolve, 300));
    expect(component.contacts()).toEqual([]);
  });

  // -------------------------------------------------------------------------
  // startChat() — validation
  // -------------------------------------------------------------------------

  it('startChat() sets error when no contact selected', () => {
    component.open();
    component.selectedContactId.set('');
    component.startChat();
    expect(component.startError()).toContain('contato');
  });

  it('startChat() sets error when no message', () => {
    component.open();
    component.selectContact('1');
    component.startMessage.set('');
    component.startChat();
    expect(component.startError()).toContain('mensagem');
  });

  // -------------------------------------------------------------------------
  // startChat() — new ticket flow
  // -------------------------------------------------------------------------

  it('startChat() creates ticket, sends message and navigates', () => {
    calledService.list.mockReturnValue(makeCalledList([])); // no existing ticket
    calledService.create.mockReturnValue(of({ data: { id: '99' } }));
    messageService.send.mockReturnValue(of({}));

    component.open();
    component.selectContact('1');
    component.startMessage.set('Olá!');
    component.startChat();

    expect(calledService.create).toHaveBeenCalled();
    expect(messageService.send).toHaveBeenCalledWith('99', 'Olá!');
    expect(router.navigate).toHaveBeenCalledWith(['/chat', '99']);
  });

  // -------------------------------------------------------------------------
  // startChat() — reuse existing ticket (free text)
  // -------------------------------------------------------------------------

  it('startChat() reuses existing open ticket without creating a new one', () => {
    calledService.list
      .mockReturnValueOnce(makeCalledList([{ id: '55' }])) // open → found
      .mockReturnValue(makeCalledList([]));

    component.open();
    component.selectContact('1');
    component.startMessage.set('Oi');
    component.startChat();

    expect(calledService.create).not.toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/chat', '55']);
  });

  // -------------------------------------------------------------------------
  // startChat() — template with existing ticket (Bug 2 fix)
  // -------------------------------------------------------------------------

  it('startChat() sends template when ticket exists and mode is template', () => {
    calledService.list
      .mockReturnValueOnce(makeCalledList([{ id: '55' }])) // open → found
      .mockReturnValue(makeCalledList([]));

    const mockTemplate: TemplateSelectedEvent = {
      templateName: 'welcome_message',
      parameters: { name: 'Rafael' },
    };

    component.open();
    component.selectContact('1');
    component.sendMode.set('template');
    component.selectedTemplate.set(mockTemplate);
    component.startChat();

    // Verify HTTP request was made to template endpoint
    const req = httpMock.expectOne(`${environment.apiUrl}/chat/tickets/55/messages/template`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      template_name: 'welcome_message',
      variables: { name: 'Rafael' },
    });
    req.flush({});

    // Should NOT have created a new ticket or sent free text
    expect(calledService.create).not.toHaveBeenCalled();
    expect(messageService.send).not.toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/chat', '55']);
  });

  // -------------------------------------------------------------------------
  // startChat() — error handling
  // -------------------------------------------------------------------------

  it('startChat() sets startError and stops loading on API failure', () => {
    calledService.list.mockReturnValue(makeCalledList([]));
    calledService.create.mockReturnValue(throwError(() => new Error('fail')));

    component.open();
    component.selectContact('1');
    component.startMessage.set('Olá!');
    component.startChat();

    expect(component.isStarting()).toBe(false);
    expect(component.startError()).toBeTruthy();
  });

  // -------------------------------------------------------------------------
  // Computed: requiresInstanceSelection
  // -------------------------------------------------------------------------

  it('requiresInstanceSelection is true when multiple instances and none selected', () => {
    const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    instanceService.list.mockReturnValue(
      of({
        data: [
          makeInstance({ id: '1', connection_status: 'connected' }),
          makeInstance({ id: '2', connection_status: 'connected' }),
        ],
      }),
    );
    component.open();
    component.instanceControl.setValue('', { emitEvent: false });
    component.selectedInstanceId.set('');
    expect(component.requiresInstanceSelection()).toBe(true);
  });

  // -------------------------------------------------------------------------
  // Re-verificação da janela Meta ao trocar de instância (TASK-4.1.3)
  // -------------------------------------------------------------------------

  describe('re-verificação da janela ao trocar de instância', () => {
    const makeWindowStatus = (overrides: Partial<WindowStatus> = {}): WindowStatus => ({
      canSendFreeText: true,
      lastMessageAt: null,
      expiresAt: null,
      windowType: null,
      ...overrides,
    });

    it('invalida o cache e reconsulta a janela ao trocar entre duas instâncias Meta', () => {
      const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
      instanceService.list.mockReturnValue(
        of({
          data: [
            makeInstance({ id: '1', connection_status: 'connected', provider: 'meta' }),
            makeInstance({ id: '2', connection_status: 'connected', provider: 'meta' }),
          ],
        }),
      );

      const windowVerificationService = TestBed.inject(WindowVerificationService);
      const invalidateSpy = vi.spyOn(windowVerificationService, 'invalidateCache');
      const checkStatusSpy = vi
        .spyOn(windowVerificationService, 'checkStatus')
        .mockReturnValue(of(makeWindowStatus()));

      component.open();
      fixture.detectChanges();

      component.selectContact('contact-1');
      fixture.detectChanges();
      // Ainda sem instância selecionada (duas conectadas, nenhuma auto-selecionada) — não dispara.
      expect(checkStatusSpy).not.toHaveBeenCalled();

      component.instanceControl.setValue('1');
      fixture.detectChanges();
      expect(invalidateSpy).toHaveBeenCalledWith('contact-1');
      expect(checkStatusSpy).toHaveBeenCalledWith('contact-1', null, '1');
      expect(checkStatusSpy).toHaveBeenCalledTimes(1);

      component.instanceControl.setValue('2');
      fixture.detectChanges();
      expect(invalidateSpy).toHaveBeenCalledTimes(2);
      expect(checkStatusSpy).toHaveBeenCalledWith('contact-1', null, '2');
      expect(checkStatusSpy).toHaveBeenCalledTimes(2);
    });

    it('não consulta a janela quando o provider da instância não é meta', () => {
      const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
      instanceService.list.mockReturnValue(
        of({ data: [makeInstance({ id: '1', connection_status: 'connected', provider: 'uazapi' })] }),
      );

      const windowVerificationService = TestBed.inject(WindowVerificationService);
      const invalidateSpy = vi.spyOn(windowVerificationService, 'invalidateCache');
      const checkStatusSpy = vi.spyOn(windowVerificationService, 'checkStatus');

      component.open();
      fixture.detectChanges();

      component.selectContact('contact-1');
      fixture.detectChanges();

      expect(invalidateSpy).not.toHaveBeenCalled();
      expect(checkStatusSpy).not.toHaveBeenCalled();
      expect(component.sendMode()).toBe('freeText');
    });

    it('atualiza sendMode para template quando a janela Meta está fechada', () => {
      const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
      instanceService.list.mockReturnValue(
        of({ data: [makeInstance({ id: '1', connection_status: 'connected', provider: 'meta' })] }),
      );

      const windowVerificationService = TestBed.inject(WindowVerificationService);
      vi.spyOn(windowVerificationService, 'invalidateCache');
      vi.spyOn(windowVerificationService, 'checkStatus').mockReturnValue(
        of(makeWindowStatus({ canSendFreeText: false })),
      );

      component.open();
      fixture.detectChanges();

      component.selectContact('contact-1');
      fixture.detectChanges();

      expect(component.sendMode()).toBe('template');
    });

    it('descarta resposta obsoleta da instância anterior (ordem invertida de respostas)', () => {
      const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
      instanceService.list.mockReturnValue(
        of({
          data: [
            makeInstance({ id: '1', connection_status: 'connected', provider: 'meta' }),
            makeInstance({ id: '2', connection_status: 'connected', provider: 'meta' }),
          ],
        }),
      );

      const windowVerificationService = TestBed.inject(WindowVerificationService);
      vi.spyOn(windowVerificationService, 'invalidateCache');

      const subjectInst1 = new Subject<WindowStatus>();
      const subjectInst2 = new Subject<WindowStatus>();
      const checkStatusSpy = vi
        .spyOn(windowVerificationService, 'checkStatus')
        .mockReturnValueOnce(subjectInst1.asObservable())
        .mockReturnValueOnce(subjectInst2.asObservable());

      component.open();
      fixture.detectChanges();

      // Seleciona contato + instância 1 → consulta disparada (resposta em voo).
      component.selectContact('contact-1');
      component.selectedInstanceId.set('1');
      fixture.detectChanges();
      expect(checkStatusSpy).toHaveBeenCalledTimes(1);

      // Troca para instância 2 ANTES da resposta da 1 chegar → nova consulta.
      component.selectedInstanceId.set('2');
      fixture.detectChanges();
      expect(checkStatusSpy).toHaveBeenCalledTimes(2);

      // Resposta da instância 2 chega primeiro: janela fechada → template.
      subjectInst2.next(makeWindowStatus({ canSendFreeText: false }));
      expect(component.sendMode()).toBe('template');

      // Resposta TARDIA da instância 1: janela aberta — NÃO pode sobrescrever.
      subjectInst1.next(makeWindowStatus({ canSendFreeText: true }));
      expect(component.sendMode()).toBe('template');
    });
  });
});
