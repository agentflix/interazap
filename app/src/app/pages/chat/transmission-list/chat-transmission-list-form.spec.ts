import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { Subject, of, throwError } from 'rxjs';
import {
  type ChatTransmissionList,
  type ChatTransmissionListPayload,
  ChatTransmissionListService,
} from '@core/services/chat-transmission-list.service';
import {
  type Integration,
  type IntegrationFilters,
  IntegrationService,
} from '@core/services/integration.service';
import { ToastService } from '@core/services/toast.service';
import { ChatTransmissionListFormComponent } from './chat-transmission-list-form';

class ChatTransmissionListServiceStub {
  show = vi.fn();
  create = vi.fn();
  update = vi.fn();
  preview = vi.fn();
  audience = vi.fn();
}

class IntegrationServiceStub {
  list = vi.fn();
}

class ToastServiceStub {
  success = vi.fn();
  error = vi.fn();
}

class RouterStub {
  navigate = vi.fn().mockResolvedValue(true);
}

describe('ChatTransmissionListFormComponent', () => {
  let component: ChatTransmissionListFormComponent;
  let transmissionListService: ChatTransmissionListServiceStub;
  let integrationService: IntegrationServiceStub;
  let toast: ToastServiceStub;
  let router: RouterStub;
  let paramMap$: Subject<ReturnType<typeof convertToParamMap>>;

  const integration: Integration = {
    id: 'instance-1',
    name: 'Instance 1',
    provider: 'whatsapp',
    is_active: true,
    settings: {
      channel_provider_id: 1,
      cellphone: '5511999999999',
    },
  };

  const transmissionList: ChatTransmissionList = {
    id: 'transmission-list-1',
    tenant_id: 'tenant-1',
    name: 'Lista existente',
    message: 'Olá {{name}}',
    instance_id: 'instance-1',
    filter_criteria: { tags: ['vip'], status: 'active', company_id: null as never },
    status: 'draft',
    created_at: '2026-01-01T00:00:00.000Z',
    updated_at: '2026-01-01T00:00:00.000Z',
  };

  beforeEach(() => {
    paramMap$ = new Subject<ReturnType<typeof convertToParamMap>>();

    TestBed.configureTestingModule({
      providers: [
        { provide: ChatTransmissionListService, useClass: ChatTransmissionListServiceStub },
        { provide: IntegrationService, useClass: IntegrationServiceStub },
        { provide: ToastService, useClass: ToastServiceStub },
        { provide: Router, useClass: RouterStub },
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: paramMap$.asObservable(),
          },
        },
      ],
    });

    transmissionListService = TestBed.inject(ChatTransmissionListService) as unknown as ChatTransmissionListServiceStub;
    integrationService = TestBed.inject(IntegrationService) as unknown as IntegrationServiceStub;
    toast = TestBed.inject(ToastService) as unknown as ToastServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;

    integrationService.list.mockImplementation((_params: IntegrationFilters) =>
      of({
        data: [integration],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      }),
    );
    transmissionListService.audience.mockReturnValue(of({ count: 10 }));
    transmissionListService.preview.mockReturnValue(
      of({ original: 'Olá', preview: 'Olá João', vars_detected: ['name'], sample_contact: null }),
    );
    transmissionListService.create.mockImplementation((payload: ChatTransmissionListPayload) =>
      of({ data: { ...transmissionList, id: 'created', ...payload } }),
    );
    transmissionListService.update.mockImplementation((id: string, payload: Partial<ChatTransmissionListPayload>) =>
      of({ data: { ...transmissionList, id, ...payload } }),
    );
    transmissionListService.show.mockReturnValue(of({ data: transmissionList }));

    component = TestBed.runInInjectionContext(() => new ChatTransmissionListFormComponent());
  });

  it('loads initial data for create flow', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    expect(integrationService.list).toHaveBeenCalled();
    expect(component.instances().length).toBe(1);
    expect(component.form.controls.instance_id.value).toBe('instance-1');
    expect(transmissionListService.audience).toHaveBeenCalled();
  });

  it('loads transmission list data for edit flow', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'transmission-list-1' }));

    expect(component.isEditing()).toBe(true);
    expect(transmissionListService.show).toHaveBeenCalledWith('transmission-list-1');
    expect(component.form.controls.name.value).toBe('Lista existente');
  });

  it('updates preview when message changes', () => {
    vi.useFakeTimers();

    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.controls.message.setValue('Mensagem com preview');
    vi.advanceTimersByTime(600);

    expect(transmissionListService.preview).toHaveBeenCalledWith('Mensagem com preview');
    expect(component.previewMessage()).toBe('Olá João');

    vi.useRealTimers();
  });

  it('validates required fields before submit', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.controls.name.setValue('');
    component.save();

    expect(toast.error).toHaveBeenCalled();
    expect(transmissionListService.create).not.toHaveBeenCalled();
  });

  it('creates a transmission list', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.patchValue({
      name: 'Nova lista',
      instance_id: 'instance-1',
      message: 'Mensagem com conteúdo suficiente',
      scheduled_at: null,
      filter_status: 'active',
      filter_tags: ['vip'],
    });

    component.save();

    expect(transmissionListService.create).toHaveBeenCalled();
    expect(toast.success).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/chat/transmission-list']);
  });

  it('updates a transmission list in edit mode', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'transmission-list-1' }));

    component.form.patchValue({
      name: 'Lista editada',
      instance_id: 'instance-1',
      message: 'Mensagem editada com tamanho válido',
      filter_status: 'active',
      filter_tags: ['vip'],
    });

    component.save();

    expect(transmissionListService.update).toHaveBeenCalledWith(
      'transmission-list-1',
      expect.objectContaining({ name: 'Lista editada' }),
    );
  });

  it('handles save error', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.patchValue({
      name: 'Nova lista',
      instance_id: 'instance-1',
      message: 'Mensagem com conteúdo suficiente',
    });
    transmissionListService.create.mockReturnValue(throwError(() => new Error('fail')));

    component.save();

    expect(toast.error).toHaveBeenCalled();
    expect(component.isSubmitting()).toBe(false);
  });

  it('inserts variable token into message', () => {
    component.form.controls.message.setValue('Olá');

    component.insertVariable('{{name}}');

    expect(component.form.controls.message.value).toContain('{{name}}');
  });
});
