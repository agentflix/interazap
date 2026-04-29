import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Capacitor } from '@capacitor/core';
import { PlatformService } from './platform.service';

describe('PlatformService', () => {
  let service: PlatformService;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [PlatformService] });
    service = TestBed.inject(PlatformService);
  });

  it('detecta web (não-nativo)', () => {
    vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(false);
    vi.spyOn(Capacitor, 'getPlatform').mockReturnValue('web');

    expect(service.isMobile).toBe(false);
    expect(service.isWeb).toBe(true);
    expect(service.isIOS).toBe(false);
    expect(service.isAndroid).toBe(false);
  });

  it('detecta iOS nativo', () => {
    vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(true);
    vi.spyOn(Capacitor, 'getPlatform').mockReturnValue('ios');

    expect(service.isMobile).toBe(true);
    expect(service.isIOS).toBe(true);
    expect(service.isAndroid).toBe(false);
    expect(service.isWeb).toBe(false);
  });

  it('detecta Android nativo', () => {
    vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(true);
    vi.spyOn(Capacitor, 'getPlatform').mockReturnValue('android');

    expect(service.isMobile).toBe(true);
    expect(service.isAndroid).toBe(true);
    expect(service.isIOS).toBe(false);
    expect(service.isWeb).toBe(false);
  });

  it('expõe getters readonly que refletem mudanças de plataforma em runtime', () => {
    const isNativeSpy = vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(false);
    const getPlatformSpy = vi.spyOn(Capacitor, 'getPlatform').mockReturnValue('web');

    expect(service.isWeb).toBe(true);
    expect(service.isMobile).toBe(false);

    isNativeSpy.mockReturnValue(true);
    getPlatformSpy.mockReturnValue('ios');

    expect(service.isWeb).toBe(false);
    expect(service.isMobile).toBe(true);
    expect(service.isIOS).toBe(true);
  });
});
