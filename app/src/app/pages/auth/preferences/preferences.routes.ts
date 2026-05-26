import { type Routes } from '@angular/router';
import { PreferencesComponent } from './preferences';
import { unsavedChangesGuard } from '../../../core/guards/unsaved-changes.guard';

/**
 * Configuração de rota para a página de preferências do usuário.
 * O guard `canDeactivate` exibe confirmação ao sair com alterações não salvas.
 */
export default {
  path: '',
  component: PreferencesComponent,
  canDeactivate: [unsavedChangesGuard],
  data: { title: 'Preferências' },
} satisfies Routes[number];
