import { Injectable, signal, computed, effect } from '@angular/core';

/**
 * Manages the application theme (light/dark/system) and sidebar collapse state.
 * Persists theme preference in localStorage.
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

  /** Current theme — 'light', 'dark', or 'system'. */
  readonly theme = signal<'light' | 'dark' | 'system'>(this.loadTheme());

  /** Current interface density. */
  readonly density = signal<'compact' | 'normal' | 'expanded'>(this.loadDensity());

  /** Current font size. */
  readonly fontSize = signal<'small' | 'medium' | 'large'>(this.loadFontSize());

  /** Whether the sidebar is collapsed (icons only) */
  readonly sidebarCollapsed = signal(false);

  /** Whether the mobile sidebar overlay is open */
  readonly mobileSidebarOpen = signal(false);

  /** Derived: true when the resolved theme (after system detection) is dark */
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
   * Apply a specific theme immediately and persist the preference.
   *
   * @param theme - 'light', 'dark', or 'system'
   */
  applyTheme(theme: 'light' | 'dark' | 'system'): void {
    this.theme.set(theme);
  }

  /**
   * Apply interface density and persist to localStorage.
   *
   * @param density - 'compact', 'normal', or 'expanded'
   */
  applyDensity(density: 'compact' | 'normal' | 'expanded'): void {
    this.density.set(density);
  }

  /**
   * Apply font size and persist to localStorage.
   *
   * @param size - 'small', 'medium', or 'large'
   */
  applyFontSize(size: 'small' | 'medium' | 'large'): void {
    this.fontSize.set(size);
  }

  /** Toggle between light and dark themes (ignores system). */
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

  /** Toggle sidebar collapsed state */
  toggleSidebar(): void {
    this.sidebarCollapsed.update((v) => !v);
  }

  /** Toggle mobile sidebar overlay */
  toggleMobileSidebar(): void {
    this.mobileSidebarOpen.update((v) => !v);
  }

  /** Close mobile sidebar */
  closeMobileSidebar(): void {
    this.mobileSidebarOpen.set(false);
  }

  private loadTheme(): 'light' | 'dark' | 'system' {
    if (typeof window === 'undefined') return 'light';
    const stored = localStorage.getItem(ThemeService.STORAGE_KEY);
    if (stored === 'light' || stored === 'dark' || stored === 'system') return stored;
    return 'system';
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
