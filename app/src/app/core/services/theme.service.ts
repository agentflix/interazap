import { Injectable, signal, computed, effect } from '@angular/core';

/**
 * Gerencia o tema da aplicação (light/dark/system) e estado da sidebar.
 * Persiste a preferência de tema no localStorage.
 *
 * @example
 * ```ts
 * const theme = inject(ThemeService);
 * theme.applyTheme('dark');
 * theme.toggleTheme();
 * theme.toggleSidebar();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private static readonly STORAGE_KEY = 'af-theme';
  private static readonly DENSITY_KEY = 'af-density';
  private static readonly FONT_SIZE_KEY = 'af-font-size';

  /** Tema atual — 'light', 'dark' ou 'system'. */
  readonly theme = signal<'light' | 'dark' | 'system'>(this.loadTheme());

  /** Densidade atual da interface. */
  readonly density = signal<'compact' | 'normal' | 'expanded'>(this.loadDensity());

  /** Tamanho atual da fonte. */
  readonly fontSize = signal<'small' | 'medium' | 'large'>(this.loadFontSize());

  /** Indica se a sidebar está recolhida (apenas ícones). */
  readonly sidebarCollapsed = signal(false);

  /** Indica se o overlay da sidebar mobile está aberto. */
  readonly mobileSidebarOpen = signal(false);

  /** Derivado: true quando o tema resolvido (após detecção do sistema) é dark. */
  readonly isDark = computed(() => {
    const t = this.theme();
    if (t === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return t === 'dark';
  });

  constructor() {
    // Apply theme class and persist whenever the stored preference changes.
    effect(() => {
      const stored = this.theme();
      const resolved = this.resolveTheme(stored);
      document.documentElement.classList.toggle('dark', resolved === 'dark');
      localStorage.setItem(ThemeService.STORAGE_KEY, stored);
    });

    // Apply density class on <html> whenever density signal changes.
    effect(() => {
      const d = this.density();
      const el = document.documentElement;
      el.classList.remove('density-compact', 'density-expanded');
      if (d === 'compact' || d === 'expanded') el.classList.add(`density-${d}`);
      localStorage.setItem(ThemeService.DENSITY_KEY, d);
    });

    // Apply font-size class on <html> whenever fontSize signal changes.
    effect(() => {
      const fs = this.fontSize();
      const el = document.documentElement;
      el.classList.remove('font-size-small', 'font-size-large');
      if (fs === 'small' || fs === 'large') el.classList.add(`font-size-${fs}`);
      localStorage.setItem(ThemeService.FONT_SIZE_KEY, fs);
    });

    // When the user explicitly picks 'system', listen for OS changes.
    effect(() => {
      const t = this.theme();
      if (t !== 'system') return;

      const mq = window.matchMedia('(prefers-color-scheme: dark)');
      const handler = () => {
        // The computed isDark will automatically update.
        // Force a re-render of the dark class by re-setting the theme signal.
        document.documentElement.classList.toggle('dark', mq.matches);
      };

      mq.addEventListener('change', handler);
      // Apply immediately on 'system' selection.
      document.documentElement.classList.toggle('dark', mq.matches);
    });
  }

  /**
   * Aplica um tema específico imediatamente e persiste a preferência.
   *
   * @param theme - 'light', 'dark' ou 'system'
   */
  applyTheme(theme: 'light' | 'dark' | 'system'): void {
    this.theme.set(theme);
  }

  /**
   * Aplica a densidade da interface e persiste no localStorage.
   *
   * @param density - 'compact', 'normal' ou 'expanded'
   */
  applyDensity(density: 'compact' | 'normal' | 'expanded'): void {
    this.density.set(density);
  }

  /**
   * Aplica o tamanho da fonte e persiste no localStorage.
   *
   * @param size - 'small', 'medium' ou 'large'
   */
  applyFontSize(size: 'small' | 'medium' | 'large'): void {
    this.fontSize.set(size);
  }

  /** Alterna entre temas light e dark (ignora system). */
  toggleTheme(): void {
    const current = this.theme();
    if (current === 'light') {
      this.theme.set('dark');
    } else if (current === 'dark') {
      this.theme.set('light');
    } else {
      // If currently system, resolve to the opposite of the resolved theme
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      this.theme.set(isDark ? 'light' : 'dark');
    }
  }

  /** Alterna o estado de recolhimento da sidebar. */
  toggleSidebar(): void {
    this.sidebarCollapsed.update((v) => !v);
  }

  /** Alterna o overlay da sidebar mobile. */
  toggleMobileSidebar(): void {
    this.mobileSidebarOpen.update((v) => !v);
  }

  /** Fecha o overlay da sidebar mobile. */
  closeMobileSidebar(): void {
    this.mobileSidebarOpen.set(false);
  }

  private loadTheme(): 'light' | 'dark' | 'system' {
    if (typeof window === 'undefined') return 'dark';
    const stored = localStorage.getItem(ThemeService.STORAGE_KEY);
    if (stored === 'light' || stored === 'dark' || stored === 'system') return stored;
    return 'dark';
  }

  private loadDensity(): 'compact' | 'normal' | 'expanded' {
    if (typeof window === 'undefined') return 'normal';
    const stored = localStorage.getItem(ThemeService.DENSITY_KEY);
    if (stored === 'compact' || stored === 'normal' || stored === 'expanded') return stored;
    return 'normal';
  }

  private loadFontSize(): 'small' | 'medium' | 'large' {
    if (typeof window === 'undefined') return 'medium';
    const stored = localStorage.getItem(ThemeService.FONT_SIZE_KEY);
    if (stored === 'small' || stored === 'medium' || stored === 'large') return stored;
    return 'medium';
  }

  private resolveTheme(t: 'light' | 'dark' | 'system'): 'light' | 'dark' {
    if (t === 'system') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    return t;
  }
}
