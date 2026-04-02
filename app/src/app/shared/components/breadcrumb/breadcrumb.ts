import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfBreadcrumbItem } from './breadcrumb.model';
export * from './breadcrumb.model';



/**
 * AfBreadcrumbComponent — Navigation breadcrumb trail.
 *
 * @example
 * ```html
 * <af-breadcrumb [items]="[{label:'Home',href:'/'},{label:'CRM',href:'/crm'},{label:'Contatos'}]" />
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
  /** Breadcrumb trail items */
  readonly items = input<AfBreadcrumbItem[]>([]);
}
