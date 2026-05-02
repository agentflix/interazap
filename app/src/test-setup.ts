import '@angular/compiler';
import '@analogjs/vitest-angular/setup-snapshots';
import '@angular/common/locales/global/pt';
import { beforeEach } from 'vitest';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import {
  LOCALE_ID,
  importProvidersFrom,
  type OnChanges,
  type SimpleChanges,
  type EnvironmentProviders,
  type Provider,
} from '@angular/core';
import { registerLocaleData } from '@angular/common';
import localePtBr from '@angular/common/locales/pt';
import { LucideAngularComponent, LucideAngularModule, icons, LUCIDE_ICONS } from 'lucide-angular';
import { provideIcons } from '@ng-icons/core';
import {
  lucideAlertTriangle,
  lucideChevronDown,
  lucideEye,
  lucideGitBranch,
  lucidePencil,
  lucidePlus,
  lucideUser,
} from '@ng-icons/lucide';
import { setupTestBed } from '@analogjs/vitest-angular/setup-testbed';
import { TestBed, type TestModuleMetadata } from '@angular/core/testing';

const LUCIDE_MISSING_ICON_ERROR_FRAGMENT =
  'icon has not been provided by any available icon providers';

const isMissingLucideIconError = (error: unknown): boolean => {
  if (error instanceof Error) {
    return error.message.includes(LUCIDE_MISSING_ICON_ERROR_FRAGMENT);
  }

  return false;
};

const patchLucideMissingIconsInTests = (): void => {
  const lucidePrototype = LucideAngularComponent.prototype as OnChanges;
  const originalNgOnChanges = lucidePrototype.ngOnChanges;

  if (!originalNgOnChanges) {
    return;
  }

  lucidePrototype.ngOnChanges = function patchedNgOnChanges(changes: SimpleChanges): void {
    try {
      originalNgOnChanges.call(this, changes);
    } catch (error) {
      if (!isMissingLucideIconError(error)) {
        throw error;
      }
    }
  };
};

patchLucideMissingIconsInTests();

registerLocaleData(localePtBr, 'pt-BR');

// Mock window.matchMedia for jsdom environment (used by ThemeService)
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  }),
});

const originalGetComputedStyle = window.getComputedStyle.bind(window);
window.getComputedStyle = ((element: Element, pseudoElt?: string | null) => {
  void pseudoElt;
  return originalGetComputedStyle(element);
}) as typeof window.getComputedStyle;

if (!('ResizeObserver' in globalThis)) {
  class MockResizeObserver implements ResizeObserver {
    observe(_target: Element): void {}

    unobserve(_target: Element): void {}

    disconnect(): void {}
  }

  Object.defineProperty(globalThis, 'ResizeObserver', {
    writable: true,
    configurable: true,
    value: MockResizeObserver,
  });
}

if (!('IntersectionObserver' in globalThis)) {
  class MockIntersectionObserver implements IntersectionObserver {
    readonly root = null;

    readonly rootMargin = '0px';

    readonly thresholds = [0];

    observe(target: Element): void {
      void target;
    }

    unobserve(target: Element): void {
      void target;
    }

    disconnect(): void {
      return;
    }

    takeRecords(): IntersectionObserverEntry[] {
      return [];
    }
  }

  Object.defineProperty(globalThis, 'IntersectionObserver', {
    writable: true,
    configurable: true,
    value: MockIntersectionObserver,
  });
}

const globalTestProviders: (Provider | EnvironmentProviders)[] = [
  { provide: LOCALE_ID, useValue: 'pt-BR' },
  provideHttpClient(),
  provideHttpClientTesting(),
  importProvidersFrom(LucideAngularModule.pick(icons)),
  { provide: LUCIDE_ICONS, useValue: {} },
  ...provideIcons({
    eye: lucideEye,
    plus: lucidePlus,
    pencil: lucidePencil,
    user: lucideUser,
    'chevron-down': lucideChevronDown,
    'git-branch': lucideGitBranch,
    'git-branch-plus': lucideGitBranch,
    'alert-triangle': lucideAlertTriangle,
    'triangle-alert': lucideAlertTriangle,
  }),
];

const originalConfigureTestingModule = TestBed.configureTestingModule.bind(TestBed);
TestBed.configureTestingModule = (moduleDef: TestModuleMetadata = {}) => {
  const providers = [...(moduleDef.providers ?? []), ...globalTestProviders];
  return originalConfigureTestingModule({ ...moduleDef, providers });
};

beforeEach(() => {
  TestBed.configureTestingModule({
    providers: globalTestProviders as Provider[],
  });
});

setupTestBed({
  zoneless: true,
  providers: globalTestProviders as Provider[],
});
