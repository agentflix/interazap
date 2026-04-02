import { RouterOutlet } from '@angular/router';
import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Componente raiz do módulo de relatórios.
 * Apenas um wrapper para o router-outlet.
 */
@Component({
  selector: 'app-reports',
  standalone: true,
  imports: [RouterOutlet],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './reports.html',
})
// eslint-disable-next-line @typescript-eslint/no-extraneous-class
export class ReportsComponent {}

export default ReportsComponent;
