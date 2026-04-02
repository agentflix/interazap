import { inject, Injectable } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { type RouterStateSnapshot, TitleStrategy } from '@angular/router';

/**
 * Custom title strategy that appends " - InteraZap" suffix to route titles.
 * Reads `data.title` from the deepest activated route snapshot.
 */
@Injectable()
export class AppTitleStrategy extends TitleStrategy {
  private readonly title = inject(Title);

  private readonly suffix = 'InteraZap';

  override updateTitle(snapshot: RouterStateSnapshot): void {
    const pageTitle = this.resolveTitle(snapshot);
    this.title.setTitle(pageTitle ? `${pageTitle} - ${this.suffix}` : this.suffix);
  }

  /**
   * Walks the route tree to find the deepest route's `data.title`.
   * Supports the existing `data: { title: '...' }` pattern across all routes.
   */
  private resolveTitle(snapshot: RouterStateSnapshot): string | undefined {
    let route = snapshot.root;

    while (route.firstChild) {
      route = route.firstChild;
    }

    return (route.data['title'] as string | undefined) ?? (route.title as string | undefined);
  }
}
