import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import { Location } from '@angular/common';
import { NavigationEnd, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { App, type BackButtonListenerEvent } from '@capacitor/app';
import { type PluginListenerHandle } from '@capacitor/core';
import { filter } from 'rxjs';
import { PlatformService } from './platform.service';

type ModalCloser = () => boolean;

/**
 * Gerencia comportamento do botão físico voltar no Android.
 *
 * Regras:
 * 1) Se houver modal aberto, fecha modal (via callback registrado)
 * 2) Senão, se houver histórico interno de navegação, executa `Location.back()`
 * 3) Se estiver na raiz do histórico, confirma saída e encerra o app
 *
 * Em web/iOS é no-op.
 */
@Injectable({ providedIn: 'root' })
export class BackButtonService {
  private readonly platform = inject(PlatformService);
  private readonly router = inject(Router);
  private readonly location = inject(Location);
  private readonly destroyRef = inject(DestroyRef);

  private readonly modalClosers = signal<readonly ModalCloser[]>([]);
  private readonly navigationHistory = signal<readonly string[]>([]);
  private readonly canGoBack = computed(() => this.navigationHistory().length > 1);

  private listenerHandle: PluginListenerHandle | null = null;
  private initialized = false;

  /** Inicializa listener Android. Idempotente e no-op fora de Android nativo. */
  async initialize(): Promise<void> {
    if (!this.platform.isAndroid || this.initialized) {
      return;
    }

    this.initialized = true;
    this.seedCurrentRoute();
    this.bindRouterHistory();

    this.listenerHandle = await this.addBackButtonListener(() => {
      void this.onBackButtonPressed();
    });
  }

  /**
   * Registra callback opcional para fechar modal aberto.
   *
   * O callback deve retornar `true` quando realmente fechou modal.
   * O retorno remove o callback registrado.
   */
  registerModalCloser(closer: ModalCloser): () => void {
    this.modalClosers.update((closers) => [...closers, closer]);
    return (): void => {
      this.modalClosers.update((closers) => closers.filter((current) => current !== closer));
    };
  }

  private seedCurrentRoute(): void {
    const current = this.normalizeUrl(this.router.url);
    this.navigationHistory.set(current ? [current] : []);
  }

  private bindRouterHistory(): void {
    this.router.events
      .pipe(
        filter((event): event is NavigationEnd => event instanceof NavigationEnd),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((event) => {
        const nextUrl = this.normalizeUrl(event.urlAfterRedirects);
        const currentHistory = this.navigationHistory();
        const lastUrl = currentHistory[currentHistory.length - 1] ?? '';

        if (!nextUrl || nextUrl === lastUrl) {
          return;
        }

        this.navigationHistory.set([...currentHistory, nextUrl]);
      });
  }

  private async onBackButtonPressed(): Promise<void> {
    if (this.tryCloseModal()) {
      return;
    }

    if (this.canGoBack()) {
      this.navigationHistory.update((history) => history.slice(0, -1));
      this.location.back();
      return;
    }

    if (this.confirmExit()) {
      await this.exitAppNative();
    }
  }

  private tryCloseModal(): boolean {
    const closers = this.modalClosers();

    for (let index = closers.length - 1; index >= 0; index -= 1) {
      const didClose = closers[index]?.() ?? false;
      if (didClose) {
        return true;
      }
    }

    return false;
  }

  private confirmExit(): boolean {
    return window.confirm('Sair do app?');
  }

  private async addBackButtonListener(
    listener: (event: BackButtonListenerEvent) => void,
  ): Promise<PluginListenerHandle> {
    return App.addListener('backButton', listener);
  }

  private async exitAppNative(): Promise<void> {
    await App.exitApp();
  }

  private normalizeUrl(url: string): string {
    const withoutQuery = url.split('?')[0] ?? '';
    const withoutFragment = withoutQuery.split('#')[0] ?? '';
    return withoutFragment;
  }
}
