import { TestBed } from '@angular/core/testing';
import { describe, expect, it, beforeEach } from 'vitest';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { UserChatThreadViewComponent } from './user-chat-thread-view.component';

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
