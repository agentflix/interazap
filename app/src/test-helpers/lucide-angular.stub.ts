/* eslint-disable @angular-eslint/component-selector */
import { Component, InjectionToken, Input, NgModule } from '@angular/core';

export const LUCIDE_ICONS = new InjectionToken('LucideIcons');

export class LucideIconProvider {
  constructor(private readonly _icons: Record<string, unknown> = {}) {
    void this._icons;
  }

  hasIcon(): boolean {
    return true;
  }

  getIcon(): unknown {
    return null;
  }
}

@Component({
  // Keep third-party selectors to match existing templates in tests.
  selector: 'lucide-angular, lucide-icon, i-lucide, span-lucide',
  template: '',
  standalone: true,
})
export class LucideAngularComponent {
  @Input() name?: string;
  @Input() img?: unknown;
  @Input() color?: string;
  @Input() size?: number | string;
  @Input() strokeWidth?: number | string;
  @Input() absoluteStrokeWidth?: boolean;
  @Input() class?: string;
}

@NgModule({
  imports: [LucideAngularComponent],
  exports: [LucideAngularComponent],
})
export class LucideAngularModule {
  private readonly _isStubModule = true;

  static pick(_icons: Record<string, unknown>): { ngModule: typeof LucideAngularModule } {
    return { ngModule: LucideAngularModule };
  }
}

export const icons: Record<string, unknown> = {};
