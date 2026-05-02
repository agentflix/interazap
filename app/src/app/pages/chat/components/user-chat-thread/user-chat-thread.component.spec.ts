import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { computed, signal } from '@angular/core';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { of } from 'rxjs';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { UserChatThreadViewComponent } from './user-chat-thread-view.component';
import { UserChatThreadComponent } from './user-chat-thread.component';
import { UserChatThreadStore } from './user-chat-thread.store';

describe('UserChatThreadViewComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UserChatThreadViewComponent],
    }).compileComponents();
  });

  it('deve criar o componente', () => {
    const fixture = TestBed.createComponent(UserChatThreadViewComponent);
    fixture.componentRef.setInput('messages', []);
    fixture.detectChanges();

    expect(fixture.componentInstance).toBeTruthy();
  });

  it('deve renderizar uma bolha por mensagem', () => {
    const fixture = TestBed.createComponent(UserChatThreadViewComponent);
    fixture.componentRef.setInput('messages', [
      { id: 'm-1', content: 'ola', direction: 'incoming' } as CalledMessage,
      { id: 'm-2', content: 'oi', direction: 'outgoing' } as CalledMessage,
    ]);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.querySelectorAll('app-user-chat-message-bubble').length).toBe(2);
  });

  it('should render internal transfer note with dedicated style', () => {
    const fixture = TestBed.createComponent(UserChatThreadViewComponent);
    fixture.componentRef.setInput('messages', [
      {
        id: 'm-internal',
        content: 'Repasse para atendimento N2',
        direction: 'outgoing',
        type: 'internal_note',
      } as CalledMessage,
    ]);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.textContent).toContain('Nota interna · oculta para o cliente');
  });

  it('should not render internal label for regular messages', () => {
    const fixture = TestBed.createComponent(UserChatThreadViewComponent);
    fixture.componentRef.setInput('messages', [
      {
        id: 'm-regular',
        content: 'Mensagem comum',
        direction: 'outgoing',
        type: 'text',
      } as CalledMessage,
    ]);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.textContent).not.toContain('Nota interna · oculta para o cliente');
  });

  it('should hide external delivery affordances for internal notes', () => {
    const fixture = TestBed.createComponent(UserChatThreadViewComponent);
    fixture.componentRef.setInput('messages', [
      {
        id: 'm-internal-2',
        content: 'Contexto interno',
        direction: 'outgoing',
        type: 'internal_note',
      } as CalledMessage,
    ]);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.querySelector('[aria-label="Responder"]')).toBeNull();
    expect(host.querySelector('[aria-label="Reagir"]')).toBeNull();
  });
});

// ─── UserChatThreadComponent — scroll indicator ────────────────────────────

describe('UserChatThreadComponent — scroll indicator', () => {
  let component: UserChatThreadComponent;
  let fixture: ComponentFixture<UserChatThreadComponent>;

  const mockCalledIdSignal = signal<string | null>('ticket-1');
  const mockMessagesSignal = signal<CalledMessage[]>([]);

  const mockStore = {
    calledId: mockCalledIdSignal.asReadonly(),
    messages: mockMessagesSignal.asReadonly(),
    hasMessages: computed(() => mockMessagesSignal().length > 0),
    isLoading: signal(false).asReadonly(),
    isLoadingMore: signal(false).asReadonly(),
    loadError: signal<string | null>(null).asReadonly(),
    contactTyping: signal<null>(null).asReadonly(),
    setContext: vi.fn(),
    connectRealtime: vi.fn(),
    loadMore: vi.fn().mockReturnValue(false),
    appendMessage: vi.fn(),
    replaceMessage: vi.fn(),
    retryLoadMessages: vi.fn(),
    openStartConversation: vi.fn(),
  };

  function makeScrollEl(opts: { scrollTop: number; clientHeight: number; scrollHeight: number }) {
    return { ...opts, scrollTo: vi.fn() } as unknown as HTMLElement;
  }

  beforeEach(async () => {
    vi.clearAllMocks();
    mockMessagesSignal.set([]);
    mockCalledIdSignal.set('ticket-1');

    await TestBed.configureTestingModule({
      imports: [UserChatThreadComponent],
      providers: [
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({})),
            snapshot: { paramMap: convertToParamMap({}) },
          },
        },
      ],
    })
      .overrideProvider(UserChatThreadStore, { useValue: mockStore })
      .compileComponents();

    fixture = TestBed.createComponent(UserChatThreadComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    // Flush microtask do ngAfterViewInit (seta scrollElement + setupResizeObserver)
    await fixture.whenStable();
  });

  it('showScrollToBottom inicia como false', () => {
    expect((component as any).showScrollToBottom()).toBe(false);
  });

  it('unreadCount inicia como zero', () => {
    expect((component as any).unreadCount()).toBe(0);
  });

  it('scrollToBottomClick zera showScrollToBottom e unreadCount', () => {
    (component as any).showScrollToBottom.set(true);
    (component as any).unreadCount.set(3);
    (component as any).scrollElement = makeScrollEl({ scrollTop: 0, clientHeight: 500, scrollHeight: 2000 });

    (component as any).scrollToBottomClick();

    expect((component as any).showScrollToBottom()).toBe(false);
    expect((component as any).unreadCount()).toBe(0);
  });

  it('botao aparece quando usuario esta scrollado para cima e nova mensagem chega', () => {
    (component as any).wasNearBottom = false;
    (component as any).prevMessageCount = 0;
    (component as any).pendingScrollToBottom = false;
    // Substitui scrollElement pelo mock após ngAfterViewInit já ter rodado
    (component as any).scrollElement = makeScrollEl({ scrollTop: 0, clientHeight: 500, scrollHeight: 2000 });

    mockMessagesSignal.set([{ id: 'm-1', content: 'nova', direction: 'incoming' } as CalledMessage]);
    fixture.detectChanges();

    expect((component as any).showScrollToBottom()).toBe(true);
    expect((component as any).unreadCount()).toBe(1);
  });

  it('botao nao aparece quando usuario esta no fundo (wasNearBottom true)', async () => {
    (component as any).wasNearBottom = true;
    (component as any).prevMessageCount = 0;
    (component as any).scrollElement = makeScrollEl({ scrollTop: 1900, clientHeight: 500, scrollHeight: 2000 });

    mockMessagesSignal.set([{ id: 'm-1', content: 'nova', direction: 'incoming' } as CalledMessage]);
    fixture.detectChanges();
    await fixture.whenStable();

    expect((component as any).showScrollToBottom()).toBe(false);
  });

  it('handleScroll reseta estado ao chegar no fundo', () => {
    (component as any).showScrollToBottom.set(true);
    (component as any).unreadCount.set(2);
    (component as any).scrollElement = makeScrollEl({ scrollTop: 1900, clientHeight: 500, scrollHeight: 2000 });
    (component as any).lastScrollTop = 0;

    (component as any).handleScroll();

    expect((component as any).showScrollToBottom()).toBe(false);
    expect((component as any).unreadCount()).toBe(0);
  });

  it('troca de calledId reseta showScrollToBottom, unreadCount e prevMessageCount', async () => {
    (component as any).showScrollToBottom.set(true);
    (component as any).unreadCount.set(5);

    mockCalledIdSignal.set('ticket-2');
    fixture.detectChanges();
    await fixture.whenStable();

    expect((component as any).showScrollToBottom()).toBe(false);
    expect((component as any).unreadCount()).toBe(0);
    expect((component as any).prevMessageCount).toBe(0);
  });

  it('botao com aria-label correto renderiza no DOM quando showScrollToBottom e true', async () => {
    (component as any).showScrollToBottom.set(true);
    fixture.detectChanges();
    await fixture.whenStable();

    const btn = fixture.nativeElement.querySelector('[aria-label="Rolar para novas mensagens"]');
    expect(btn).not.toBeNull();
  });
});
