import { Injectable, OnDestroy, inject } from '@angular/core';
import { Router } from '@angular/router';
import { App, type URLOpenListenerEvent } from '@capacitor/app';
import { PlatformService } from './platform.service';

/** Extrai o caminho da URL de um Universal Link. */
function extractPathFromUrl(url: string): string | null {
  try {
    const parsed = new URL(url);
    return parsed.pathname + parsed.hash;
  } catch {
    return null;
  }
}

/**
 * Gerencia deep links (Universal Links iOS / App Links Android).
 *
 * Responsabilidades:
 * - Escuta `App.addListener('appUrlOpen')` no mobile (cold start + warm start)
 * - Parseia URLs `https://app.interazap.com.br/chat/{id}` → rota Angular `/chat/{id}`
 * - Ignora URLs de domínios não reconhecidos (segurança)
 *
 * @example
 * ```ts
 * // Inicializar uma vez no AppComponent ou no root effect
 * const deepLink = inject(DeepLinkService);
 * deepLink.initialize();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class DeepLinkService implements OnDestroy {
  private readonly router = inject(Router);
  private readonly platform = inject(PlatformService);

  private bound = false;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private listenerHandle: any = null;

  /** Registra o listener de app URL open. No-op em web. */
  async initialize(): Promise<void> {
    if (!this.platform.isMobile || this.bound) {
      return;
    }

    this.bound = true;
    this.listenerHandle = await App.addListener('appUrlOpen', (event: URLOpenListenerEvent) => {
      this.handleUrlOpen(event.url);
    });
  }

  private handleUrlOpen(url: string): void {
    if (!isAllowedDomain(url)) {
      return;
    }

    const path = extractPathFromUrl(url);
    if (path === null) {
      return;
    }

    // Normaliza hash routing: /chat/123 ou #/chat/123 → ['chat', '123']
    const cleanPath = path.startsWith('/#') ? path.slice(2) : path;
    const conversationId = extractConversationId(cleanPath);

    if (conversationId !== null) {
      void this.router.navigate(['/chat', conversationId]);
    } else {
      // Fallback: navega para o path relativo sem o domínio
      const normalizedPath = cleanPath.startsWith('/') ? cleanPath : `/${cleanPath}`;
      void this.router.navigateByUrl(normalizedPath);
    }
  }

  ngOnDestroy(): void {
    if (this.listenerHandle !== null) {
      void this.listenerHandle.remove();
      this.listenerHandle = null;
    }
  }
}

const ALLOWED_HOSTS = ['app.interazap.com.br'];

function isAllowedDomain(url: string): boolean {
  try {
    const parsed = new URL(url);
    return ALLOWED_HOSTS.includes(parsed.hostname);
  } catch {
    return false;
  }
}

/** Extrai `conversationId` de rotas como `/chat/abc-123`. */
function extractConversationId(path: string): string | null {
  const match = /^\/chat\/([^/?#]+)/.exec(path);
  if (match === null || match[1] === undefined) {
    return null;
  }
  return match[1];
}
