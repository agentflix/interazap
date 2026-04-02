import { Component, ChangeDetectionStrategy } from '@angular/core';

/**
 * AfBaseListComponent — Card wrapper with named content projection slots
 * for building standard list pages: header, filters, table, pagination.
 *
 * @example
 * ```html
 * <af-base-list>
 *   <div header>Title + actions</div>
 *   <div filters>Search + filter chips</div>
 *   <div table>Table content</div>
 *   <div pagination>Pagination controls</div>
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
