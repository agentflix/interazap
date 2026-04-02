import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach } from 'vitest';
import { ChatQuotedMessageComponent } from './chat-quoted-message.component';

describe('ChatQuotedMessageComponent', () => {
  let fixture: ComponentFixture<ChatQuotedMessageComponent>;
  let component: ChatQuotedMessageComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatQuotedMessageComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatQuotedMessageComponent);
    component = fixture.componentInstance;
  });

  it('renders edited indicator when quoted message was edited', () => {
    fixture.componentRef.setInput('quoted', {
      id: 'quoted-1',
      content: 'Texto original editado',
      direction: 'incoming',
      sender_name: 'Contato',
      type: 'text',
      is_edited: true,
    });

    fixture.detectChanges();

    expect(component).toBeTruthy();
    expect(fixture.nativeElement.textContent).toContain('Contato');
    expect(fixture.nativeElement.textContent).toContain('Editada');
  });

  it('applies outgoing visual style when parentDirection is outgoing', () => {
    fixture.componentRef.setInput('quoted', {
      id: 'quoted-2',
      content: 'Mensagem recebida',
      direction: 'incoming',
      sender_name: 'Contato',
      type: 'text',
    });
    fixture.componentRef.setInput('parentDirection', 'outgoing');

    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    const container = host.querySelector('[data-testid="quoted-container"]');
    const sidebar = host.querySelector('[data-testid="quoted-sidebar"]');

    expect(container).not.toBeNull();
    expect(sidebar).not.toBeNull();
    expect(container?.classList.contains('bg-primary/15')).toBe(true);
    expect(sidebar?.classList.contains('bg-primary')).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Contato');
  });

  it('applies incoming visual style when parentDirection is incoming', () => {
    fixture.componentRef.setInput('quoted', {
      id: 'quoted-3',
      content: 'Mensagem enviada',
      direction: 'outgoing',
      sender_name: 'Atendente',
      type: 'text',
    });
    fixture.componentRef.setInput('parentDirection', 'incoming');

    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    const container = host.querySelector('[data-testid="quoted-container"]');
    const sidebar = host.querySelector('[data-testid="quoted-sidebar"]');

    expect(container).not.toBeNull();
    expect(sidebar).not.toBeNull();
    expect(container?.classList.contains('bg-neutral-100')).toBe(true);
    expect(sidebar?.classList.contains('bg-emerald-500')).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Você');
  });
});
