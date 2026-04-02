import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { ThemeService } from '../../../core/services/theme.service';
import { ThemeTogglerComponent } from './theme-toggler';
import { NotificationDropdownComponent } from './notification-dropdown';
import { UserProfileComponent } from './user-profile';
import { LucideAngularModule } from 'lucide-angular';
import { SearchSpotlightService } from '../../../core/services/search-spotlight.service';

/**
 * Top navigation bar with search, actions, and user profile.
 * Sticky at the top of the page content area.
 *
 * @example
 * ```html
 * <af-topbar />
 * ```
 */
@Component({
  selector: 'af-topbar',
  standalone: true,
  imports: [
    ThemeTogglerComponent,
    NotificationDropdownComponent,
    UserProfileComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './topbar.html',
})
export class TopbarComponent {
  protected readonly theme = inject(ThemeService);
  protected readonly spotlight = inject(SearchSpotlightService);

  protected openSearch(): void {
    this.spotlight.open();
  }
}
