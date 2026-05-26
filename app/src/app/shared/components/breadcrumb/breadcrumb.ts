import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfBreadcrumbItem } from './breadcrumb.model';
export * from './breadcrumb.model';



/**
 * Trilha de navegação (breadcrumb) indicando a hierarquia de páginas.
 *
 * Contexto: utilizado no topo das páginas internas para orientar o usuário
 * sobre sua localização na estrutura do sistema.
 *
 * @example
 * ```html
 * <af-breadcrumb [items]="[{label:'Início',href:'/'},{label:'CRM',href:'/crm'},{label:'Contatos'}]" />
 * ```
 */
@Component({
  selector: 'af-breadcrumb',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './breadcrumb.html',
})
export class AfBreadcrumbComponent {
  /** Itens da trilha de navegação */
  readonly items = input<AfBreadcrumbItem[]>([]);
}
