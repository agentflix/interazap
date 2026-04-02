import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

import type { AfStepItem } from './stepper.model';
export * from './stepper.model';



/**
 * AfStepperComponent — Multi-step progress indicator (wizard).
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
  /** Step definitions */
  readonly steps = input<AfStepItem[]>([]);

  /** Zero-based current step index */
  readonly currentStep = input(0);
}
