import type { ErrorHandler} from '@angular/core';
import { Injectable, inject } from '@angular/core';
import { PlatformService } from './platform.service';

/**
 * STUB temporário do SentryService.
 *
 * Motivo: as versões declaradas anteriormente no package.json
 * (`@sentry/capacitor@^1.7.0` e `@sentry/angular@^8.53.0`) não existem no
 * registro npm, impedindo o build mobile. Esta implementação no-op preserva
 * a API pública usada pelo `app.config.ts` para que o build prossiga.
 *
 * TODO: substituir por integração real após decidir versões compatíveis
 * (sugestão: `@sentry/capacitor@1.5.0` + `@sentry/angular@10.x`).
 */
interface SentryUserContext {
  id: string;
  tenantId: string;
  role?: string;
}

@Injectable({ providedIn: 'root' })
export class SentryService {
  private readonly platform = inject(PlatformService);

  init(): void {
    void this.platform.isMobile;
  }

  setUser(_context: SentryUserContext): void {
    // no-op
  }

  clearUser(): void {
    // no-op
  }
}

/** ErrorHandler stub que apenas loga no console. */
export class SentryAngularErrorHandler implements ErrorHandler {
  handleError(error: unknown): void {
     
    console.error('[SentryAngularErrorHandler stub]', error);
  }
}
