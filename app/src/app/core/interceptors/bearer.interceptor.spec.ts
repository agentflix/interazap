import { HttpRequest, type HttpHandlerFn, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { firstValueFrom, of } from 'rxjs';
import { bearerInterceptor } from './bearer.interceptor';
import { AuthStorageService } from '../services/platform/auth-storage.service';
import { PlatformService } from '../services/platform/platform.service';

describe('bearerInterceptor', () => {
  let isMobile = false;
  let storedToken: string | null = null;

  beforeEach(() => {
    isMobile = false;
    storedToken = null;

    TestBed.configureTestingModule({
      providers: [
        {
          provide: PlatformService,
          useValue: {
            get isMobile() {
              return isMobile;
            },
          },
        },
        {
          provide: AuthStorageService,
          useValue: {
            getSync: () => storedToken,
          },
        },
      ],
    });
  });

  it('não modifica o request em web', async () => {
    isMobile = false;
    storedToken = 'should-be-ignored';

    const request = new HttpRequest('GET', '/api/anything');
    const handler: HttpHandlerFn = vi.fn((req) => {
      expect(req.headers.has('Authorization')).toBe(false);
      return of(new HttpResponse({ status: 200 }));
    });

    await firstValueFrom(
      TestBed.runInInjectionContext(() => bearerInterceptor(request, handler)),
    );

    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('em mobile sem token, request passa sem Authorization', async () => {
    isMobile = true;
    storedToken = null;

    const request = new HttpRequest('GET', '/api/anything');
    const handler: HttpHandlerFn = vi.fn((req) => {
      expect(req.headers.has('Authorization')).toBe(false);
      return of(new HttpResponse({ status: 200 }));
    });

    await firstValueFrom(
      TestBed.runInInjectionContext(() => bearerInterceptor(request, handler)),
    );

    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('em mobile com token, injeta Authorization: Bearer <token>', async () => {
    isMobile = true;
    storedToken = 'pat-token-xyz';

    const request = new HttpRequest('GET', '/api/secure');
    const handler: HttpHandlerFn = vi.fn((req) => {
      expect(req.headers.get('Authorization')).toBe('Bearer pat-token-xyz');
      return of(new HttpResponse({ status: 200 }));
    });

    await firstValueFrom(
      TestBed.runInInjectionContext(() => bearerInterceptor(request, handler)),
    );

    expect(handler).toHaveBeenCalledTimes(1);
  });

  it('em mobile com Authorization já definido, não sobrescreve', async () => {
    isMobile = true;
    storedToken = 'storage-token';

    const request = new HttpRequest('GET', '/api/secure', null, {
      setHeaders: { Authorization: 'Bearer caller-supplied' },
    } as never).clone({ setHeaders: { Authorization: 'Bearer caller-supplied' } });

    const handler: HttpHandlerFn = vi.fn((req) => {
      expect(req.headers.get('Authorization')).toBe('Bearer caller-supplied');
      return of(new HttpResponse({ status: 200 }));
    });

    await firstValueFrom(
      TestBed.runInInjectionContext(() => bearerInterceptor(request, handler)),
    );

    expect(handler).toHaveBeenCalledTimes(1);
  });
});
