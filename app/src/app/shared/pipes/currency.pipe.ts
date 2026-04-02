/**
 * Re-exports Angular's built-in CurrencyPipe for consistent usage across the application.
 *
 * @remarks
 * This re-export allows the currency pipe to be used in standalone component
 * imports without referencing the Angular common module directly.
 *
 * @see {@link https://angular.io/api/common/CurrencyPipe|Angular CurrencyPipe}
 *
 * @example
 * ```typescript
 * import { CurrencyPipe } from '@shared/pipes/currency.pipe';
 *
 * @Component({
 *   standalone: true,
 *   imports: [CurrencyPipe],
 *   template: `{{ amount | currency:'BRL' }}`
 * })
 * export class MyComponent {
 *   amount = 1234.56;
 * }
 * ```
 */
export { CurrencyPipe } from '@angular/common';
