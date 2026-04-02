import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { type Contact } from 'src/app/core/models/contact.model';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatStore } from './chat.store';

describe('ChatStore', () => {
  let store: ChatStore;
  let calledServiceSpy: {
    get: ReturnType<typeof vi.fn>;
    open: ReturnType<typeof vi.fn>;
  };
  let messageServiceSpy: Record<string, never>;
  let chatRefreshSpy: {
    request: ReturnType<typeof vi.fn>;
  };

  const createCalled = (overrides: Partial<Called> = {}): Called => ({
    id: overrides.id ?? '123',
    company_id: overrides.company_id ?? 'company-1',
    status: overrides.status ?? 'pending',
    channel: overrides.channel ?? 'whatsapp',
    ...overrides,
  });

  beforeEach(() => {
    calledServiceSpy = {
      get: vi.fn(),
      open: vi.fn(),
    };
    messageServiceSpy = {};
    chatRefreshSpy = {
      request: vi.fn(),
    };

    TestBed.configureTestingModule({
      providers: [
        ChatStore,
        { provide: CalledService, useValue: calledServiceSpy },
        { provide: CalledMessageService, useValue: messageServiceSpy },
        { provide: ChatRefreshService, useValue: chatRefreshSpy },
      ],
    });

    store = TestBed.inject(ChatStore);
  });

  it('should be created', () => {
    expect(store).toBeTruthy();
  });

  it('selects called and fetches data when id is provided', () => {
    const id = '123';
    const mockCalled = createCalled({ id, status: 'pending' });
    calledServiceSpy.get.mockReturnValue(of({ data: mockCalled }));

    store.selectCalled(id);

    expect(store.selectedCalledId()).toBe(id);
    expect(calledServiceSpy.get).toHaveBeenCalledWith(id);
    expect(store.selectedCalled()).toEqual(mockCalled);
  });

  it('clears selection when id is null', () => {
    store.selectCalled(null);

    expect(store.selectedCalledId()).toBeNull();
    expect(store.selectedCalled()).toBeNull();
    expect(calledServiceSpy.get).not.toHaveBeenCalled();
  });

  it('handles fetch error', () => {
    calledServiceSpy.get.mockReturnValue(throwError(() => new Error('Error')));

    store.selectCalled('123');

    expect(store.selectedCalled()).toBeNull();
  });

  it('starts attendance and updates state', () => {
    const id = '123';
    const current = createCalled({ id, status: 'pending' });
    const updated = createCalled({ id, status: 'in_progress' });

    calledServiceSpy.get.mockReturnValue(of({ data: current }));
    calledServiceSpy.open.mockReturnValue(of({ data: updated }));

    store.selectCalled(id);
    store.startAttendance();

    expect(calledServiceSpy.open).toHaveBeenCalledWith(id);
    expect(store.selectedCalled()).toEqual(updated);
    expect(chatRefreshSpy.request).toHaveBeenCalled();
  });

  it('reverts state when start attendance fails', () => {
    const id = '123';
    const current = createCalled({ id, status: 'pending' });

    calledServiceSpy.get.mockReturnValue(of({ data: current }));
    calledServiceSpy.open.mockReturnValue(throwError(() => new Error('Error')));

    store.selectCalled(id);
    store.startAttendance();

    expect(store.selectedCalled()).toEqual(current);
  });

  it('computes canSendMessage based on selected status', () => {
    calledServiceSpy.get.mockReturnValue(of({ data: createCalled({ status: 'pending' }) }));
    store.selectCalled('1');
    expect(store.canSendMessage()).toBe(false);

    calledServiceSpy.get.mockReturnValue(of({ data: createCalled({ status: 'in_progress' }) }));
    store.selectCalled('2');
    expect(store.canSendMessage()).toBe(true);
  });

  it('updates selected contact', () => {
    const current = createCalled({
      id: '1',
      contact: { id: '1', name: 'Old' },
    });
    calledServiceSpy.get.mockReturnValue(of({ data: current }));
    store.selectCalled('1');

    const newContact: Contact = {
      id: '1',
      name: 'New',
      is_active: true,
      company_id: 'company-1',
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    store.updateContact(newContact);

    expect(store.selectedCalled()?.contact).toEqual(newContact);
  });
});
