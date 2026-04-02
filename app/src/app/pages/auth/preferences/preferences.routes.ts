import { type Routes } from '@angular/router';
import { PreferencesComponent } from './preferences';
import { unsavedChangesGuard } from '../../../core/guards/unsaved-changes.guard';

/**
 * Route configuration for the user preferences page.
 * The `canDeactivate` guard prompts before leaving with unsaved changes.
 */
export default {
  path: '',
  component: PreferencesComponent,
  canDeactivate: [unsavedChangesGuard],
  data: { title: 'Preferências' },
} satisfies Routes[number];
