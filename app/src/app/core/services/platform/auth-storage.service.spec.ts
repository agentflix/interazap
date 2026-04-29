import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthStorageService } from './auth-storage.service';
import { PlatformService } from './platform.service';

describe('AuthStorageService', () => {
  let service: AuthStorageService;
  let isMobile = false;

  beforeEach(() => {
    isMobile = false;

    TestBed.configureTestingModule({
      providers: [
        AuthStorageService,
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
      ],
    });
    service = TestBed.inject(AuthStorageService);
  });

  describe('em web', () => {
    beforeEach(() => {
      isMobile = false;
    });

    it('hydrate é no-op e não chama Preferences', async () => {
      const readSpy = vi.spyOn(service as any, 'readTokenFromStorage').mockResolvedValue(null);
      await service.hydrate();
      expect(readSpy).not.toHaveBeenCalled();
    });

    it('set não persiste em Preferences', async () => {
      const writeSpy = vi.spyOn(service as any, 'writeTokenToStorage').mockResolvedValue(undefined);
      await service.set('token-web');
      expect(writeSpy).not.toHaveBeenCalled();
    });

    it('getSync sempre retorna null', () => {
      expect(service.getSync()).toBeNull();
    });

    it('get assíncrono sempre retorna null', async () => {
      await expect(service.get()).resolves.toBeNull();
    });

    it('clear é no-op (não chama Preferences.remove)', async () => {
      const removeSpy = vi.spyOn(service as any, 'removeTokenFromStorage').mockResolvedValue(
        undefined,
      );
      await service.clear();
      expect(removeSpy).not.toHaveBeenCalled();
    });
  });

  describe('em mobile', () => {
    beforeEach(() => {
      isMobile = true;
    });

    it('hydrate carrega token do storage nativo para o cache em memória', async () => {
      vi.spyOn(service as any, 'readTokenFromStorage').mockResolvedValue('native-token');

      await service.hydrate();

      expect(service.getSync()).toBe('native-token');
    });

    it('hydrate não duplica chamadas', async () => {
      const readSpy = vi.spyOn(service as any, 'readTokenFromStorage').mockResolvedValue('tok');
      await service.hydrate();
      await service.hydrate();
      expect(readSpy).toHaveBeenCalledTimes(1);
    });

    it('set persiste em Preferences e atualiza o cache', async () => {
      const writeSpy = vi.spyOn(service as any, 'writeTokenToStorage').mockResolvedValue(undefined);
      await service.set('new-token');

      expect(writeSpy).toHaveBeenCalledWith('new-token');
      expect(service.getSync()).toBe('new-token');
    });

    it('clear remove do Preferences e zera cache', async () => {
      vi.spyOn(service as any, 'writeTokenToStorage').mockResolvedValue(undefined);
      const removeSpy = vi
        .spyOn(service as any, 'removeTokenFromStorage')
        .mockResolvedValue(undefined);
      await service.set('tok');
      expect(service.getSync()).toBe('tok');

      await service.clear();

      expect(removeSpy).toHaveBeenCalledTimes(1);
      expect(service.getSync()).toBeNull();
    });

    it('get fallback consulta Preferences quando cache vazio', async () => {
      vi.spyOn(service as any, 'readTokenFromStorage').mockResolvedValue('persisted');
      await expect(service.get()).resolves.toBe('persisted');
      expect(service.getSync()).toBe('persisted');
    });

    it('get devolve null quando Preferences lança erro', async () => {
      vi.spyOn(service as any, 'readTokenFromStorage').mockRejectedValue(new Error('boom'));
      await expect(service.get()).resolves.toBeNull();
    });
  });
});
