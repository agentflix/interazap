import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { type AppState } from '@capacitor/app';
import { ImpactStyle } from '@capacitor/haptics';
import { NativeBridgeService } from './native-bridge.service';
import { PlatformService } from './platform.service';

interface MutablePlatform {
  isMobile: boolean;
  isAndroid: boolean;
  isIOS: boolean;
  isWeb: boolean;
}

function createPlatformStub(overrides: Partial<MutablePlatform> = {}): MutablePlatform {
  return {
    isMobile: false,
    isAndroid: false,
    isIOS: false,
    isWeb: true,
    ...overrides,
  };
}

function makeService(platform: MutablePlatform): NativeBridgeService {
  TestBed.resetTestingModule();
  TestBed.configureTestingModule({
    providers: [NativeBridgeService, { provide: PlatformService, useValue: platform }],
  });
  return TestBed.inject(NativeBridgeService);
}

describe('NativeBridgeService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('em web (no-op guards)', () => {
    it('initialize() não invoca nenhum plugin nativo', async () => {
      const service = makeService(createPlatformStub({ isMobile: false, isWeb: true }));
      const statusSpy = vi.spyOn(service as any, 'setStatusBarBrand').mockResolvedValue(undefined);
      const keyboardSpy = vi
        .spyOn(service as any, 'setKeyboardResizeNative')
        .mockResolvedValue(undefined);
      const splashSpy = vi.spyOn(service as any, 'hideSplashNative').mockResolvedValue(undefined);
      const listenerSpy = vi
        .spyOn(service as any, 'addAppStateListener')
        .mockResolvedValue(undefined);

      await service.initialize();

      expect(statusSpy).not.toHaveBeenCalled();
      expect(splashSpy).not.toHaveBeenCalled();
      expect(keyboardSpy).not.toHaveBeenCalled();
      expect(listenerSpy).not.toHaveBeenCalled();
    });

    it('setStatusBarBrand() / hideSplash() / vibrate() são no-op', async () => {
      const service = makeService(createPlatformStub({ isMobile: false }));
      const applyStatusSpy = vi
        .spyOn(service as any, 'applyStatusBarBrand')
        .mockResolvedValue(undefined);
      const splashSpy = vi.spyOn(service as any, 'hideSplashNative').mockResolvedValue(undefined);
      const hapticsSpy = vi.spyOn(service as any, 'impactHaptics').mockResolvedValue(undefined);

      await service.setStatusBarBrand();
      await service.hideSplash();
      await service.vibrate();

      expect(applyStatusSpy).not.toHaveBeenCalled();
      expect(splashSpy).not.toHaveBeenCalled();
      expect(hapticsSpy).not.toHaveBeenCalled();
    });
  });

  describe('em mobile (Android)', () => {
    it('initialize() configura status bar teal, keyboard native e esconde splash', async () => {
      const service = makeService(
        createPlatformStub({ isMobile: true, isAndroid: true, isWeb: false }),
      );
      const statusSpy = vi.spyOn(service as any, 'setStatusBarBrand').mockResolvedValue(undefined);
      const keyboardSpy = vi
        .spyOn(service as any, 'setKeyboardResizeNative')
        .mockResolvedValue(undefined);
      const splashSpy = vi.spyOn(service as any, 'hideSplashNative').mockResolvedValue(undefined);
      const listenerSpy = vi
        .spyOn(service as any, 'addAppStateListener')
        .mockResolvedValue(undefined);

      await service.initialize();

      expect(statusSpy).toHaveBeenCalledTimes(1);
      expect(keyboardSpy).toHaveBeenCalledTimes(1);
      expect(splashSpy).toHaveBeenCalledTimes(1);
      expect(listenerSpy).toHaveBeenCalledTimes(1);
    });

    it('initialize() é idempotente — segunda chamada não duplica setup', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isAndroid: true }));
      const splashSpy = vi.spyOn(service as any, 'hideSplashNative').mockResolvedValue(undefined);
      const listenerSpy = vi
        .spyOn(service as any, 'addAppStateListener')
        .mockResolvedValue(undefined);

      await service.initialize();
      await service.initialize();

      expect(splashSpy).toHaveBeenCalledTimes(1);
      expect(listenerSpy).toHaveBeenCalledTimes(1);
    });

    it('vibrate() chama Haptics.impact com style Medium por padrão', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isAndroid: true }));
      const impactSpy = vi.spyOn(service as any, 'impactHaptics').mockResolvedValue(undefined);

      await service.vibrate();

      expect(impactSpy).toHaveBeenCalledWith(ImpactStyle.Medium);
    });

    it('vibrate(Heavy) propaga style customizado', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isAndroid: true }));
      const impactSpy = vi.spyOn(service as any, 'impactHaptics').mockResolvedValue(undefined);

      await service.vibrate(ImpactStyle.Heavy);

      expect(impactSpy).toHaveBeenCalledWith(ImpactStyle.Heavy);
    });

    it('falha em StatusBar.setStyle não interrompe initialize()', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isAndroid: true }));
      vi.spyOn(service as any, 'applyStatusBarBrand').mockRejectedValue(new Error('boom'));
      const splashSpy = vi.spyOn(service as any, 'hideSplashNative').mockResolvedValue(undefined);

      await expect(service.initialize()).resolves.toBeUndefined();
      expect(splashSpy).toHaveBeenCalledTimes(1);
    });
  });

  describe('em mobile (iOS)', () => {
    it('initialize() NÃO chama Keyboard.setResizeMode (Android-only) nem setBackgroundColor', async () => {
      const service = makeService(
        createPlatformStub({ isMobile: true, isIOS: true, isWeb: false }),
      );
      const statusSpy = vi.spyOn(service as any, 'setStatusBarBrand').mockResolvedValue(undefined);
      const keyboardSpy = vi
        .spyOn(service as any, 'setKeyboardResizeNative')
        .mockResolvedValue(undefined);
      const splashSpy = vi.spyOn(service as any, 'hideSplashNative').mockResolvedValue(undefined);

      await service.initialize();

      expect(statusSpy).toHaveBeenCalledTimes(1);
      expect(keyboardSpy).not.toHaveBeenCalled();
      expect(splashSpy).toHaveBeenCalledTimes(1);
    });

    it('capturePhoto retorna permission-denied quando camera é negada', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isIOS: true }));
      const servicePrivate = service as object as {
        requestCameraPermissions: () => Promise<{ camera: string; photos: string }>;
      };

      vi.spyOn(servicePrivate, 'requestCameraPermissions').mockResolvedValue({
        camera: 'denied',
        photos: 'granted',
      });

      const result = await service.capturePhoto();

      expect(result.status).toBe('permission-denied');
    });

    it('pickPhotoFromGallery retorna success com File quando plugin devolve URI válida', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isIOS: true }));
      const servicePrivate = service as object as {
        requestCameraPermissions: () => Promise<{ camera: string; photos: string }>;
        getPhoto: () => Promise<{ webPath?: string; path?: string; format: string }>;
        resolvePhotoBlob: (
          webPath: string | undefined,
          path: string | undefined,
          mimeType: string,
        ) => Promise<Blob | null>;
      };

      vi.spyOn(servicePrivate, 'requestCameraPermissions').mockResolvedValue({
        camera: 'granted',
        photos: 'granted',
      });
      const photo = {
        webPath: 'https://example.com/file.jpg',
        path: '/tmp/file.jpg',
        format: 'jpeg',
      };
      vi.spyOn(servicePrivate, 'getPhoto').mockResolvedValue(photo);
      vi.spyOn(servicePrivate, 'resolvePhotoBlob').mockResolvedValue(new Blob(['abc']));

      const result = await service.pickPhotoFromGallery();

      expect(result.status).toBe('success');
      if (result.status === 'success') {
        expect(result.file.name).toContain('file.jpg');
      }
    });

    it('retorna cancelled quando Camera.getPhoto lança erro/cancelamento', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isIOS: true }));
      const servicePrivate = service as object as {
        requestCameraPermissions: () => Promise<{ camera: string; photos: string }>;
        getPhoto: () => Promise<{ webPath?: string; path?: string; format: string }>;
      };

      vi.spyOn(servicePrivate, 'requestCameraPermissions').mockResolvedValue({
        camera: 'granted',
        photos: 'granted',
      });
      vi.spyOn(servicePrivate, 'getPhoto').mockRejectedValue(new Error('cancelled'));

      const result = await service.capturePhoto();

      expect(result).toEqual({ status: 'cancelled' });
    });
  });

  describe('attachments em web', () => {
    it('capturePhoto retorna unavailable em web', async () => {
      const service = makeService(createPlatformStub({ isMobile: false, isWeb: true }));

      const result = await service.capturePhoto();

      expect(result.status).toBe('unavailable');
    });

    it('isNativeFilePickerAvailable retorna false', () => {
      const service = makeService(createPlatformStub({ isMobile: true }));
      expect(service.isNativeFilePickerAvailable()).toBe(false);
    });
  });

  describe('onAppResume', () => {
    it('callback dispara quando appStateChange emite isActive=true', async () => {
      const service = makeService(createPlatformStub({ isMobile: true, isAndroid: true }));
      let capturedListener: ((state: AppState) => void) | undefined;
      vi.spyOn(service as any, 'addAppStateListener').mockImplementation(
        async (...args: unknown[]) => {
          const [listener] = args;
          capturedListener = listener as (state: AppState) => void;
        },
      );

      await service.initialize();

      const callback = vi.fn();
      const sub = service.onAppResume(callback);

      capturedListener?.({ isActive: true });
      capturedListener?.({ isActive: false });

      expect(callback).toHaveBeenCalledTimes(1);
      expect(callback).toHaveBeenCalledWith({ isActive: true });

      sub.unsubscribe();
      capturedListener?.({ isActive: true });
      expect(callback).toHaveBeenCalledTimes(1);
    });
  });
});
