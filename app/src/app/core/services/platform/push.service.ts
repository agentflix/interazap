import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ImpactStyle } from '@capacitor/haptics';
import {
  type ActionPerformed,
  type PermissionStatus,
  type PushNotificationSchema,
  PushNotifications,
  type RegistrationError,
  type Token,
} from '@capacitor/push-notifications';
import { firstValueFrom } from 'rxjs';
import { environment } from '@env/environment';
import { PlatformService } from './platform.service';
import { NativeBridgeService } from './native-bridge.service';

type DevicePlatform = 'ios' | 'android' | 'web';

interface DeviceRegistrationResponse {
  data?: {
    id?: string | number;
  };
}

type NotificationPayloadValue = string | number | boolean | null | undefined;

const DEVICE_ID_STORAGE_KEY = 'push_device_id';
const TOKEN_STORAGE_KEY = 'push_registered_token';

/**
 * Serviço de push para mobile (Capacitor):
 * - solicita permissão e registra token no SO
 * - envia token para o backend (`/devices/register`)
 * - processa handlers de foreground/action
 * - revoga device no logout (`DELETE /devices/:id`)
 */
@Injectable({ providedIn: 'root' })
export class PushService {
  private readonly http = inject(HttpClient);
  private readonly platform = inject(PlatformService);
  private readonly nativeBridge = inject(NativeBridgeService);
  private readonly router = inject(Router);

  private listenersBound = false;
  private registrationInFlight = false;

  private currentDeviceId: string | null = null;
  private currentToken: string | null = null;

  private readonly badgeCountSignal = signal(0);
  readonly badgeCount = this.badgeCountSignal.asReadonly();

  /**
   * Inicializa fluxo de push após autenticação no mobile.
   * No web é no-op.
   */
  async initializeAfterLogin(): Promise<void> {
    if (!this.platform.isMobile) {
      return;
    }

    this.loadPersistedRegistration();
    await this.bindListeners();

    const permissions = await this.requestPermissionsFromPlugin();
    if (permissions.receive !== 'granted') {
      return;
    }

    await this.registerWithPushProvider();
  }

  /**
   * Handler público do evento `registration`.
   * Envia/reativa token no backend sempre que o OS notifica registro.
   */
  async handleRegistrationToken(token: Token): Promise<void> {
    const tokenValue = token.value.trim();
    if (tokenValue === '' || this.registrationInFlight) {
      return;
    }

    this.registrationInFlight = true;
    try {
      const response = await firstValueFrom(
        this.http.post<DeviceRegistrationResponse>(
          `${environment.apiUrl}/devices/register`,
          {
            token: tokenValue,
            platform: this.resolvePlatform(),
          },
          { withCredentials: true },
        ),
      );

      const deviceId = this.extractDeviceId(response);
      if (deviceId !== null) {
        this.currentDeviceId = deviceId;
      }
      this.currentToken = tokenValue;
      this.persistRegistration();
    } finally {
      this.registrationInFlight = false;
    }
  }

  /**
   * Handler público de push em foreground: badge local + háptico.
   */
  async handleForegroundNotification(_notification: PushNotificationSchema): Promise<void> {
    this.badgeCountSignal.update((value) => value + 1);
    await this.nativeBridge.vibrate(ImpactStyle.Medium);
  }

  /**
   * Handler público de tap em notificação.
   * Navega para `/chat/:conversationId` quando houver id no payload.
   */
  async handleNotificationAction(action: ActionPerformed): Promise<void> {
    const payload = action.notification.data as Record<string, NotificationPayloadValue>;
    const conversationId = this.extractConversationId(payload);

    if (conversationId === null) {
      await this.router.navigate(['/chat']);
      return;
    }

    await this.router.navigate(['/chat', conversationId]);
  }

  /**
   * Revoga o device atual no backend durante logout.
   * No web é no-op.
   */
  async unregisterCurrentDevice(): Promise<void> {
    if (!this.platform.isMobile) {
      return;
    }

    this.loadPersistedRegistration();

    const deviceId = this.currentDeviceId;
    if (deviceId === null || deviceId.trim() === '') {
      return;
    }

    try {
      await firstValueFrom(
        this.http.delete(`${environment.apiUrl}/devices/${encodeURIComponent(deviceId)}`, {
          withCredentials: true,
        }),
      );
    } finally {
      this.currentDeviceId = null;
      this.currentToken = null;
      this.clearPersistedRegistration();
    }
  }

  private async bindListeners(): Promise<void> {
    if (this.listenersBound) {
      return;
    }

    this.listenersBound = true;

    await this.addRegistrationListener((token) => {
      void this.handleRegistrationToken(token);
    });

    await this.addRegistrationErrorListener(() => {
      this.registrationInFlight = false;
    });

    await this.addPushReceivedListener((notification) => {
      void this.handleForegroundNotification(notification);
    });

    await this.addPushActionListener((action) => {
      void this.handleNotificationAction(action);
    });
  }

  async requestPermissionsFromPlugin(): Promise<PermissionStatus> {
    return PushNotifications.requestPermissions();
  }

  async registerWithPushProvider(): Promise<void> {
    await PushNotifications.register();
  }

  async addRegistrationListener(listener: (token: Token) => void): Promise<void> {
    await PushNotifications.addListener('registration', listener);
  }

  async addRegistrationErrorListener(listener: (error: RegistrationError) => void): Promise<void> {
    await PushNotifications.addListener('registrationError', listener);
  }

  async addPushReceivedListener(listener: (notification: PushNotificationSchema) => void): Promise<void> {
    await PushNotifications.addListener('pushNotificationReceived', listener);
  }

  async addPushActionListener(listener: (action: ActionPerformed) => void): Promise<void> {
    await PushNotifications.addListener('pushNotificationActionPerformed', listener);
  }

  private resolvePlatform(): DevicePlatform {
    if (this.platform.isIOS) {
      return 'ios';
    }
    if (this.platform.isAndroid) {
      return 'android';
    }
    return 'web';
  }

  private extractDeviceId(response: DeviceRegistrationResponse): string | null {
    const id = response.data?.id;
    if (id === undefined || id === null) {
      return null;
    }
    return String(id);
  }

  private extractConversationId(
    payload: Record<string, NotificationPayloadValue>,
  ): string | null {
    const rawValue =
      payload['conversationId'] ??
      payload['conversation_id'] ??
      payload['calledId'] ??
      payload['called_id'] ??
      payload['ticketId'] ??
      payload['ticket_id'];

    if (rawValue === undefined || rawValue === null) {
      return null;
    }

    const normalized = String(rawValue).trim();
    return normalized === '' ? null : normalized;
  }

  private loadPersistedRegistration(): void {
    if (typeof window === 'undefined') {
      return;
    }

    const storedDeviceId = localStorage.getItem(DEVICE_ID_STORAGE_KEY);
    const storedToken = localStorage.getItem(TOKEN_STORAGE_KEY);

    this.currentDeviceId = storedDeviceId && storedDeviceId.trim() !== '' ? storedDeviceId : null;
    this.currentToken = storedToken && storedToken.trim() !== '' ? storedToken : null;
  }

  private persistRegistration(): void {
    if (typeof window === 'undefined') {
      return;
    }

    if (this.currentDeviceId !== null && this.currentDeviceId !== '') {
      localStorage.setItem(DEVICE_ID_STORAGE_KEY, this.currentDeviceId);
    }

    if (this.currentToken !== null && this.currentToken !== '') {
      localStorage.setItem(TOKEN_STORAGE_KEY, this.currentToken);
    }
  }

  private clearPersistedRegistration(): void {
    if (typeof window === 'undefined') {
      return;
    }

    localStorage.removeItem(DEVICE_ID_STORAGE_KEY);
    localStorage.removeItem(TOKEN_STORAGE_KEY);
  }
}