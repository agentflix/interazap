import { Injectable, inject } from '@angular/core';
import { Subject, type Observable } from 'rxjs';
import { App, type AppState } from '@capacitor/app';
import { Camera, CameraResultType, CameraSource, type PermissionStatus } from '@capacitor/camera';
import { Filesystem } from '@capacitor/filesystem';
import { Haptics, ImpactStyle } from '@capacitor/haptics';
import { Keyboard, KeyboardResize } from '@capacitor/keyboard';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';
import { PlatformService } from './platform.service';

/** Cor brand teal usada na status bar mobile (alinhada com `.context/LAYOUT/`). */
const BRAND_TEAL = '#14b8a6';

export type NativeAttachmentResult =
  | { status: 'success'; file: File }
  | { status: 'cancelled' }
  | { status: 'permission-denied'; message: string }
  | { status: 'unavailable'; message: string };

/**
 * Bridge unificada para plugins de UI nativa do Capacitor.
 *
 * Cada método é guardado por `platform.isMobile` — em web é no-op silencioso,
 * permitindo importar este serviço em qualquer componente sem quebrar build
 * ou SSR. Plugins NÃO devem ser invocados no constructor (DI durante SSR
 * pode falhar) — sempre via {@link initialize} chamado em `ngOnInit`.
 *
 * @example
 * ```ts
 * private readonly nativeBridge = inject(NativeBridgeService);
 *
 * async ngOnInit(): Promise<void> {
 *   await this.nativeBridge.initialize();
 *   this.nativeBridge.onAppResume(() => this.ws.reconnect());
 * }
 * ```
 */
@Injectable({ providedIn: 'root' })
export class NativeBridgeService {
  private readonly platform = inject(PlatformService);

  private readonly appResumeSubject = new Subject<AppState>();

  /** Emite sempre que o app retorna de background para foreground (mobile only). */
  readonly appResume$: Observable<AppState> = this.appResumeSubject.asObservable();

  private initialized = false;

  /**
   * Configura status bar, splash screen, keyboard e listener de ciclo de vida.
   * Idempotente — chamadas subsequentes são no-op. No-op em web.
   */
  async initialize(): Promise<void> {
    if (!this.platform.isMobile || this.initialized) {
      return;
    }
    this.initialized = true;

    await this.setStatusBarBrand();

    if (this.platform.isAndroid) {
      try {
        await this.setKeyboardResizeNative();
      } catch {
        // Keyboard plugin pode não estar disponível em alguns devices — não crítico
      }
    }

    await this.addAppStateListener((state) => {
      if (state.isActive) {
        this.appResumeSubject.next(state);
      }
    });

    await this.hideSplash();
  }

  /**
   * Aplica a cor brand teal na status bar nativa.
   * `setBackgroundColor` só funciona no Android; `setStyle` funciona em ambos.
   */
  async setStatusBarBrand(): Promise<void> {
    if (!this.platform.isMobile) {
      return;
    }
    try {
      await this.applyStatusBarBrand();
    } catch {
      // Status bar pode falhar em algumas versões — não crítico para boot
    }
  }

  /** Esconde a splash screen programaticamente após o app estar pronto. */
  async hideSplash(): Promise<void> {
    if (!this.platform.isMobile) {
      return;
    }
    try {
      await this.hideSplashNative();
    } catch {
      // SplashScreen pode já estar escondida — ignorar
    }
  }

  /** Dispara feedback háptico curto (ações críticas: envio msg, notif). */
  async vibrate(style: ImpactStyle = ImpactStyle.Medium): Promise<void> {
    if (!this.platform.isMobile) {
      return;
    }
    try {
      await this.impactHaptics(style);
    } catch {
      // Haptics pode estar desabilitado nas configs do device — silencioso
    }
  }

  /**
   * Registra callback disparado quando o app retorna de background.
   */
  onAppResume(callback: (state: AppState) => void): { unsubscribe: () => void } {
    const subscription = this.appResume$.subscribe(callback);
    return { unsubscribe: () => subscription.unsubscribe() };
  }

  /**
   * Abre a camera nativa para capturar uma foto e retorna um `File` pronto para upload.
   */
  async capturePhoto(): Promise<NativeAttachmentResult> {
    return this.pickPhoto(CameraSource.Camera);
  }

  /**
   * Abre a galeria nativa e retorna a foto selecionada como `File`.
   */
  async pickPhotoFromGallery(): Promise<NativeAttachmentResult> {
    return this.pickPhoto(CameraSource.Photos);
  }

  /**
   * Atualmente usamos fallback para seletor web de arquivos.
   */
  isNativeFilePickerAvailable(): boolean {
    return false;
  }

  private async applyStatusBarBrand(): Promise<void> {
    await StatusBar.setStyle({ style: Style.Dark });
    if (this.platform.isAndroid) {
      await StatusBar.setBackgroundColor({ color: BRAND_TEAL });
    }
  }

  private async hideSplashNative(): Promise<void> {
    await SplashScreen.hide();
  }

  private async setKeyboardResizeNative(): Promise<void> {
    await Keyboard.setResizeMode({ mode: KeyboardResize.Native });
  }

  private async impactHaptics(style: ImpactStyle): Promise<void> {
    await Haptics.impact({ style });
  }

  private async addAppStateListener(listener: (state: AppState) => void): Promise<void> {
    await App.addListener('appStateChange', listener);
  }

  private async pickPhoto(source: CameraSource): Promise<NativeAttachmentResult> {
    if (!this.platform.isMobile) {
      return {
        status: 'unavailable',
        message: 'Captura nativa indisponivel no navegador.',
      };
    }

    const permissions = await this.requestCameraPermissions(source);
    if (!this.hasRequiredPermission(permissions, source)) {
      return {
        status: 'permission-denied',
        message:
          source === CameraSource.Camera
            ? 'Permissao da camera negada. Voce pode anexar arquivos pela opcao Arquivo.'
            : 'Permissao da galeria negada. Voce pode anexar arquivos pela opcao Arquivo.',
      };
    }

    try {
      const photo = await this.getPhoto(source);
      const mimeType = this.resolveMimeType(photo.format);
      const fileName = this.resolveFileName(photo.path, photo.format);
      const blob = await this.resolvePhotoBlob(photo.webPath, photo.path, mimeType);

      if (blob === null) {
        return {
          status: 'unavailable',
          message: 'Nao foi possivel converter a foto selecionada para upload.',
        };
      }

      return {
        status: 'success',
        file: new File([blob], fileName, { type: mimeType }),
      };
    } catch {
      return { status: 'cancelled' };
    }
  }

  private async requestCameraPermissions(source: CameraSource): Promise<PermissionStatus> {
    if (source === CameraSource.Camera) {
      return Camera.requestPermissions({ permissions: ['camera', 'photos'] });
    }

    return Camera.requestPermissions({ permissions: ['photos'] });
  }

  private hasRequiredPermission(permissions: PermissionStatus, source: CameraSource): boolean {
    const photosAllowed = permissions.photos === 'granted' || permissions.photos === 'limited';
    if (source === CameraSource.Photos) {
      return photosAllowed;
    }

    const cameraAllowed = permissions.camera === 'granted';
    return cameraAllowed && photosAllowed;
  }

  private async getPhoto(
    source: CameraSource,
  ): Promise<{ webPath?: string; path?: string; format: string }> {
    return Camera.getPhoto({
      source,
      quality: 85,
      resultType: CameraResultType.Uri,
      saveToGallery: false,
    });
  }

  private async resolvePhotoBlob(
    webPath: string | undefined,
    path: string | undefined,
    mimeType: string,
  ): Promise<Blob | null> {
    if (typeof webPath === 'string' && webPath.trim() !== '') {
      const response = await fetch(webPath);
      return response.blob();
    }

    if (typeof path === 'string' && path.trim() !== '') {
      const file = await Filesystem.readFile({ path });
      if (typeof file.data !== 'string') {
        return file.data;
      }
      return this.base64ToBlob(file.data, mimeType);
    }

    return null;
  }

  private resolveFileName(path: string | undefined, format: string): string {
    if (typeof path === 'string' && path.trim() !== '') {
      const pathSegments = path.split('/');
      const candidate = pathSegments[pathSegments.length - 1];
      if (typeof candidate === 'string' && candidate.trim() !== '') {
        return candidate;
      }
    }

    return `photo-${Date.now()}.${format}`;
  }

  private resolveMimeType(format: string): string {
    const normalized = format.trim().toLowerCase();
    if (normalized === 'png') {
      return 'image/png';
    }
    if (normalized === 'webp') {
      return 'image/webp';
    }
    return 'image/jpeg';
  }

  private base64ToBlob(base64Data: string, mimeType: string): Blob {
    const normalized =
      base64Data.indexOf(',') > -1 ? (base64Data.split(',')[1] ?? base64Data) : base64Data;
    const binary = atob(normalized);
    const buffer = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
      buffer[index] = binary.charCodeAt(index);
    }

    return new Blob([buffer], { type: mimeType });
  }
}
