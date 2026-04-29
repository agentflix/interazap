import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { type Type } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { type ActionPerformed, type PushNotificationSchema, type Token } from '@capacitor/push-notifications';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { environment } from '@env/environment';
import { NativeBridgeService } from './native-bridge.service';
import { PlatformService } from './platform.service';
import type { PushService } from './push.service';

const DEVICE_ID_STORAGE_KEY = 'push_device_id';
const TOKEN_STORAGE_KEY = 'push_registered_token';

describe('PushService', () => {
  let pushServiceClass: Type<PushService>;
  let service: PushService;
  let httpMock: HttpTestingController;

  let isMobile = false;
  let isIOS = false;
  let isAndroid = false;

  const navigateMock = vi.fn().mockResolvedValue(true);
  const vibrateMock = vi.fn().mockResolvedValue(undefined);

  beforeEach(async () => {
    localStorage.removeItem(DEVICE_ID_STORAGE_KEY);
    localStorage.removeItem(TOKEN_STORAGE_KEY);

    isMobile = false;
    isIOS = false;
    isAndroid = false;

    navigateMock.mockReset();
    navigateMock.mockResolvedValue(true);
    vibrateMock.mockReset();
    vibrateMock.mockResolvedValue(undefined);

    const serviceModule = await import('./push.service');
    pushServiceClass = serviceModule.PushService;

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        pushServiceClass,
        {
          provide: PlatformService,
          useValue: {
            get isMobile() {
              return isMobile;
            },
            get isIOS() {
              return isIOS;
            },
            get isAndroid() {
              return isAndroid;
            },
            get isWeb() {
              return !isMobile;
            },
          },
        },
        {
          provide: NativeBridgeService,
          useValue: {
            vibrate: vibrateMock,
          },
        },
        {
          provide: Router,
          useValue: {
            navigate: navigateMock,
          },
        },
      ],
    });

    service = TestBed.inject(pushServiceClass);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    if (httpMock) {
      httpMock.verify();
    }
    vi.restoreAllMocks();
  });

  it('em web, initializeAfterLogin é no-op', async () => {
    isMobile = false;

    const requestSpy = vi
      .spyOn(service, 'requestPermissionsFromPlugin')
      .mockResolvedValue({ receive: 'granted' });
    const registerSpy = vi.spyOn(service, 'registerWithPushProvider').mockResolvedValue(undefined);
    const registrationListenerSpy = vi
      .spyOn(service, 'addRegistrationListener')
      .mockResolvedValue(undefined);
    const registrationErrorListenerSpy = vi
      .spyOn(service, 'addRegistrationErrorListener')
      .mockResolvedValue(undefined);
    const pushReceivedListenerSpy = vi
      .spyOn(service, 'addPushReceivedListener')
      .mockResolvedValue(undefined);
    const pushActionListenerSpy = vi
      .spyOn(service, 'addPushActionListener')
      .mockResolvedValue(undefined);

    await service.initializeAfterLogin();

    expect(requestSpy).not.toHaveBeenCalled();
    expect(registerSpy).not.toHaveBeenCalled();
    expect(registrationListenerSpy).not.toHaveBeenCalled();
    expect(registrationErrorListenerSpy).not.toHaveBeenCalled();
    expect(pushReceivedListenerSpy).not.toHaveBeenCalled();
    expect(pushActionListenerSpy).not.toHaveBeenCalled();
  });

  it('em mobile, solicita permissão, registra e adiciona listeners', async () => {
    isMobile = true;
    isIOS = true;

    const requestSpy = vi
      .spyOn(service, 'requestPermissionsFromPlugin')
      .mockResolvedValue({ receive: 'granted' });
    const registerSpy = vi.spyOn(service, 'registerWithPushProvider').mockResolvedValue(undefined);
    const registrationListenerSpy = vi
      .spyOn(service, 'addRegistrationListener')
      .mockResolvedValue(undefined);
    const registrationErrorListenerSpy = vi
      .spyOn(service, 'addRegistrationErrorListener')
      .mockResolvedValue(undefined);
    const pushReceivedListenerSpy = vi
      .spyOn(service, 'addPushReceivedListener')
      .mockResolvedValue(undefined);
    const pushActionListenerSpy = vi
      .spyOn(service, 'addPushActionListener')
      .mockResolvedValue(undefined);

    await service.initializeAfterLogin();

    expect(requestSpy).toHaveBeenCalledTimes(1);
    expect(registerSpy).toHaveBeenCalledTimes(1);
    expect(registrationListenerSpy).toHaveBeenCalledTimes(1);
    expect(registrationErrorListenerSpy).toHaveBeenCalledTimes(1);
    expect(pushReceivedListenerSpy).toHaveBeenCalledTimes(1);
    expect(pushActionListenerSpy).toHaveBeenCalledTimes(1);
  });

  it('registra token no backend e persiste device id', async () => {
    isMobile = true;
    isIOS = true;

    const token: Token = { value: 'token-ios-123' };
    const promise = service.handleRegistrationToken(token);

    const req = httpMock.expectOne(`${environment.apiUrl}/devices/register`);
    expect(req.request.method).toBe('POST');
    expect(req.request.withCredentials).toBe(true);
    expect(req.request.body).toEqual({ token: 'token-ios-123', platform: 'ios' });
    req.flush({ data: { id: 'device-abc' } });

    await promise;

    expect(localStorage.getItem(DEVICE_ID_STORAGE_KEY)).toBe('device-abc');
    expect(localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('token-ios-123');
  });

  it('re-registra no backend quando recebe novo token apos invalidacao e atualiza estado local', async () => {
    isMobile = true;
    isAndroid = true;

    localStorage.setItem(DEVICE_ID_STORAGE_KEY, 'device-old');
    localStorage.setItem(TOKEN_STORAGE_KEY, 'token-invalidado');

    const token: Token = { value: 'token-novo-456' };
    const promise = service.handleRegistrationToken(token);

    const req = httpMock.expectOne(`${environment.apiUrl}/devices/register`);
    expect(req.request.method).toBe('POST');
    expect(req.request.withCredentials).toBe(true);
    expect(req.request.body).toEqual({ token: 'token-novo-456', platform: 'android' });
    req.flush({ data: { id: 'device-new' } });

    await promise;

    expect(localStorage.getItem(DEVICE_ID_STORAGE_KEY)).toBe('device-new');
    expect(localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('token-novo-456');
  });

  it('incrementa badge local e vibra no push em foreground', async () => {
    const notification: PushNotificationSchema = {
      id: 'push-1',
      title: 'Nova mensagem',
      body: 'Você recebeu uma mensagem',
      data: { conversationId: '42' },
    };

    expect(service.badgeCount()).toBe(0);

    await service.handleForegroundNotification(notification);

    expect(service.badgeCount()).toBe(1);
    expect(vibrateMock).toHaveBeenCalledTimes(1);
  });

  it('navega para /chat/:conversationId ao tocar na notificação', async () => {
    const notification: PushNotificationSchema = {
      id: 'push-2',
      title: 'Nova conversa',
      body: 'Toque para abrir',
      data: { conversation_id: 'conv-777' },
    };

    const action: ActionPerformed = {
      actionId: 'tap',
      notification,
    };

    await service.handleNotificationAction(action);

    expect(navigateMock).toHaveBeenCalledWith(['/chat', 'conv-777']);
  });

  it('revoga device no backend no logout e limpa estado local', async () => {
    isMobile = true;
    localStorage.setItem(DEVICE_ID_STORAGE_KEY, 'device-logout-1');
    localStorage.setItem(TOKEN_STORAGE_KEY, 'token-logout-1');

    const promise = service.unregisterCurrentDevice();

    const req = httpMock.expectOne(`${environment.apiUrl}/devices/device-logout-1`);
    expect(req.request.method).toBe('DELETE');
    expect(req.request.withCredentials).toBe(true);
    req.flush(null, { status: 204, statusText: 'No Content' });

    await promise;

    expect(localStorage.getItem(DEVICE_ID_STORAGE_KEY)).toBeNull();
    expect(localStorage.getItem(TOKEN_STORAGE_KEY)).toBeNull();
  });
});
