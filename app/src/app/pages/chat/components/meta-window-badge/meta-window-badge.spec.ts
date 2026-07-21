import { describe, it, expect } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { MetaWindowBadgeComponent } from './meta-window-badge';

describe('MetaWindowBadgeComponent', () => {
  interface BuildInputs {
    expiresAt?: string | null;
    windowType?: '24h' | '72h' | null;
    lastInboundAt?: string | null;
  }

  function build(inputs: BuildInputs = {}) {
    TestBed.configureTestingModule({ imports: [MetaWindowBadgeComponent] });
    const fixture = TestBed.createComponent(MetaWindowBadgeComponent);
    fixture.componentRef.setInput('expiresAt', inputs.expiresAt ?? null);
    fixture.componentRef.setInput('windowType', inputs.windowType ?? null);
    fixture.componentRef.setInput('lastInboundAt', inputs.lastInboundAt ?? null);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  // ---------------------------------------------------------------------------
  // Sem nenhuma fonte de janela
  // ---------------------------------------------------------------------------

  it('expired quando nenhuma fonte de janela é informada', () => {
    const badge = build();
    expect(badge.status()).toBe('expired');
    expect(badge.label().toLowerCase()).toContain('expirada');
  });

  // ---------------------------------------------------------------------------
  // Fallback por lastInboundAt (sem expiresAt) — thresholds novos (4h/1h)
  // ---------------------------------------------------------------------------

  it('fallback: safe quando ≥4h restantes', () => {
    const iso = new Date(Date.now() - 60 * 60 * 1000).toISOString(); // 1h atrás → 23h restantes
    expect(build({ lastInboundAt: iso }).status()).toBe('safe');
  });

  it('fallback: warning entre 1h e 4h restantes', () => {
    const iso = new Date(Date.now() - 22 * 60 * 60 * 1000).toISOString(); // 22h atrás → 2h restantes
    expect(build({ lastInboundAt: iso }).status()).toBe('warning');
  });

  it('fallback: danger quando <1h restante', () => {
    const iso = new Date(Date.now() - 23.5 * 60 * 60 * 1000).toISOString(); // 23h30 atrás → 30min restantes
    expect(build({ lastInboundAt: iso }).status()).toBe('danger');
  });

  it('fallback: expired quando >24h passados', () => {
    const iso = new Date(Date.now() - 25 * 60 * 60 * 1000).toISOString();
    expect(build({ lastInboundAt: iso }).status()).toBe('expired');
  });

  // ---------------------------------------------------------------------------
  // expiresAt no futuro — fonte autoritativa
  // ---------------------------------------------------------------------------

  it('usa expiresAt no futuro em vez do fallback (mesmo com lastInboundAt velho)', () => {
    const futureIso = new Date(Date.now() + 3 * 60 * 60 * 1000).toISOString(); // 3h no futuro
    const staleInbound = new Date(Date.now() - 48 * 60 * 60 * 1000).toISOString(); // fallback diria expirado
    const badge = build({ expiresAt: futureIso, windowType: '24h', lastInboundAt: staleInbound });

    expect(badge.usingAuthoritativeSource()).toBe(true);
    expect(badge.status()).not.toBe('expired');
  });

  it('windowType 72h com expiresAt no futuro renderiza rótulo "72h CTWA"', () => {
    const futureIso = new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString();
    const badge = build({ expiresAt: futureIso, windowType: '72h' });
    expect(badge.label()).toContain('72h CTWA');
  });

  it('windowType 24h com expiresAt no futuro renderiza rótulo "24h"', () => {
    const futureIso = new Date(Date.now() + 10 * 60 * 60 * 1000).toISOString();
    const badge = build({ expiresAt: futureIso, windowType: '24h' });
    expect(badge.label()).toMatch(/^24h ·/);
  });

  // ---------------------------------------------------------------------------
  // expiresAt no passado + lastInboundAt recente → cai no fallback (defesa em profundidade)
  // ---------------------------------------------------------------------------

  it('expiresAt no passado com lastInboundAt recente cai no fallback e mostra janela aberta', () => {
    const pastIso = new Date(Date.now() - 60 * 60 * 1000).toISOString(); // expirou há 1h
    const recentInbound = new Date(Date.now() - 30 * 60 * 1000).toISOString(); // inbound há 30min
    const badge = build({ expiresAt: pastIso, windowType: '72h', lastInboundAt: recentInbound });

    expect(badge.usingAuthoritativeSource()).toBe(false);
    expect(badge.status()).not.toBe('expired');
    // Fallback sempre assume 24h — não herda o windowType 72h da fonte descartada
    expect(badge.label()).toMatch(/^24h ·/);
  });

  it('expiresAt no passado sem lastInboundAt válido permanece expirado', () => {
    const pastIso = new Date(Date.now() - 60 * 60 * 1000).toISOString();
    const badge = build({ expiresAt: pastIso, windowType: '72h', lastInboundAt: null });
    expect(badge.status()).toBe('expired');
  });

  // ---------------------------------------------------------------------------
  // Acessibilidade
  // ---------------------------------------------------------------------------

  it('aria-label no estado danger reforça a urgência textualmente (não só cor)', () => {
    const iso = new Date(Date.now() - 23.7 * 60 * 60 * 1000).toISOString(); // ~18min restantes
    const badge = build({ lastInboundAt: iso });
    expect(badge.status()).toBe('danger');
    expect(badge.ariaLabel().toLowerCase()).toContain('expirar');
  });

  it('aria-label do estado expirado menciona template aprovado', () => {
    expect(build().ariaLabel().toLowerCase()).toContain('template');
  });
});
