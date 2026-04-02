import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { Subject, of, throwError } from 'rxjs';
import {
  type ChatCampaign,
  type ChatCampaignPayload,
  ChatCampaignService,
} from '@core/services/chat-campaign.service';
import {
  type Integration,
  type IntegrationFilters,
  IntegrationService,
} from '@core/services/integration.service';
import { ToastService } from '@core/services/toast.service';
import { CampaignFormComponent } from './campaign-form';

class ChatCampaignServiceStub {
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

describe('CampaignFormComponent', () => {
  let component: CampaignFormComponent;
  let campaignService: ChatCampaignServiceStub;
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
      integration_id: 1,
      cellphone: '5511999999999',
    },
  };

  const campaign: ChatCampaign = {
    id: 'campaign-1',
    tenant_id: 'tenant-1',
    name: 'Campanha existente',
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
        { provide: ChatCampaignService, useClass: ChatCampaignServiceStub },
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

    campaignService = TestBed.inject(ChatCampaignService) as unknown as ChatCampaignServiceStub;
    integrationService = TestBed.inject(IntegrationService) as unknown as IntegrationServiceStub;
    toast = TestBed.inject(ToastService) as unknown as ToastServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;

    integrationService.list.mockImplementation((_params: IntegrationFilters) =>
      of({
        data: [integration],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      }),
    );
    campaignService.audience.mockReturnValue(of({ count: 10 }));
    campaignService.preview.mockReturnValue(
      of({ original: 'Olá', preview: 'Olá João', vars_detected: ['name'], sample_contact: null }),
    );
    campaignService.create.mockImplementation((payload: ChatCampaignPayload) =>
      of({ data: { ...campaign, id: 'created', ...payload } }),
    );
    campaignService.update.mockImplementation((id: string, payload: Partial<ChatCampaignPayload>) =>
      of({ data: { ...campaign, id, ...payload } }),
    );
    campaignService.show.mockReturnValue(of({ data: campaign }));

    component = TestBed.runInInjectionContext(() => new CampaignFormComponent());
  });

  it('loads initial data for create flow', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    expect(integrationService.list).toHaveBeenCalled();
    expect(component.instances().length).toBe(1);
    expect(component.form.controls.instance_id.value).toBe('instance-1');
    expect(campaignService.audience).toHaveBeenCalled();
  });

  it('loads campaign data for edit flow', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'campaign-1' }));

    expect(component.isEditing()).toBe(true);
    expect(campaignService.show).toHaveBeenCalledWith('campaign-1');
    expect(component.form.controls.name.value).toBe('Campanha existente');
  });

  it('updates preview when message changes', () => {
    vi.useFakeTimers();

    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.controls.message.setValue('Mensagem com preview');
    vi.advanceTimersByTime(600);

    expect(campaignService.preview).toHaveBeenCalledWith('Mensagem com preview');
    expect(component.previewMessage()).toBe('Olá João');

    vi.useRealTimers();
  });

  it('validates required fields before submit', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.controls.name.setValue('');
    component.save();

    expect(toast.error).toHaveBeenCalled();
    expect(campaignService.create).not.toHaveBeenCalled();
  });

  it('creates a campaign', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.patchValue({
      name: 'Nova campanha',
      instance_id: 'instance-1',
      message: 'Mensagem com conteúdo suficiente',
      scheduled_at: null,
      filter_status: 'active',
      filter_tags: ['vip'],
    });

    component.save();

    expect(campaignService.create).toHaveBeenCalled();
    expect(toast.success).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/chat/campaigns']);
  });

  it('updates a campaign in edit mode', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'campaign-1' }));

    component.form.patchValue({
      name: 'Campanha editada',
      instance_id: 'instance-1',
      message: 'Mensagem editada com tamanho válido',
      filter_status: 'active',
      filter_tags: ['vip'],
    });

    component.save();

    expect(campaignService.update).toHaveBeenCalledWith(
      'campaign-1',
      expect.objectContaining({ name: 'Campanha editada' }),
    );
  });

  it('handles save error', () => {
    component.ngOnInit();
    paramMap$.next(convertToParamMap({ id: 'new' }));

    component.form.patchValue({
      name: 'Nova campanha',
      instance_id: 'instance-1',
      message: 'Mensagem com conteúdo suficiente',
    });
    campaignService.create.mockReturnValue(throwError(() => new Error('fail')));

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
