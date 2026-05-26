import { type CanDeactivateFn } from '@angular/router';
import { type PreferencesStore } from '../services/preferences.store';

/**
 * Confirma com o usuário antes de sair de uma rota quando há alterações não salvas.
 *
 * O componente protegido deve expor uma propriedade `store` do tipo `PreferencesStore`.
 *
 * @example
 * ```typescript
 * // Na configuração da rota:
 * {
 *   path: 'preferences',
 *   canDeactivate: [unsavedChangesGuard],
 * }
 * ```
 */
export const unsavedChangesGuard: CanDeactivateFn<{ store?: PreferencesStore }> = (component) => {
  const store = component?.store;

  if (store?.isDirty && store.isDirty()) {
    return confirm('Descartar alterações não salvas?');
  }

  return true;
};
