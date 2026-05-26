import {
  type OnInit,
  Component,
  ChangeDetectionStrategy,
  signal,
  output,
  computed,
  inject,
} from '@angular/core';

import { CommonModule, DatePipe } from '@angular/common';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { AfPopoverComponent } from '@shared/components/popover/popover';
import { AfButtonComponent } from '@shared/components/button/button';
import { AfDateRangePickerComponent } from '@shared/components/date-range-picker/date-range-picker';
import { type DashboardFilterOption, type DateRange } from '../../models/dashboard.model';

/**
 * Filtro de período do dashboard — seletor de intervalo de datas com opções predefinidas e período personalizado.
 */
@Component({
  selector: 'app-dashboard-date-filter',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    LucideAngularModule,
    AfPopoverComponent,
    AfButtonComponent,
    AfDateRangePickerComponent,
  ],
  providers: [DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './date-filter.html',
})
export class DashboardDateFilterComponent implements OnInit {
  private readonly datePipe = inject(DatePipe);

  /** Emitido quando o intervalo de datas é alterado. */
  readonly filterChanged = output<DateRange>();

  /** Opção de período selecionada atualmente. */
  protected readonly selectedOption = signal<DashboardFilterOption>('last30');

  /** Controles do período personalizado. */
  protected readonly customStart = new FormControl('', {
    nonNullable: true,
    validators: [Validators.required],
  });
  protected readonly customEnd = new FormControl('', {
    nonNullable: true,
    validators: [Validators.required],
  });

  protected readonly filterOptions: { label: string; value: DashboardFilterOption }[] = [
    { label: 'Hoje', value: 'today' },
    { label: 'Ontem', value: 'yesterday' },
    { label: 'Últimos 7 dias', value: 'last7' },
    { label: 'Últimos 15 dias', value: 'last15' },
    { label: 'Últimos 30 dias', value: 'last30' },
  ];

  /** Rótulo calculado para o botão de acionamento do filtro. */
  protected readonly currentLabel = computed(() => {
    const opt = this.selectedOption();
    if (opt === 'custom') {
      const start = this.customStart.value;
      const end = this.customEnd.value;
      if (start && end) {
        return `${this.formatDate(start)} - ${this.formatDate(end)}`;
      }
      return 'Personalizado';
    }
    return this.filterOptions.find((o) => o.value === opt)?.label ?? 'Período';
  });

  ngOnInit(): void {
    const range = this.calculateRange('last30');
    this.filterChanged.emit(range);
  }

  protected selectOption(option: DashboardFilterOption): void {
    this.selectedOption.set(option);

    if (option !== 'custom') {
      const range = this.calculateRange(option);
      this.filterChanged.emit(range);
    }
  }

  protected applyCustomRange(): void {
    const from = this.customStart.value;
    const to = this.customEnd.value;

    if (from && to) {
      this.filterChanged.emit({
        from,
        to,
        option: 'custom',
        label: `${this.formatDate(from)} - ${this.formatDate(to)}`,
      });
    }
  }

  private calculateRange(option: DashboardFilterOption): DateRange {
    const now = new Date();
    const from = new Date();
    const to = new Date();
    let label = '';

    switch (option) {
      case 'today':
        label = 'Hoje';
        break;
      case 'yesterday':
        from.setDate(now.getDate() - 1);
        to.setDate(now.getDate() - 1);
        label = 'Ontem';
        break;
      case 'last7':
        from.setDate(now.getDate() - 6);
        label = 'Últimos 7 dias';
        break;
      case 'last15':
        from.setDate(now.getDate() - 14);
        label = 'Últimos 15 dias';
        break;
      case 'last30':
        from.setDate(now.getDate() - 29);
        label = 'Últimos 30 dias';
        break;
    }

    return {
      from: this.toIsoDate(from),
      to: this.toIsoDate(to),
      option,
      label,
    };
  }

  private toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  private formatDate(isoDate: string): string {
    return this.datePipe.transform(isoDate, 'dd/MM/yyyy') ?? isoDate;
  }
}
