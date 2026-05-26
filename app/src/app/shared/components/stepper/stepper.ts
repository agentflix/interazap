import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfStepItem } from './stepper.model';
export * from './stepper.model';



/**
 * Indicador de progresso multi-etapas (wizard).
 *
 * @example
 * ```html
 * <af-stepper [steps]="steps" [currentStep]="2" />
 * ```
 */
@Component({
  selector: 'af-stepper',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './stepper.html',
})
export class AfStepperComponent {
  /** Definições das etapas */
  readonly steps = input<AfStepItem[]>([]);

  /** Índice da etapa atual (base zero) */
  readonly currentStep = input(0);
}
