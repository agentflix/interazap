import { TestBed } from '@angular/core/testing';
import { Title } from '@angular/platform-browser';
import {
  type ActivatedRouteSnapshot,
  type RouterStateSnapshot,
  TitleStrategy,
} from '@angular/router';
import { beforeEach, describe, expect, it } from 'vitest';
import { AppTitleStrategy } from './app-title.strategy';

/**
 * Creates a minimal RouterStateSnapshot stub with the given data.title
 * chain for testing the AppTitleStrategy.
 */
function createSnapshot(dataTitle?: string): RouterStateSnapshot {
  const leaf = {
    data: dataTitle ? { title: dataTitle } : {},
    firstChild: null as ActivatedRouteSnapshot | null,
    title: undefined,
  } as unknown as ActivatedRouteSnapshot;

  const root = {
    data: {},
    firstChild: leaf,
    title: undefined,
  } as unknown as ActivatedRouteSnapshot;

  return { root, url: '/' } as unknown as RouterStateSnapshot;
}

describe('AppTitleStrategy', () => {
  let strategy: AppTitleStrategy;
  let titleService: Title;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [{ provide: TitleStrategy, useClass: AppTitleStrategy }, Title],
    });

    strategy = TestBed.inject(TitleStrategy) as AppTitleStrategy;
    titleService = TestBed.inject(Title);
  });

  it('should set "PageName - AgentFlix" when data.title is present', () => {
    const snapshot = createSnapshot('Dashboard');
    strategy.updateTitle(snapshot);

    expect(titleService.getTitle()).toBe('Dashboard - AgentFlix');
  });

  it('should set only "AgentFlix" when no title is defined', () => {
    const snapshot = createSnapshot();
    strategy.updateTitle(snapshot);

    expect(titleService.getTitle()).toBe('AgentFlix');
  });

  it('should work with different page titles', () => {
    const cases = ['Login', 'Contatos', 'Funil de Vendas', 'Configurações'];

    for (const title of cases) {
      const snapshot = createSnapshot(title);
      strategy.updateTitle(snapshot);

      expect(titleService.getTitle()).toBe(`${title} - AgentFlix`);
    }
  });
});
