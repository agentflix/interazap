/**
 * Reexporta o CurrencyPipe nativo do Angular para uso consistente na aplicação.
 *
 * @remarks
 * Este reexport permite usar o currency pipe em imports de componentes standalone
 * sem referenciar o módulo common do Angular diretamente.
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
 * export class MeuComponent {
 *   amount = 1234.56;
 * }
 * ```
 */
export { CurrencyPipe } from '@angular/common';
