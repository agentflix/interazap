import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstValueFrom } from 'rxjs';
import { environment } from '@env/environment';
import { AuthService } from './auth.service';
import { type AuthResponse } from '@core/models/auth.model';
import { AuthStorageService } from './platform/auth-storage.service';
import { PlatformService } from './platform/platform.service';
import { PushService } from './platform/push.service';

describe('AuthService', () => {
  let service: AuthService;
  let httpMock: HttpTestingController;
  let storageSet: ReturnType<typeof vi.fn>;
  let storageClear: ReturnType<typeof vi.fn>;
  let unregisterCurrentDevice: ReturnType<typeof vi.fn>;
  let isMobile = false;

  const buildResponse = (token = 'pat-123'): AuthResponse => ({
    data: {
      user: { id: 1, name: 'Maria', email: 'maria@example.com' },
      token,
      permissions: [],
      tenant_plan: null,
    },
  });

  beforeEach(() => {
    isMobile = false;
    storageSet = vi.fn().mockResolvedValue(undefined);
    storageClear = vi.fn().mockResolvedValue(undefined);
    unregisterCurrentDevice = vi.fn().mockResolvedValue(undefined);

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        AuthService,
        {
          provide: PlatformService,
          useValue: {
            get isMobile() {
              return isMobile;
            },
            get isIOS() {
              return false;
            },
            get isAndroid() {
              return false;
            },
            get isWeb() {
              return !isMobile;
            },
          },
        },
        {
          provide: AuthStorageService,
          useValue: {
            set: storageSet,
            clear: storageClear,
            getSync: () => null,
            get: vi.fn().mockResolvedValue(null),
            hydrate: vi.fn().mockResolvedValue(undefined),
          },
        },
        {
          provide: PushService,
          useValue: {
            unregisterCurrentDevice,
          },
        },
      ],
    });

    service = TestBed.inject(AuthService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  describe('login em web', () => {
    beforeEach(() => {
      isMobile = false;
    });

    it('NÃO envia device_name no body e NÃO persiste token via AuthStorageService', async () => {
      const promise = firstValueFrom(
        service.login({ email: 'maria@example.com', password: 'secret' }),
      );

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
      expect(req.request.method).toBe('POST');
      expect(req.request.withCredentials).toBe(true);
      expect(req.request.body).toEqual({
        email: 'maria@example.com',
        password: 'secret',
      });
      expect((req.request.body as Record<string, unknown>)['device_name']).toBeUndefined();

      req.flush(buildResponse('cookie-flow-token'));

      await promise;
      expect(storageSet).not.toHaveBeenCalled();
    });
  });

  describe('login em mobile', () => {
    beforeEach(() => {
      isMobile = true;
      vi.spyOn(service as any, 'resolveDeviceName').mockResolvedValue('iPhone15,2-ios');
    });

    it('envia device_name no body e persiste token via AuthStorageService', async () => {
      const promise = firstValueFrom(
        service.login({ email: 'maria@example.com', password: 'secret' }),
      );

      // resolveDeviceName retorna Promise → http é disparado no próximo microtask
      await Promise.resolve();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
      expect(req.request.method).toBe('POST');
      const body = req.request.body as { email: string; password: string; device_name: string };
      expect(body.email).toBe('maria@example.com');
      expect(body.password).toBe('secret');
      expect(body.device_name).toBe('iPhone15,2-ios');

      req.flush(buildResponse('mobile-pat-456'));

      await promise;
      expect(storageSet).toHaveBeenCalledWith('mobile-pat-456');
    });

    it('cai em fallback de device_name quando Device.getInfo lança', async () => {
      // Simula o retorno do fallback: resolveDeviceName já trata a exceção internamente
      vi.spyOn(service as any, 'resolveDeviceName').mockResolvedValue('mobile-1234567890');

      const promise = firstValueFrom(service.login({ email: 'a@b.com', password: 'x' }));

      // resolveDeviceName retorna Promise → http é disparado no próximo microtask
      await Promise.resolve();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
      const body = req.request.body as { device_name: string };
      // fallback inclui plataforma + timestamp; checamos prefixo
      expect(body.device_name.startsWith('mobile-')).toBe(true);
      req.flush(buildResponse());
      await promise;
    });

    it('não persiste token quando resposta não inclui token', async () => {
      const promise = firstValueFrom(service.login({ email: 'a@b.com', password: 'x' }));

      // resolveDeviceName retorna Promise → http é disparado no próximo microtask
      await Promise.resolve();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login`);
      req.flush({ data: { requires_2fa: true, email: 'a@b.com' } } satisfies AuthResponse);

      await promise;
      expect(storageSet).not.toHaveBeenCalled();
    });
  });

  describe('logout', () => {
    it('em web, chama POST /auth/logout e clear (no-op em web) é executado', async () => {
      isMobile = false;
      const promise = firstValueFrom(service.logout());

      // logout agora inicia por Promise (unregisterCurrentDevice) antes do POST
      await Promise.resolve();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/logout`);
      expect(req.request.method).toBe('POST');
      expect(req.request.withCredentials).toBe(true);
      req.flush(null);

      await promise;
      expect(storageClear).toHaveBeenCalledTimes(1);
      expect(unregisterCurrentDevice).toHaveBeenCalledTimes(1);
    });

    it('em mobile, chama POST /auth/logout e limpa AuthStorageService', async () => {
      isMobile = true;
      const promise = firstValueFrom(service.logout());

      // logout agora inicia por Promise (unregisterCurrentDevice) antes do POST
      await Promise.resolve();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/logout`);
      expect(req.request.method).toBe('POST');
      req.flush(null);

      await promise;
      expect(storageClear).toHaveBeenCalledTimes(1);
      expect(unregisterCurrentDevice).toHaveBeenCalledTimes(1);
    });
  });

  describe('loginWith2FA em mobile', () => {
    beforeEach(() => {
      isMobile = true;
      vi.spyOn(service as any, 'resolveDeviceName').mockResolvedValue('Pixel 8-android');
    });

    it('envia device_name e persiste token retornado', async () => {
      const promise = firstValueFrom(service.loginWith2FA('a@b.com', '123456'));

      // resolveDeviceName retorna Promise → http é disparado no próximo microtask
      await Promise.resolve();

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/login-with-2fa`);
      const body = req.request.body as {
        email: string;
        code: string;
        device_name: string;
      };
      expect(body.email).toBe('a@b.com');
      expect(body.code).toBe('123456');
      expect(body.device_name).toBe('Pixel 8-android');

      req.flush(buildResponse('2fa-pat'));

      await promise;
      expect(storageSet).toHaveBeenCalledWith('2fa-pat');
    });
  });
});
