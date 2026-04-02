import {
  Component,
  ChangeDetectionStrategy,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../core/services/auth.service';
import { AuthStoreService } from '../../../core/services/auth-store.service';
import { AfAvatarComponent } from '../../../shared/components/avatar/avatar';
import { LucideAngularModule } from 'lucide-angular';

/**
 * User profile avatar + dropdown menu (My Account, Logout).
 *
 * @example
 * ```html
 * <af-user-profile />
 * ```
 */
@Component({
  selector: 'af-user-profile',
  standalone: true,
  imports: [AfAvatarComponent, LucideAngularModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './user-profile.html',
  host: {
    '(document:click)': 'onDocumentClick($event)',
  },
})
export class UserProfileComponent {
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStoreService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly open = signal(false);
  readonly isLoggingOut = signal(false);

  readonly userName = computed(() => this.authStore.user()?.name ?? 'Usuário');
  readonly userEmail = computed(() => this.authStore.user()?.email ?? '');
  readonly avatarUrl = computed(() => this.authStore.user()?.avatar_url ?? null);
  readonly userInitials = computed(() => this.getInitials(this.userName()));
  readonly userRoleLabel = computed(() =>
    this.authStore.user()?.is_supervisor === true ? 'Supervisor' : 'Usuário',
  );

  protected onDocumentClick(event: Event): void {
    const el = event.target as HTMLElement;
    if (!el.closest('af-user-profile')) {
      this.open.set(false);
    }
  }

  protected logout(): void {
    if (this.isLoggingOut()) {
      return;
    }

    this.isLoggingOut.set(true);

    this.authService
      .logout()
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        finalize(() => this.isLoggingOut.set(false)),
      )
      .subscribe({
        next: () => this.finishLogout(),
        error: () => this.finishLogout(),
      });
  }

  private finishLogout(): void {
    this.authStore.logout();
    this.open.set(false);
    void this.router.navigate(['/login']);
  }

  private getInitials(name: string): string {
    const parts = name
      .trim()
      .split(' ')
      .filter((part) => part.length > 0);

    if (parts.length === 0) {
      return 'US';
    }

    if (parts.length === 1) {
      return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }
}
