import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { type ChatTransmissionList, ChatTransmissionListService } from '@core/services/chat-transmission-list.service';
import { ToastService } from '@core/services/toast.service';
import { ChatTransmissionListComponent } from './chat-transmission-list';

class ChatTransmissionListServiceStub {
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

describe('ChatTransmissionListComponent', () => {
  let component: ChatTransmissionListComponent;
  let service: ChatTransmissionListServiceStub;
  let toast: ToastServiceStub;
  let router: RouterStub;

  const transmissionList: ChatTransmissionList = {
    id: '1',
    tenant_id: 'tenant-1',
    name: 'Lista 1',
    status: 'draft',
    created_at: '2026-01-01T00:00:00.000Z',
    updated_at: '2026-01-01T00:00:00.000Z',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        { provide: ChatTransmissionListService, useClass: ChatTransmissionListServiceStub },
        { provide: ToastService, useClass: ToastServiceStub },
        { provide: Router, useClass: RouterStub },
      ],
    });

    service = TestBed.inject(ChatTransmissionListService) as unknown as ChatTransmissionListServiceStub;
    toast = TestBed.inject(ToastService) as unknown as ToastServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;

    service.list.mockReturnValue(
      of({
        success: true,
        data: {
          data: [transmissionList],
          current_page: 1,
          last_page: 1,
          per_page: 10,
          total: 1,
        },
      }),
    );
    service.delete.mockReturnValue(of(void 0));

    component = TestBed.runInInjectionContext(() => new ChatTransmissionListComponent());
  });

  it('loads transmission lists on init', () => {
    component.ngOnInit();

    expect(service.list).toHaveBeenCalled();
    expect(component.transmissionLists().length).toBe(1);
    expect(component.isLoading()).toBe(false);
  });

  it('filters transmission lists by search term', () => {
    component.onSearch('Lista');

    expect(service.list).toHaveBeenCalledWith(
      expect.objectContaining({ search: 'Lista', page: 1 }),
    );
  });

  it('loads selected page', () => {
    component.loadPage(2);

    expect(service.list).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }));
  });

  it('navigates to create/edit routes', () => {
    component.openCreate();
    component.openEdit(transmissionList);

    expect(router.navigate).toHaveBeenCalledWith(['/chat/transmission-list/new']);
    expect(router.navigate).toHaveBeenCalledWith(['/chat/transmission-list', '1']);
  });

  it('removes transmission list after confirmation', () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.remove(transmissionList);

    expect(confirmSpy).toHaveBeenCalled();
    expect(service.delete).toHaveBeenCalledWith('1');
    expect(toast.success).toHaveBeenCalled();
  });

  it('does not remove transmission list when confirmation is cancelled', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    component.remove(transmissionList);

    expect(service.delete).not.toHaveBeenCalled();
  });

  it('handles remove error', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    service.delete.mockReturnValue(throwError(() => new Error('fail')));

    component.remove(transmissionList);

    expect(toast.error).toHaveBeenCalled();
  });

  it('sets error state when load fails', () => {
    service.list.mockReturnValue(throwError(() => new Error('fail')));

    component.ngOnInit();

    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);
  });
});
