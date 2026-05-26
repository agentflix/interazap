import { Component, ChangeDetectionStrategy } from '@angular/core';

/**
 * Contêiner card com slots nomeados para construção de páginas de lista padrão.
 *
 * Contexto: utilizado como estrutura base em todas as páginas de listagem
 * do sistema, organizando cabeçalho, filtros, tabela e paginação.
 *
 * @example
 * ```html
 * <af-base-list>
 *   <div header>Título + ações</div>
 *   <div filters>Busca + chips de filtro</div>
 *   <div table>Conteúdo da tabela</div>
 *   <div pagination>Controles de paginação</div>
 * </af-base-list>
 * ```
 */
@Component({
  selector: 'af-base-list',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './base-list.html',
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class AfBaseListComponent {}
