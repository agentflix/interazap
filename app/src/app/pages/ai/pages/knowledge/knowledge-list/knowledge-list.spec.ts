import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { provideRouter } from '@angular/router';
import { LucideAngularModule, icons } from 'lucide-angular';
import { EMPTY, of } from 'rxjs';
import { KnowledgeListComponent } from './knowledge-list';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';
import { RealtimeService } from '../../../../../core/services/realtime.service';
import { ToastService } from '../../../../../core/services/toast.service';
import { type AiKnowledge } from '@ai/models/ai.model';

const pagination = {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 1,
};

const pendingDoc: AiKnowledge = {
  id: '1',
  name: 'Doc Pendente',
  original_filename: 'doc.txt',
  file_type: 'txt',
  embedding_status: 'processing',
  file_size_bytes: 100,
  file_size_formatted: '100 B',
  version: 1,
  chunk_count: 3,
  title: 'Doc Pendente',
  content_type: 'text',
  status: 'processing',
  token_count: 30,
  file_path: null,
  source_url: null,
};

const readyDoc: AiKnowledge = {
  ...pendingDoc,
  id: '2',
  name: 'Doc Pronto',
  title: 'Doc Pronto',
  embedding_status: 'ready',
  status: 'indexed',
};

describe('KnowledgeListComponent polling', () => {
  let fixture: ComponentFixture<KnowledgeListComponent>;
  let component: KnowledgeListComponent;

  const serviceMock = {
    list: vi.fn(),
    get: vi.fn(),
    delete: vi.fn(),
    reindex: vi.fn(),
    bulkDelete: vi.fn(),
    bulkReindex: vi.fn(),
  };

  const realtimeMock = {
    connected: vi.fn(() => false),
    connect: vi.fn(() => undefined),
    on: vi.fn(() => EMPTY),
  };

  const toastMock = {
    success: vi.fn<(message: string) => void>(),
    error: vi.fn<(message: string) => void>(),
  };

  beforeEach(async () => {
    vi.useFakeTimers();

    serviceMock.list.mockReset();
    serviceMock.get.mockReset();
    serviceMock.delete.mockReset();
    serviceMock.reindex.mockReset();
    serviceMock.bulkDelete.mockReset();
    serviceMock.bulkReindex.mockReset();

    serviceMock.get.mockReturnValue(of(pendingDoc));
    serviceMock.delete.mockReturnValue(of(undefined));
    serviceMock.reindex.mockReturnValue(of(pendingDoc));
    serviceMock.bulkDelete.mockReturnValue(of({ deleted_count: 0 }));
    serviceMock.bulkReindex.mockReturnValue(of({ queued_count: 0 }));

    await TestBed.configureTestingModule({
      imports: [KnowledgeListComponent],
      providers: [
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: AiKnowledgeService, useValue: serviceMock },
        { provide: RealtimeService, useValue: realtimeMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();
  });

  afterEach(() => {
    if (fixture) {
      fixture.destroy();
    }
    vi.useRealTimers();
  });

  it('starts polling when documents have non-final status', () => {
    serviceMock.list.mockReturnValue(of({ data: [pendingDoc], meta: pagination }));

    fixture = TestBed.createComponent(KnowledgeListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(serviceMock.list).toHaveBeenCalledTimes(1);
    expect(component.hasProcessingDocuments()).toBe(true);

    vi.advanceTimersByTime(5000);

    expect(serviceMock.list).toHaveBeenCalledTimes(2);
    expect(component.pollingActive()).toBe(true);
  });

  it('stops polling when all documents reach final status', () => {
    serviceMock.list
      .mockReturnValueOnce(of({ data: [pendingDoc], meta: pagination }))
      .mockReturnValueOnce(of({ data: [readyDoc], meta: pagination }))
      .mockReturnValue(of({ data: [readyDoc], meta: pagination }));

    fixture = TestBed.createComponent(KnowledgeListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(serviceMock.list).toHaveBeenCalledTimes(1);

    vi.advanceTimersByTime(5000);
    expect(serviceMock.list.mock.calls.length).toBeGreaterThanOrEqual(2);

    vi.advanceTimersByTime(10000);
    expect(component.documents().every((doc) => doc.embedding_status === 'ready')).toBe(true);
  });
});
