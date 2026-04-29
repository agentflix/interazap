import { Injectable } from '@angular/core';
import { Capacitor } from '@capacitor/core';

/**
 * Detecta a plataforma de execução (web, iOS, Android) usando Capacitor.
 *
 * Estado é estático por sessão (a plataforma não muda em runtime), portanto
 * usamos getters readonly em vez de signals. Service é `providedIn: 'root'`
 * e deve ser injetado em interceptors/componentes que precisam variar
 * comportamento por plataforma.
 *
 * @example
 * ```ts
 * private readonly platform = inject(PlatformService);
 *
 * if (this.platform.isMobile) {
 *   // lógica nativa
 * }
 * ```
 */
@Injectable({ providedIn: 'root' })
export class PlatformService {
  /** `true` quando rodando dentro de um container nativo (iOS ou Android). */
  get isMobile(): boolean {
    return Capacitor.isNativePlatform();
  }

  /** `true` quando rodando no iOS nativo. */
  get isIOS(): boolean {
    return Capacitor.getPlatform() === 'ios';
  }

  /** `true` quando rodando no Android nativo. */
  get isAndroid(): boolean {
    return Capacitor.getPlatform() === 'android';
  }

  /** `true` quando rodando no navegador web (PWA ou desktop). */
  get isWeb(): boolean {
    return Capacitor.getPlatform() === 'web';
  }
}
