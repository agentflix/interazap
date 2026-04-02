import { type CanDeactivateFn } from '@angular/router';
import { type PreferencesStore } from '../services/preferences.store';

/**
 * Guard that prompts the user before leaving a route if there are unsaved changes.
 *
 * @example
 * ```typescript
 * // In route config:
 * {
 *   path: 'preferences',
 *   canDeactivate: [unsavedChangesGuard],
 * }
 * ```
 *
 * The guarded component must expose a `store` property of type `PreferencesStore`.
 */
export const unsavedChangesGuard: CanDeactivateFn<{ store?: PreferencesStore }> = (component) => {
  const store = component?.store;

  if (store?.isDirty && store.isDirty()) {
    return confirm('Descartar alterações não salvas?');
  }

  return true;
};
