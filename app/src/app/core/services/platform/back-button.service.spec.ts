import { Location } from '@angular/common';
import { type WritableSignal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { type Event, Router } from '@angular/router';
import { App, type BackButtonListenerEvent } from '@capacitor/app';
import { Subject } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BackButtonService } from './back-button.service';
import { PlatformService } from './platform.service';

interface MutablePlatform {
  isMobile: boolean;
  isAndroid: boolean;
  isIOS: boolean;
  isWeb: boolean;
}

interface RouterStub {
  url: string;
  events: Subject<Event>;
}

interface LocationStub {
  back: () => void;
}

interface BackButtonServiceTestInternals {
  onBackButtonPressed: () => Promise<void>;
  navigationHistory: WritableSignal<readonly string[]>;
  modalClosers: WritableSignal<readonly (() => boolean)[]>;
  exitAppNative: () => Promise<void>;
}

function asInternals(service: BackButtonService): BackButtonServiceTestInternals {
  return service as unknown as BackButtonServiceTestInternals;
}

type BackButtonListener = (event: BackButtonListenerEvent) => void;
type AddBackButtonListenerFn = (
  eventName: 'backButton',
  listener: BackButtonListener,
) => Promise<{ remove: () => Promise<void> }>;

function createPlatformStub(overrides: Partial<MutablePlatform> = {}): MutablePlatform {
  return {
    isMobile: false,
    isAndroid: false,
    isIOS: false,
    isWeb: true,
    ...overrides,
  };
}

function createRouterStub(initialUrl = '/'): RouterStub {
  return {
    url: initialUrl,
    events: new Subject<Event>(),
  };
}

function createLocationStub(): LocationStub {
  return {
    back: vi.fn(),
  };
}

function makeService(deps: {
  platform: MutablePlatform;
  router: RouterStub;
  location: LocationStub;
}): BackButtonService {
  TestBed.resetTestingModule();
  TestBed.configureTestingModule({
    providers: [
      BackButtonService,
      { provide: PlatformService, useValue: deps.platform },
      { provide: Router, useValue: deps.router },
      { provide: Location, useValue: deps.location },
    ],
  });
  return TestBed.inject(BackButtonService);
}

function setupCapacitorAppMocks(): {
  addListenerMock: ReturnType<typeof vi.fn<AddBackButtonListenerFn>>;
} {
  const addListenerMock = vi.fn<AddBackButtonListenerFn>(async () => ({
    remove: async (): Promise<void> => {},
  }));

  Object.defineProperty(App, 'addListener', {
    value: addListenerMock,
    configurable: true,
    writable: true,
  });

  return { addListenerMock };
}

describe('BackButtonService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('em web é no-op e não registra listener nativo', async () => {
    const { addListenerMock } = setupCapacitorAppMocks();
    const platform = createPlatformStub({ isWeb: true, isAndroid: false });
    const router = createRouterStub('/chat');
    const location = createLocationStub();
    const service = makeService({ platform, router, location });

    await service.initialize();

    expect(addListenerMock).not.toHaveBeenCalled();
    expect(location.back).not.toHaveBeenCalled();
  });

  it('fecha modal antes de navegar quando callback retorna true', async () => {
    setupCapacitorAppMocks();
    const platform = createPlatformStub({ isMobile: true, isAndroid: true, isWeb: false });
    const router = createRouterStub('/chat');
    const location = createLocationStub();
    const service = makeService({ platform, router, location });
    const modalCloser = vi.fn(() => true);
    const internals = asInternals(service);
    internals.modalClosers.set([modalCloser]);

    await internals.onBackButtonPressed();

    expect(modalCloser).toHaveBeenCalledTimes(1);
    expect(location.back).not.toHaveBeenCalled();
  });

  it('navega para trás quando há histórico interno', async () => {
    setupCapacitorAppMocks();
    const platform = createPlatformStub({ isMobile: true, isAndroid: true, isWeb: false });
    const router = createRouterStub('/chat');
    const location = createLocationStub();
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const service = makeService({ platform, router, location });
    const internals = asInternals(service);
    internals.navigationHistory.set(['/chat', '/chat/123']);

    await internals.onBackButtonPressed();

    expect(location.back).toHaveBeenCalledTimes(1);
    expect(confirmSpy).not.toHaveBeenCalled();
  });

  it('na raiz confirma saída e chama exitApp quando usuário confirma', async () => {
    setupCapacitorAppMocks();
    const platform = createPlatformStub({ isMobile: true, isAndroid: true, isWeb: false });
    const router = createRouterStub('/chat');
    const location = createLocationStub();
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const service = makeService({ platform, router, location });
    const internals = asInternals(service);
    const exitSpy = vi.spyOn(internals, 'exitAppNative').mockResolvedValue(undefined);
    internals.navigationHistory.set(['/chat']);

    await internals.onBackButtonPressed();

    expect(location.back).not.toHaveBeenCalled();
    expect(confirmSpy).toHaveBeenCalledWith('Sair do app?');
    expect(exitSpy).toHaveBeenCalledTimes(1);
  });
});
