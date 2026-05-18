import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthStoreService } from '../../../core/services/auth-store.service';
import { type AuthUser } from '@core/models/auth.model';
import { AuthService } from '../../../core/services/auth.service';
import { ToastService } from '../../../core/services/toast.service';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Banner vermelho exibido no topo da tela quando o usuário está impersonando um tenant.
 * Mostra o nome do tenant e um botão para retornar à sessão do super admin.
 */
@Component({
  selector: 'af-impersonation-banner',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './impersonation-banner.html',
})
export class ImpersonationBannerComponent {
  protected readonly authStore = inject(AuthStoreService);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);

  protected stopImpersonating(): void {
    this.authService.stopImpersonating().subscribe({
      next: (response) => {
        const user = response.data?.user;
        const token = response.data?.token;
        if (user && token) {
          this.authStore.stopImpersonation(user as unknown as AuthUser, token);
        }
        this.toast.success('Você retornou à sessão de super admin.');
        void this.router.navigate(['/platform/tenants']);
      },
      error: () => {
        this.toast.error('Erro ao encerrar a impersonação. Tente novamente.');
      },
    });
  }
}
