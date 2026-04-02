import { type Routes } from '@angular/router';
import { TenantSettingsComponent } from './tenant-settings';

/**
 * Route configuration for the tenant settings page.
 * Protected by `permissionGuard` requiring `platform.tenants.manage`.
 */
export default {
  path: '',
  component: TenantSettingsComponent,
  data: { title: 'Configurações do Inquilino' },
} satisfies Routes[number];
