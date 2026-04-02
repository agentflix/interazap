import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { QuickAnswers } from './quick-answers';
import { type QuickAnswer, QuickAnswerService } from '@core/services/quick-answer.service';
import { ToastService } from '@core/services/toast.service';

class QuickAnswerServiceStub {
  list = vi.fn();
  delete = vi.fn();
}

class ToastServiceStub {
  success = vi.fn();
  error = vi.fn();
}

describe('QuickAnswers', () => {
  let component: QuickAnswers;
  let service: QuickAnswerServiceStub;
  let toast: ToastServiceStub;

  const item: QuickAnswer = {
    id: 'qa-1',
    name: 'Saudação',
    content: 'Olá!',
    shortcut: 'oi',
    is_active: true,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        { provide: QuickAnswerService, useClass: QuickAnswerServiceStub },
        { provide: ToastService, useClass: ToastServiceStub },
      ],
    });

    service = TestBed.inject(QuickAnswerService) as unknown as QuickAnswerServiceStub;
    toast = TestBed.inject(ToastService) as unknown as ToastServiceStub;

    service.list.mockReturnValue(
      of({
        data: [item],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      }),
    );
    service.delete.mockReturnValue(of(void 0));

    component = TestBed.runInInjectionContext(() => new QuickAnswers());
  });

  it('loads items on init', () => {
    component.ngOnInit();

    expect(service.list).toHaveBeenCalled();
    expect(component.quickAnswers().length).toBe(1);
    expect(component.isLoading()).toBe(false);
    expect(component.hasError()).toBe(false);
  });

  it('searches and resets to first page', () => {
    component.onSearch('oi');

    expect(service.list).toHaveBeenCalledWith(expect.objectContaining({ search: 'oi', page: 1 }));
  });

  it('loads a specific page', () => {
    component.loadPage(2);

    expect(service.list).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }));
  });

  it('deletes selected item and reloads list', () => {
    component.ngOnInit();
    service.list.mockClear();

    component.openDelete(item);
    component.handleDeleteConfirmed();

    expect(service.delete).toHaveBeenCalledWith('qa-1');
    expect(toast.success).toHaveBeenCalled();
    expect(service.list).toHaveBeenCalled();
  });

  it('handles delete error', () => {
    service.delete.mockReturnValue(throwError(() => new Error('fail')));

    component.openDelete(item);
    component.handleDeleteConfirmed();

    expect(toast.error).toHaveBeenCalled();
    expect(component.showDeleteModal()).toBe(true);
  });

  it('sets error state when load fails', () => {
    service.list.mockReturnValue(throwError(() => new Error('fail')));

    component.ngOnInit();

    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);
  });
});
