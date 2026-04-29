import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { EMPTY } from 'rxjs';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { environment } from '@env/environment';
import { QueueService } from './queue.service';
import { RealtimeService } from './realtime.service';

describe('QueueService', () => {
  let service: QueueService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        QueueService,
        {
          provide: RealtimeService,
          useValue: {
            connected: () => false,
            on: () => EMPTY,
          },
        },
      ],
    });

    service = TestBed.inject(QueueService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('deve carregar overview do endpoint principal quando disponivel', () => {
    const apiOverview = {
      data: {
        queues: [
          {
            name: 'default',
            waiting: 2,
            active: 1,
            completed: 8,
            failed: 1,
            delayed: 0,
            paused: false,
          },
        ],
        totalJobs: 12,
        totalFailed: 1,
        totalCompleted: 8,
        uptime: 100,
        redis: {
          connected: true,
          memory: '10mb',
        },
      },
    };

    service.getOverview().subscribe((overview) => {
      expect(overview.totalJobs).toBe(12);
      expect(overview.redis.connected).toBe(true);
      expect(service.overview()?.queues[0]?.name).toBe('default');
    });

    const req = httpMock.expectOne(`${environment.apiUrl}/admin/queues/overview`);
    expect(req.request.method).toBe('GET');
    req.flush(apiOverview);
  });

  it('deve usar fallback de health quando endpoint principal retorna erro', () => {
    service.getOverview().subscribe((overview) => {
      expect(overview.queues.length).toBe(1);
      expect(overview.queues[0]).toEqual({
        name: 'default',
        waiting: 3,
        active: 0,
        completed: 0,
        failed: 0,
        delayed: 1,
        paused: false,
      });
      expect(overview.totalJobs).toBe(4);
      expect(overview.redis.connected).toBe(true);
      expect(overview.redis.memory).toBe('N/A');
    });

    const mainReq = httpMock.expectOne(`${environment.apiUrl}/admin/queues/overview`);
    mainReq.flush({ message: 'Not Found' }, { status: 404, statusText: 'Not Found' });

    const fallbackReq = httpMock.expectOne(`${environment.apiUrl}/health/queues`);
    expect(fallbackReq.request.method).toBe('GET');
    fallbackReq.flush({
      healthy: true,
      queues: [{ name: 'default', size: 3, delayed: 1 }],
      checked_at: '2026-04-29T00:00:00Z',
    });
  });

  it('deve usar fallback para metricas de fila individual quando necessario', () => {
    service.getQueueMetrics('default').subscribe((metrics) => {
      expect(metrics).toEqual({
        name: 'default',
        waiting: 5,
        active: 0,
        completed: 0,
        failed: 0,
        delayed: 2,
        paused: false,
      });
    });

    const mainReq = httpMock.expectOne(`${environment.apiUrl}/admin/queues/default/metrics`);
    mainReq.flush({ message: 'Not Found' }, { status: 404, statusText: 'Not Found' });

    const fallbackReq = httpMock.expectOne(`${environment.apiUrl}/health/queues/default`);
    fallbackReq.flush({ name: 'default', size: 5, delayed: 2 });
  });
});
