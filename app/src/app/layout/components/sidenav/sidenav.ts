import { Component, ChangeDetectionStrategy, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { ThemeService } from '../../../core/services/theme.service';
import { LucideAngularModule } from 'lucide-angular';
import { AuthStoreService } from '../../../core/services/auth-store.service';
import { SIDEBAR_MENU, type AiFeatureFlag, type SidebarMenuItem } from './menu-config';
import { AfScrollAreaComponent } from '../../../shared/components/scroll-area/scroll-area';

/**
 * Sidebar navigation panel — collapsible, neutral surface with hairline borders.
 * Renders menu items with icons, accordion groups, and active-route highlighting.
 * Light mode: white canvas with hairline border. Dark mode: neutral-900 surface.
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
  protected readonly menuItems = computed(() => this.removeOrphanTitles(this.filterMenuItems(SIDEBAR_MENU)));

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
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 dark:focus-visible:ring-offset-neutral-900',
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

  /**
   * Remove títulos que ficam órfãos (sem itens visíveis abaixo).
   */
  private removeOrphanTitles(items: SidebarMenuItem[]): SidebarMenuItem[] {
    const result: SidebarMenuItem[] = [];
    let pendingTitle: SidebarMenuItem | null = null;

    for (const item of items) {
      if (item.type === 'title') {
        // Se já tinha um título pendente, descarta (ficou órfão)
        pendingTitle = item;
        continue;
      }

      // Se tem um título pendente e agora encontrou um item visível, adiciona o título
      if (pendingTitle) {
        result.push(pendingTitle);
        pendingTitle = null;
      }

      result.push(item);
    }

    // Título pendente no final fica órfão, não adiciona
    return result;
  }

  private isMenuItemVisible(item: SidebarMenuItem): boolean {
    // Verificar permissão antes de tudo
    if (item.requiredPermission && !this.authStore.hasPermission(item.requiredPermission)) {
      return false;
    }

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
