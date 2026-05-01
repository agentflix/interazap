import { describe, it, expect } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { Window24hBadgeComponent } from './window-24h-badge';

describe('Window24hBadgeComponent', () => {
  function build(lastInboundAt: string | null) {
    TestBed.configureTestingModule({ imports: [Window24hBadgeComponent] });
    const fixture = TestBed.createComponent(Window24hBadgeComponent);
    fixture.componentRef.setInput('lastInboundAt', lastInboundAt);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  it('expired quando lastInboundAt é null', () => {
    expect(build(null).status()).toBe('expired');
  });

  it('safe quando >12h restantes', () => {
    const now = new Date();
    const isoRecent = new Date(now.getTime() - 60 * 60 * 1000).toISOString(); // 1h atrás → 23h restantes
    expect(build(isoRecent).status()).toBe('safe');
  });

  it('warning entre 4h e 12h restantes', () => {
    const now = new Date();
    const iso = new Date(now.getTime() - 18 * 60 * 60 * 1000).toISOString(); // 18h atrás → 6h restantes
    expect(build(iso).status()).toBe('warning');
  });

  it('danger quando <4h restantes', () => {
    const now = new Date();
    const iso = new Date(now.getTime() - 22 * 60 * 60 * 1000).toISOString(); // 22h atrás → 2h restantes
    expect(build(iso).status()).toBe('danger');
  });

  it('expired quando >24h passados', () => {
    const iso = new Date(Date.now() - 25 * 60 * 60 * 1000).toISOString();
    expect(build(iso).status()).toBe('expired');
  });

  it('label inclui "expirada" quando expirado', () => {
    expect(build(null).label().toLowerCase()).toContain('expirada');
  });
});
