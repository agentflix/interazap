import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, it, expect, beforeEach } from 'vitest';
import { ChatTicketItemComponent } from './chat-ticket-item';
import type { Called } from 'src/app/core/services/called.service';

const buildTicket = (overrides: Partial<Called> = {}): Called => ({
  id: '1',
  company_id: '1',
  status: 'open',
  channel: 'whatsapp',
  contact: { id: '1', name: 'Rafael Amor', phone: '+5511999999999' },
  ...overrides,
});

describe('ChatTicketItemComponent', () => {
  let component: ChatTicketItemComponent;
  let fixture: ComponentFixture<ChatTicketItemComponent>;

  const render = (ticket: Called) => {
    fixture = TestBed.createComponent(ChatTicketItemComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('ticket', ticket);
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatTicketItemComponent],
    }).compileComponents();
  });

  it('should create', () => {
    render(buildTicket());
    expect(component).toBeTruthy();
  });

  describe('getInitials()', () => {
    it('returns first 2 chars of contact name uppercased', () => {
      render(buildTicket());
      expect(component.getInitials(buildTicket())).toBe('RA');
    });

    it('falls back to phone when name is absent', () => {
      const ticket = buildTicket({ contact: { id: '1', name: '', phone: '+5511' } });
      render(ticket);
      expect(component.getInitials(ticket)).toBe('+5');
    });

    it('returns ?? when name and phone are absent', () => {
      const ticket = buildTicket({ contact: { id: '1', name: '', phone: '' } });
      render(ticket);
      expect(component.getInitials(ticket)).toBe('??');
    });
  });

  describe('getProfilePicture()', () => {
    it('returns null when no picture URL is set', () => {
      render(buildTicket());
      expect(component.getProfilePicture(buildTicket())).toBeNull();
    });

    it('returns profile_picture_url from ticket root', () => {
      const ticket = buildTicket({ profile_picture_url: 'https://example.com/pic.jpg' });
      render(ticket);
      expect(component.getProfilePicture(ticket)).toBe('https://example.com/pic.jpg');
    });

    it('returns profile_picture_url from contact when ticket root is null', () => {
      const ticket = buildTicket({
        contact: { id: '1', name: 'Rafael', profile_picture_url: 'https://cdn.com/a.jpg' },
      });
      render(ticket);
      expect(component.getProfilePicture(ticket)).toBe('https://cdn.com/a.jpg');
    });
  });

  describe('template rendering', () => {
    it('shows initials avatar when no profile picture', () => {
      const el = render(buildTicket());
      const img = el.querySelector('img');
      const initials = el.querySelector('span.text-xs');
      expect(img).toBeNull();
      expect(initials?.textContent?.trim()).toBe('RA');
    });

    it('shows img when profile picture is available', () => {
      const el = render(buildTicket({ profile_picture_url: 'https://cdn.com/pic.jpg' }));
      const img = el.querySelector('img') as HTMLImageElement;
      expect(img).toBeTruthy();
      expect(img.src).toContain('cdn.com/pic.jpg');
    });

    it('shows IA badge when is_bot_active is true', () => {
      const el = render(buildTicket({ is_bot_active: true }));
      expect(el.textContent).toContain('IA');
    });

    it('does not show IA badge when is_bot_active is falsy', () => {
      const el = render(buildTicket({ is_bot_active: false }));
      expect(el.textContent).not.toContain('IA');
    });

    it('shows assigned_user badge', () => {
      const ticket = buildTicket({ assigned_user: { id: '2', name: 'Admin AgentFlix' } });
      const el = render(ticket);
      expect(el.textContent).toContain('Admin AgentFlix');
    });

    it('shows unread count badge when unread_count > 0', () => {
      const el = render(buildTicket({ unread_count: 5 }));
      expect(el.textContent).toContain('5');
    });

    it('shows status dot with yellow for pending status', () => {
      const el = render(buildTicket({ status: 'pending' }));
      const dot = el.querySelector('.bg-yellow-400');
      expect(dot).toBeTruthy();
    });

    it('shows status dot with green for open status', () => {
      const el = render(buildTicket({ status: 'open' }));
      const dot = el.querySelector('.bg-green-500');
      expect(dot).toBeTruthy();
    });
  });
});
