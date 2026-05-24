import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter, Router } from '@angular/router';
import { importProvidersFrom } from '@angular/core';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of, throwError } from 'rxjs';
import { KnowledgeDashboardComponent } from './knowledge-dashboard';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';
import { type KnowledgeStats, type AiKnowledge } from '@ai/models/ai.model';

const mockStats: KnowledgeStats = {
  document_count: 20,
  total_storage_bytes: 500000,
  storage_limit_bytes: 1000000,
  storage_used_percent: 50,
  storage_formatted: '500 KB',
  storage_limit_formatted: '1 MB',
  total_chunks: 100,
  documents_ready: 14,
  documents_processing: 3,
  documents_pending: 1,
  documents_failed: 2,
};

const mockDocs: AiKnowledge[] = [
  {
    id: '1',
    name: 'Test Doc 1',
    original_filename: 'test1.txt',
    file_type: 'txt',
    embedding_status: 'ready',
    file_size_bytes: 1024,
    file_size_formatted: '1 KB',
    version: 1,
    chunk_count: 10,
    title: 'Test Doc 1',
    content_type: 'text',
    status: 'indexed',
    token_count: 0,
    file_path: null,
    source_url: null,
    created_at: '2026-02-25T00:00:00Z',
  },
  {
    id: '2',
    name: 'Test Doc 2',
    original_filename: 'test2.pdf',
    file_type: 'pdf',
    embedding_status: 'processing',
    file_size_bytes: 2048,
    file_size_formatted: '2 KB',
    version: 1,
    chunk_count: 5,
    title: 'Test Doc 2',
    content_type: 'pdf',
    status: 'processing',
    token_count: 0,
    file_path: null,
    source_url: null,
    created_at: '2026-02-24T00:00:00Z',
  },
];

let serviceMock: {
  getStats: ReturnType<typeof vi.fn>;
  list: ReturnType<typeof vi.fn>;
};

describe('KnowledgeDashboardComponent', () => {
  let fixture: ComponentFixture<KnowledgeDashboardComponent>;
  let component: KnowledgeDashboardComponent;

  beforeEach(async () => {
    serviceMock = {
      getStats: vi.fn().mockReturnValue(of(mockStats)),
      list: vi
        .fn()
        .mockReturnValue(
          of({ data: mockDocs, meta: { current_page: 1, last_page: 1, per_page: 5, total: 2 } }),
        ),
    };

    await TestBed.configureTestingModule({
      imports: [KnowledgeDashboardComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: AiKnowledgeService, useValue: serviceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(KnowledgeDashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('renders stat cards with icons and correct padding', () => {
    const el: HTMLElement = fixture.nativeElement;
    const cards = el.querySelectorAll('.p-6');
    // 4 stat cards + 2 secondary panels = 6 p-6 elements minimum
    expect(cards.length).toBeGreaterThanOrEqual(4);

    // Verify icons are present
    const iconNames = ['file-text', 'layers', 'hard-drive', 'triangle-alert'];
    for (const name of iconNames) {
      const icon = el.querySelector(`lucide-icon[name="${name}"]`);
      expect(icon).toBeTruthy();
    }

    // No p-4 card containers (old padding removed)
    const oldPaddingCards = el.querySelectorAll('.rounded-xl.p-4:not(.animate-pulse)');
    expect(oldPaddingCards.length).toBe(0);
  });

  it('uses text-danger token for failed docs when count > 0', () => {
    const el: HTMLElement = fixture.nativeElement;
    const failedValue = el.querySelector('.text-danger');
    expect(failedValue).toBeTruthy();
    expect(failedValue?.textContent?.trim()).toBe('2');
  });

  it('uses neutral color for failed docs when count is 0', () => {
    const zeroStats = { ...mockStats, documents_failed: 0 };
    serviceMock.getStats.mockReturnValue(of(zeroStats));

    component.reload();
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    const dangerElements = el.querySelectorAll('.text-danger');
    // No text-danger when failed = 0
    expect(dangerElements.length).toBe(0);
  });

  it('renders storage bar with dynamic color class', () => {
    expect(component.storageBarColor()).toBe('bg-accent-500');

    // 70-89% → warning
    const warnStats = { ...mockStats, storage_used_percent: 75 };
    serviceMock.getStats.mockReturnValue(of(warnStats));
    component.reload();
    fixture.detectChanges();
    expect(component.storageBarColor()).toBe('bg-warning');

    // ≥90% → danger
    const dangerStats = { ...mockStats, storage_used_percent: 95 };
    serviceMock.getStats.mockReturnValue(of(dangerStats));
    component.reload();
    fixture.detectChanges();
    expect(component.storageBarColor()).toBe('bg-danger');
  });

  it('computes status distribution percentages correctly', () => {
    const dist = component.statusDistribution();
    expect(dist).toHaveLength(4);

    expect(dist[0]).toEqual({ label: 'Prontos', count: 14, pct: 70, color: 'bg-success' });
    expect(dist[1]).toEqual({ label: 'Processando', count: 3, pct: 15, color: 'bg-accent-500' });
    expect(dist[2]).toEqual({ label: 'Pendentes', count: 1, pct: 5, color: 'bg-warning' });
    expect(dist[3]).toEqual({ label: 'Com falha', count: 2, pct: 10, color: 'bg-danger' });
  });

  it('returns empty distribution when document_count is 0', () => {
    const emptyStats = { ...mockStats, document_count: 0 };
    serviceMock.getStats.mockReturnValue(of(emptyStats));
    component.reload();
    fixture.detectChanges();

    expect(component.statusDistribution()).toEqual([]);
  });

  it('computes avgChunksPerDoc correctly', () => {
    // 100 chunks / 20 docs = 5
    expect(component.avgChunksPerDoc()).toBe(5);
  });

  it('returns 0 avgChunksPerDoc when no documents', () => {
    const emptyStats = { ...mockStats, document_count: 0, total_chunks: 0 };
    serviceMock.getStats.mockReturnValue(of(emptyStats));
    component.reload();
    fixture.detectChanges();

    expect(component.avgChunksPerDoc()).toBe(0);
  });

  it('renders status distribution bar', () => {
    const el: HTMLElement = fixture.nativeElement;
    const bar = el.querySelector('.bg-success');
    expect(bar).toBeTruthy();
    // Check legend items
    const legendText = el.textContent ?? '';
    expect(legendText).toContain('Prontos');
    expect(legendText).toContain('Processando');
    expect(legendText).toContain('Pendentes');
    expect(legendText).toContain('Com falha');
  });

  it('renders recent documents with status badges', () => {
    const el: HTMLElement = fixture.nativeElement;
    const text = el.textContent ?? '';
    expect(text).toContain('Test Doc 1');
    expect(text).toContain('Test Doc 2');

    const badges = el.querySelectorAll('af-status-badge');
    expect(badges.length).toBe(2);
  });

  it('shows empty state when no recent documents', () => {
    serviceMock.list.mockReturnValue(
      of({ data: [], meta: { current_page: 1, last_page: 1, per_page: 5, total: 0 } }),
    );
    component.reload();
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    const emptyState = el.querySelector('af-empty-state');
    expect(emptyState).toBeTruthy();
  });

  it('isolates recent docs error from stats section', () => {
    serviceMock.getStats.mockReturnValue(of(mockStats));
    serviceMock.list.mockReturnValue(throwError(() => new Error('network error')));

    component.retryRecentDocs();
    fixture.detectChanges();

    // Stats should still be loaded
    expect(component.stats()).toEqual(mockStats);
    // Recent docs should show error
    expect(component.recentDocsError()).toBe(true);

    const el: HTMLElement = fixture.nativeElement;
    const text = el.textContent ?? '';
    expect(text).toContain('Erro ao carregar documentos recentes');
  });

  it('navigates to upload on quick action', () => {
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.goToUpload();

    expect(navigateSpy).toHaveBeenCalledWith(['/ai/knowledge/upload']);
  });

  it('navigates to search on quick action', () => {
    const router = TestBed.inject(Router);
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.goToSearch();

    expect(navigateSpy).toHaveBeenCalledWith(['/ai/knowledge/search']);
  });
});
