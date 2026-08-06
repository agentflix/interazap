import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { type Contact } from 'src/app/core/models/contact.model';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { InstanceService } from 'src/app/core/services/instance.service';
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
  let instanceServiceSpy: {
    list: ReturnType<typeof vi.fn>;
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
    instanceServiceSpy = {
      list: vi.fn().mockReturnValue(of({ data: [] })),
    };

    TestBed.configureTestingModule({
      providers: [
        ChatStore,
        { provide: CalledService, useValue: calledServiceSpy },
        { provide: CalledMessageService, useValue: messageServiceSpy },
        { provide: ChatRefreshService, useValue: chatRefreshSpy },
        { provide: InstanceService, useValue: instanceServiceSpy },
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
      crm_company_id: 'company-1',
      created_at: '2026-01-01T00:00:00.000Z',
      updated_at: '2026-01-01T00:00:00.000Z',
    };

    store.updateContact(newContact);

    expect(store.selectedCalled()?.contact).toEqual(newContact);
  });

  describe('composerMode', () => {
    it('returns "free" when ticket has no instance_id', () => {
      store.instanceProviders.set({});
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(of({ data: createCalled({ id: '1' }) }));
      store.selectCalled('1');
      expect(store.composerMode()).toBe('free');
    });

    it('returns "free" when instance provider is not meta', () => {
      store.instanceProviders.set({ i1: 'uazapi' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      expect(store.composerMode()).toBe('free');
    });

    it('returns "mixed" when meta provider and window is open', () => {
      store.instanceProviders.set({ i1: 'meta' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      store.setWindowStatus({
        canSendFreeText: true,
        lastMessageAt: new Date(),
        expiresAt: null,
        windowType: null,
      });
      expect(store.composerMode()).toBe('mixed');
    });

    it('returns "template-only" when meta provider and window is expired', () => {
      store.instanceProviders.set({ i1: 'meta' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      store.setWindowStatus({
        canSendFreeText: false,
        lastMessageAt: null,
        expiresAt: null,
        windowType: null,
      });
      expect(store.composerMode()).toBe('template-only');
    });

    it('is "template-only" while provider map is still loading (fail-closed)', () => {
      store.instanceProviders.set({});
      store.isProviderMapLoading.set(true);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      expect(store.composerMode()).toBe('template-only');
    });

    it('is "template-only" when meta window status is loading (fail-closed)', () => {
      store.instanceProviders.set({ i1: 'meta' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      store.setWindowStatus(null);
      store.setWindowStatusLoading(true);
      expect(store.composerMode()).toBe('template-only');
    });

    it('is "template-only" when meta window status errored (fail-closed)', () => {
      store.instanceProviders.set({ i1: 'meta' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      store.setWindowStatus(null);
      store.setWindowStatusError(true);
      expect(store.composerMode()).toBe('template-only');
    });

    it('flips from "mixed" to "template-only" when the clock passes the deadline', () => {
      store.instanceProviders.set({ i1: 'meta' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');

      const expiresAt = new Date(Date.now() + 2 * 60 * 1000);
      store.setWindowStatus({
        canSendFreeText: true,
        lastMessageAt: new Date(),
        expiresAt,
        windowType: '24h',
      });
      expect(store.composerMode()).toBe('mixed');

      // Relógio compartilhado avança além do deadline — sem nova requisição.
      store.now.set(expiresAt.getTime() + 1000);
      expect(store.composerMode()).toBe('template-only');
    });

    it('does not flip from "mixed" while still before the deadline', () => {
      store.instanceProviders.set({ i1: 'meta' });
      store.isProviderMapLoading.set(false);
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');

      const expiresAt = new Date(Date.now() + 60 * 60 * 1000);
      store.setWindowStatus({
        canSendFreeText: true,
        lastMessageAt: new Date(),
        expiresAt,
        windowType: '24h',
      });
      store.now.set(Date.now() + 30 * 60 * 1000);
      expect(store.composerMode()).toBe('mixed');
    });
  });

  describe('windowStatus', () => {
    it('sets and clears window status', () => {
      store.setWindowStatus({
        canSendFreeText: true,
        lastMessageAt: new Date(),
        expiresAt: null,
        windowType: null,
      });
      expect(store.windowStatus()?.canSendFreeText).toBe(true);

      store.clearWindowStatus();
      expect(store.windowStatus()).toBeNull();
    });
  });

  describe('windowBadge', () => {
    it('is not visible when ticket has no instance_id', () => {
      store.instanceProviders.set({});
      calledServiceSpy.get.mockReturnValue(of({ data: createCalled({ id: '1' }) }));
      store.selectCalled('1');
      expect(store.windowBadge().visible).toBe(false);
    });

    it('is not visible when instance provider is not meta', () => {
      store.instanceProviders.set({ i1: 'uazapi' });
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');
      expect(store.windowBadge().visible).toBe(false);
    });

    it('is visible with windowType and expiresAt as ISO strings when provider is meta', () => {
      store.instanceProviders.set({ i1: 'meta' });
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');

      const expiresAt = new Date('2026-07-22T10:00:00Z');
      const lastMessageAt = new Date('2026-07-21T10:00:00Z');
      store.setWindowStatus({
        canSendFreeText: true,
        lastMessageAt,
        expiresAt,
        windowType: '72h',
      });

      const badge = store.windowBadge();
      expect(badge.visible).toBe(true);
      expect(badge.windowType).toBe('72h');
      expect(badge.expiresAt).toBe(expiresAt.toISOString());
      expect(badge.lastInboundAt).toBe(lastMessageAt.toISOString());
    });

    it('has null fields when meta provider but no windowStatus loaded yet', () => {
      store.instanceProviders.set({ i1: 'meta' });
      calledServiceSpy.get.mockReturnValue(
        of({ data: createCalled({ id: '1', instance_id: 'i1' }) }),
      );
      store.selectCalled('1');

      const badge = store.windowBadge();
      expect(badge.visible).toBe(true);
      expect(badge.windowType).toBeNull();
      expect(badge.expiresAt).toBeNull();
      expect(badge.lastInboundAt).toBeNull();
    });
  });
});
