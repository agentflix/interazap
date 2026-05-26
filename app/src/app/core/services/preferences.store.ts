import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type UserPreferences } from '@shared/models/preferences.model';
import { PreferencesService } from './preferences.service';

/**
 * Store baseado em signals para preferências do usuário.
 * Gerencia carregamento, salvamento, rastreamento de estado sujo e tratamento de erros.
 * Signals conduzem a detecção automática de mudanças do Angular, dispensando `ChangeDetectorRef`.
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

  /** Preferências atuais carregadas do backend. Nulo até o primeiro carregamento. */
  readonly preferences = signal<UserPreferences | null>(null);

  /** `true` quando há alterações não salvas no formulário. */
  readonly isDirty = signal(false);

  /** `true` enquanto uma requisição de salvamento está em andamento. */
  readonly isSaving = signal(false);

  /** `true` enquanto a requisição de carregamento inicial está em andamento. */
  readonly isLoading = signal(false);

  /** Mensagem de erro da última operação com falha. */
  readonly error = signal<string | null>(null);

  // ── Computed ─────────────────────────────────────────────────────────────────

  /** Alias de conveniência — `true` quando há alterações não salvas. */
  readonly hasUnsavedChanges = computed(() => this.isDirty());

  // ── Actions ──────────────────────────────────────────────────────────────────

  /**
   * Carrega as preferências do backend.
   * Em caso de sucesso, substitui as preferências atuais e reinicia o estado sujo.
   * Em caso de falha, define o signal de erro.
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
   * Salva as preferências fornecidas no backend.
   * Reinicia o estado sujo em caso de sucesso; preserva em caso de falha.
   *
   * @param prefs - Objeto completo de preferências a persistir
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
   * Recarrega as preferências do backend, descartando quaisquer alterações não salvas.
   */
  reset(): void {
    this.load();
  }

  /**
   * Marca o formulário como tendo alterações não salvas.
   * Chamar sempre que qualquer controle de preferência tiver o valor alterado.
   */
  markDirty(): void {
    this.isDirty.set(true);
  }
}
