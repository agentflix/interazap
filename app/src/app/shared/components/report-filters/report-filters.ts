import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  output,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { debounceTime } from 'rxjs/operators';
import { AfTextInputComponent } from '../text-input/text-input';
import { AfSelectInputComponent, type AfSelectOption } from '../select-input/select-input';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

import type { AfReportFilterPayload } from './report-filters.model';
export * from './report-filters.model';



/**
 * AfReportFiltersComponent — Horizontal filter bar for reports with
 * date range and status selector.
 *
 * @example
 * ```html
 * <af-report-filters
 *   [statusOptions]="statusOpts"
 *   (filtersApplied)="onFilter($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-report-filters',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfSelectInputComponent,
    AfButtonComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-filters.html',
})
export class AfReportFiltersComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);

  readonly showFields = input<string[]>(['granularity', 'channel']);

  /** Status filter options */
  readonly statusOptions = input<AfSelectOption[]>([]);

  /** Emitted when user clicks "Filtrar" */
  readonly filtersApplied = output<AfReportFilterPayload>();

  protected readonly startDateControl = new FormControl(this.defaultStartDate(), {
    nonNullable: true,
  });
  protected readonly endDateControl = new FormControl(this.defaultEndDate(), { nonNullable: true });
  protected readonly granularityControl = new FormControl<'day' | 'week' | 'month'>('month', {
    nonNullable: true,
  });
  protected readonly channelControl = new FormControl<'whatsapp' | 'telegram' | 'webchat' | ''>(
    '',
    {
      nonNullable: true,
    },
  );
  protected readonly statusControl = new FormControl('', { nonNullable: true });

  protected readonly granularityOptions: AfSelectOption[] = [
    { label: 'Dia', value: 'day' },
    { label: 'Semana', value: 'week' },
    { label: 'Mês', value: 'month' },
  ];

  protected readonly channelOptions: AfSelectOption[] = [
    { label: 'Todos', value: '' },
    { label: 'WhatsApp', value: 'whatsapp' },
    { label: 'Telegram', value: 'telegram' },
    { label: 'Webchat', value: 'webchat' },
  ];

  ngOnInit(): void {
    this.emitFilters();

    this.startDateControl.valueChanges
      .pipe(debounceTime(300), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.emitFilters());

    this.endDateControl.valueChanges
      .pipe(debounceTime(300), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.emitFilters());

    this.granularityControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.emitFilters());

    this.channelControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.emitFilters());
  }

  protected shouldShow(field: string): boolean {
    return this.showFields().includes(field);
  }

  protected applyFilters(): void {
    this.emitFilters();
  }

  private emitFilters(): void {
    this.filtersApplied.emit({
      startDate: this.startDateControl.value,
      endDate: this.endDateControl.value,
      granularity: this.granularityControl.value,
      channel: this.channelControl.value || undefined,
      status: this.statusControl.value,
    });
  }

  private defaultStartDate(): string {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
  }

  private defaultEndDate(): string {
    return new Date().toISOString().split('T')[0];
  }
}
