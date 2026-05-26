import { inject, Injectable } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { type RouterStateSnapshot, TitleStrategy } from '@angular/router';

/**
 * Estratégia de título personalizada que acrescenta o sufixo " - InteraZap" aos títulos de rota.
 * Lê `data.title` do snapshot de rota mais profundo ativado.
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
   * Percorre a árvore de rotas para encontrar o `data.title` da rota mais profunda.
   * Suporta o padrão `data: { title: '...' }` utilizado em todas as rotas da aplicação.
   */
  private resolveTitle(snapshot: RouterStateSnapshot): string | undefined {
    let route = snapshot.root;

    while (route.firstChild) {
      route = route.firstChild;
    }

    return (route.data['title'] as string | undefined) ?? (route.title as string | undefined);
  }
}
