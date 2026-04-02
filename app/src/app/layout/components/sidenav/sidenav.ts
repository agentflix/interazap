import { Component, ChangeDetectionStrategy, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { ThemeService } from '../../../core/services/theme.service';
import { LucideAngularModule, ChevronDown, ChevronRight } from 'lucide-angular';
import { AuthStoreService } from '../../../core/services/auth-store.service';
import { SIDEBAR_MENU, type AiFeatureFlag, type SidebarMenuItem } from './menu-config';
import { AfScrollAreaComponent } from '../../../shared/components/scroll-area/scroll-area';

/**
 * Sidebar navigation panel — collapsible, dark green (#1a3c34) background.
 * Renders menu items with icons, accordion groups, and active-route highlighting.
 */
@Component({
  selector: 'af-sidenav',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sidenav.html',
})
export class SidenavComponent {
  protected readonly theme = inject(ThemeService);
  private readonly authStore = inject(AuthStoreService);
  protected readonly menuItems = computed(() => this.filterMenuItems(SIDEBAR_MENU));

  private openAccordions = signal<Set<string>>(new Set());

  protected sidebarClasses(): string {
    return this.theme.sidebarCollapsed()
      ? 'w-[68px] bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800'
      : 'w-[260px] bg-white dark:bg-neutral-900 border-r border-neutral-200 dark:border-neutral-800';
  }

  protected menuItemClasses(): string {
    return [
      'flex items-center gap-3 px-3 py-2 text-sm',
      'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 hover:bg-neutral-100 dark:hover:text-neutral-50 dark:hover:bg-neutral-800',
      'rounded-md transition-colors duration-150 cursor-pointer',
    ].join(' ');
  }

  protected toggleAccordion(label: string): void {
    this.openAccordions.update((set) => {
      const next = new Set(set);
      if (next.has(label)) {
        next.delete(label);
      } else {
        next.add(label);
      }
      return next;
    });
  }

  protected isAccordionOpen(label: string): boolean {
    return this.openAccordions().has(label);
  }

  private filterMenuItems(items: SidebarMenuItem[]): SidebarMenuItem[] {
    return items.reduce<SidebarMenuItem[]>((acc, item) => {
      if (!this.isMenuItemVisible(item)) {
        return acc;
      }

      if (item.children) {
        const visibleChildren = this.filterMenuItems(item.children);
        if (item.type === 'accordion' && visibleChildren.length === 0) {
          return acc;
        }
        acc.push({ ...item, children: visibleChildren });
        return acc;
      }

      acc.push(item);
      return acc;
    }, []);
  }

  private isMenuItemVisible(item: SidebarMenuItem): boolean {
    if (item.requiresAiEnabled && !this.hasAiModuleEnabled()) {
      return false;
    }

    if (item.requiresAiDisabled && this.hasAiModuleEnabled()) {
      return false;
    }

    if (
      item.requiredAnyAiFeatures &&
      !item.requiredAnyAiFeatures.some((feature) => this.hasAiFeature(feature))
    ) {
      return false;
    }

    if (item.requiredAiFeature && !this.hasAiFeature(item.requiredAiFeature)) {
      return false;
    }

    return true;
  }

  private hasAiModuleEnabled(): boolean {
    return Boolean(this.authStore.user()?.tenant_plan?.ai_enabled);
  }

  private hasAiFeature(feature: AiFeatureFlag): boolean {
    return Boolean(this.authStore.user()?.tenant_plan?.features?.[feature]);
  }
}
