import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type UserPreferences } from '@shared/models/preferences.model';
import { PreferencesService } from './preferences.service';

/**
 * Signal-based store for user preferences.
 * Manages loading, saving, dirty-state tracking, and error handling.
 * Signals drive Angular's automatic change detection, so no ChangeDetectorRef is needed.
 *
 * @example
 * ```ts
 * const store = inject(PreferencesStore);
 * store.load();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class PreferencesStore {
  private readonly service = inject(PreferencesService);
  private readonly destroyRef = inject(DestroyRef);

  // ── State signals ────────────────────────────────────────────────────────────

  /** Current preferences loaded from the backend. Null until first load. */
  readonly preferences = signal<UserPreferences | null>(null);

  /** True when there are unsaved changes in the form. */
  readonly isDirty = signal(false);

  /** True while a save request is in flight. */
  readonly isSaving = signal(false);

  /** True while the initial load request is in flight. */
  readonly isLoading = signal(false);

  /** Error message from the last failed operation. */
  readonly error = signal<string | null>(null);

  // ── Computed ─────────────────────────────────────────────────────────────────

  /** Convenience alias — true when there are unsaved changes. */
  readonly hasUnsavedChanges = computed(() => this.isDirty());

  // ── Actions ──────────────────────────────────────────────────────────────────

  /**
   * Load preferences from the backend.
   * On success, replaces the current preferences and resets dirty state.
   * On failure, sets the error signal.
   */
  load(): void {
    this.isLoading.set(true);
    this.error.set(null);

    this.service
      .getPreferences()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.preferences.set(response.data);
          this.isDirty.set(false);
          this.isLoading.set(false);
        },
        error: (err) => {
          this.error.set(err.error?.message ?? 'Não foi possível carregar as preferências.');
          this.isLoading.set(false);
        },
      });
  }

  /**
   * Save the given preferences to the backend.
   * Resets dirty state on success; preserves dirty state on failure.
   *
   * @param prefs - Complete preferences object to persist
   */
  save(prefs: UserPreferences): void {
    if (this.isSaving()) {
      return;
    }

    this.isSaving.set(true);
    this.error.set(null);

    this.service
      .updatePreferences(prefs)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.preferences.set(response.data);
          this.isDirty.set(false);
          this.isSaving.set(false);
        },
        error: (err) => {
          this.error.set(err.error?.message ?? 'Não foi possível salvar as preferências.');
          this.isSaving.set(false);
        },
      });
  }

  /**
   * Reload preferences from the backend, discarding any unsaved changes.
   */
  reset(): void {
    this.load();
  }

  /**
   * Mark the form as having unsaved changes.
   * Call this whenever any preference control value changes.
   */
  markDirty(): void {
    this.isDirty.set(true);
  }
}
