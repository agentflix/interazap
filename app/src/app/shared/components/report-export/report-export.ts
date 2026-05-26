import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

import type { AfReportExportPayload } from './report-export.model';
export * from './report-export.model';



/**
 * Grupo de botões para exportar relatórios como CSV ou XLSX.
 *
 * @example
 * ```html
 * <af-report-export (exported)="onExport($event)" />
 * ```
 */
@Component({
  selector: 'af-report-export',
  standalone: true,
  imports: [AfButtonComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-export.html',
})
export class AfReportExportComponent {
  /** Indica se os botões estão desabilitados */
  readonly disabled = input(false);

  /** Emitido quando uma exportação é acionada */
  readonly exported = output<AfReportExportPayload>();

  protected exportAs(format: 'csv' | 'xlsx'): void {
    this.exported.emit({ format });
  }
}
