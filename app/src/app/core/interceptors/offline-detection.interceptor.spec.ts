import {
  HttpRequest,
  type HttpHandlerFn,
  HttpResponse,
  HttpErrorResponse,
} from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { firstValueFrom, of, throwError } from 'rxjs';
import { NetworkStatusService } from '../services/network-status.service';
import { offlineDetectionInterceptor } from './offline-detection.interceptor';

describe('offlineDetectionInterceptor', () => {
  const markOnline = vi.fn();
  const markOffline = vi.fn();

  beforeEach(() => {
    markOnline.mockClear();
    markOffline.mockClear();

    TestBed.configureTestingModule({
      providers: [
        {
          provide: NetworkStatusService,
          useValue: {
            markOnline,
            markOffline,
          },
        },
      ],
    });
  });

  it('marca online quando recebe resposta HTTP com sucesso', async () => {
    const request = new HttpRequest('GET', '/api/health');
    const handler: HttpHandlerFn = vi.fn(() => of(new HttpResponse({ status: 200 })));

    await firstValueFrom(
      TestBed.runInInjectionContext(() => offlineDetectionInterceptor(request, handler)),
    );

    expect(markOnline).toHaveBeenCalledTimes(1);
    expect(markOffline).not.toHaveBeenCalled();
  });

  it('marca offline quando a request falha com status 0', async () => {
    const request = new HttpRequest('GET', '/api/health');
    const handler: HttpHandlerFn = vi.fn(() =>
      throwError(
        () =>
          new HttpErrorResponse({
            status: 0,
            statusText: 'Unknown Error',
          }),
      ),
    );

    await expect(
      firstValueFrom(
        TestBed.runInInjectionContext(() => offlineDetectionInterceptor(request, handler)),
      ),
    ).rejects.toBeInstanceOf(HttpErrorResponse);

    expect(markOffline).toHaveBeenCalledTimes(1);
  });
});
