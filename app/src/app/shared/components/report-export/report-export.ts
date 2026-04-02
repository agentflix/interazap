import { Component, ChangeDetectionStrategy, input, output } from '@angular/core';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

import type { AfReportExportPayload } from './report-export.model';
export * from './report-export.model';



/**
 * AfReportExportComponent — Button group for exporting reports as CSV or XLSX.
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
  /** Whether buttons are disabled */
  readonly disabled = input(false);

  /** Emitted when an export action is triggered */
  readonly exported = output<AfReportExportPayload>();

  protected exportAs(format: 'csv' | 'xlsx'): void {
    this.exported.emit({ format });
  }
}
