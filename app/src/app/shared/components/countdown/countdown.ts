import {
  type OnInit,
  type OnDestroy,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
} from '@angular/core';
import { DecimalPipe } from '@angular/common';

/**
 * Temporizador de contagem regressiva exibindo o tempo restante.
 *
 * @example
 * ```html
 * <af-countdown [targetDate]="promoEnd" (finished)="onExpire()" />
 * ```
 */
@Component({
  selector: 'af-countdown',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './countdown.html',
  imports: [DecimalPipe],
})
export class AfCountdownComponent implements OnInit, OnDestroy {
  /** Data alvo em formato ISO */
  readonly targetDate = input('');

  /** Exibe dias/horas/minutos/segundos */
  readonly showDays = input(true);

  /** Emitido quando a contagem chega a zero */
  readonly finished = output<void>();

  protected readonly displayUnits = signal<{ label: string; value: number }[]>([]);

  private intervalId: ReturnType<typeof setInterval> | null = null;

  ngOnInit(): void {
    this.tick();
    this.intervalId = setInterval(() => this.tick(), 1000);
  }

  ngOnDestroy(): void {
    if (this.intervalId) clearInterval(this.intervalId);
  }

  private tick(): void {
    const target = new Date(this.targetDate()).getTime();
    const now = Date.now();
    const diff = Math.max(0, target - now);

    if (diff === 0) {
      this.finished.emit();
      if (this.intervalId) clearInterval(this.intervalId);
    }

    const totalSeconds = Math.floor(diff / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const units: { label: string; value: number }[] = [];
    if (this.showDays()) units.push({ label: 'Dias', value: days });
    units.push({ label: 'Horas', value: hours });
    units.push({ label: 'Min', value: minutes });
    units.push({ label: 'Seg', value: seconds });

    this.displayUnits.set(units);
  }
}
