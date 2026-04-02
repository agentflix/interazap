import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { type ChatCampaign, ChatCampaignService } from '@core/services/chat-campaign.service';
import { ToastService } from '@core/services/toast.service';
import { CampaignsComponent } from './campaigns';

class ChatCampaignServiceStub {
  list = vi.fn();
  delete = vi.fn();
}

class ToastServiceStub {
  success = vi.fn();
  error = vi.fn();
}

class RouterStub {
  navigate = vi.fn().mockResolvedValue(true);
}

describe('CampaignsComponent', () => {
  let component: CampaignsComponent;
  let service: ChatCampaignServiceStub;
  let toast: ToastServiceStub;
  let router: RouterStub;

  const campaign: ChatCampaign = {
    id: '1',
    tenant_id: 'tenant-1',
    name: 'Campanha 1',
    status: 'draft',
    created_at: '2026-01-01T00:00:00.000Z',
    updated_at: '2026-01-01T00:00:00.000Z',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        { provide: ChatCampaignService, useClass: ChatCampaignServiceStub },
        { provide: ToastService, useClass: ToastServiceStub },
        { provide: Router, useClass: RouterStub },
      ],
    });

    service = TestBed.inject(ChatCampaignService) as unknown as ChatCampaignServiceStub;
    toast = TestBed.inject(ToastService) as unknown as ToastServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;

    service.list.mockReturnValue(
      of({
        success: true,
        data: {
          data: [campaign],
          current_page: 1,
          last_page: 1,
          per_page: 10,
          total: 1,
        },
      }),
    );
    service.delete.mockReturnValue(of(void 0));

    component = TestBed.runInInjectionContext(() => new CampaignsComponent());
  });

  it('loads campaigns on init', () => {
    component.ngOnInit();

    expect(service.list).toHaveBeenCalled();
    expect(component.campaigns().length).toBe(1);
    expect(component.isLoading()).toBe(false);
  });

  it('filters campaigns by search term', () => {
    component.onSearch('Campanha');

    expect(service.list).toHaveBeenCalledWith(
      expect.objectContaining({ search: 'Campanha', page: 1 }),
    );
  });

  it('loads selected page', () => {
    component.loadPage(2);

    expect(service.list).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }));
  });

  it('navigates to create/edit routes', () => {
    component.openCreate();
    component.openEdit(campaign);

    expect(router.navigate).toHaveBeenCalledWith(['/chat/campaigns/new']);
    expect(router.navigate).toHaveBeenCalledWith(['/chat/campaigns', '1']);
  });

  it('removes campaign after confirmation', () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.remove(campaign);

    expect(confirmSpy).toHaveBeenCalled();
    expect(service.delete).toHaveBeenCalledWith('1');
    expect(toast.success).toHaveBeenCalled();
  });

  it('does not remove campaign when confirmation is cancelled', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    component.remove(campaign);

    expect(service.delete).not.toHaveBeenCalled();
  });

  it('handles remove error', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    service.delete.mockReturnValue(throwError(() => new Error('fail')));

    component.remove(campaign);

    expect(toast.error).toHaveBeenCalled();
  });

  it('sets error state when load fails', () => {
    service.list.mockReturnValue(throwError(() => new Error('fail')));

    component.ngOnInit();

    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);
  });
});
