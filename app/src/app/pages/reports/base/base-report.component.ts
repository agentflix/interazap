import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import type { Observable } from 'rxjs';
import { ReportsService } from '@core/services/reports.service';
import { type ReportFilter, type ReportResponse } from '@shared/models/report.model';
import { type AfSelectOption } from '@shared/components';

interface ReportChartSeriesItem<TData = unknown> {
  name: string;
  data: TData;
}
type DefaultReportChartSeries = ReportChartSeriesItem[];
export type NumericReportChartSeries = ReportChartSeriesItem<number[]>[];

/**
 * Base class for all report components.
 *
 * Provides common signals, form controls, filter management, export, and data loading logic.
 * Subclasses must:
 * - Call `this.setupReport()` in constructor after setting up specific dependencies
 * - Implement `getReportKey()` to return the report type identifier ('billing', 'chat-volume', etc.)
 * - Implement `loadReportData(filters)` to fetch data for their specific report
 * - Implement `onDataLoaded(data)` to process fetched data and build charts if needed
 *
 * @template T The type of the report data payload
 * @template TChartSeries The type of the chart series payload
 *
 * @example
 * export class MyReportComponent extends BaseReportComponent<MyReportData> {
 *   constructor() {
 *     super();
 *     this.setupReport();
 *   }
 *
 *   protected override getReportKey(): string {
 *     return 'my-report';
 *   }
 *
 *   protected override loadReportData(filters: ReportFilter): Observable<ReportResponse<MyReportData>> {
 *     return this.reportsService.getMyReport(filters);
 *   }
 *
 *   protected override onDataLoaded(data: MyReportData): void {
 *     // Build charts or process data
 *     this.buildChart(data);
 *   }
 * }
 */
export abstract class BaseReportComponent<
  T,
  TChartSeries extends DefaultReportChartSeries = DefaultReportChartSeries,
> {
  protected readonly reportsService = inject(ReportsService);
  protected readonly destroyRef = inject(DestroyRef);

  // ========== Common Signals ==========
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly isExporting = signal(false);
  readonly isFilterOpen = signal(false);
  readonly data = signal<T | null>(null);
  readonly filters = signal<ReportFilter>({ start_date: '', end_date: '' });

  // ========== Filter Controls ==========
  readonly searchControl = new FormControl<string>('', { nonNullable: true });
  readonly granularityControl = new FormControl<'day' | 'week' | 'month'>('month', {
    nonNullable: true,
  });
  readonly startDateControl = new FormControl<string>('', { nonNullable: true });
  readonly endDateControl = new FormControl<string>('', { nonNullable: true });

  readonly granularityOptions: AfSelectOption[] = [
    { label: 'Dia', value: 'day' },
    { label: 'Semana', value: 'week' },
    { label: 'Mês', value: 'month' },
  ];

  // ========== Chart Signals (for components that use charts) ==========
  readonly chartSeries = signal<TChartSeries>([] as unknown as TChartSeries);
  readonly chartCategories = signal<string[]>([]);
  readonly chartExtra = signal<Record<string, unknown>>({});

  /**
   * Initialize default dates (last 30 days) and load initial data.
   * Must be called from subclass constructor after dependencies are injected.
   */
  protected setupReport(): void {
    const startDate = this.getDefaultStartDate();
    const endDate = this.getDefaultEndDate();

    this.startDateControl.setValue(startDate, { emitEvent: false });
    this.endDateControl.setValue(endDate, { emitEvent: false });

    const initialFilters: ReportFilter = {
      start_date: startDate,
      end_date: endDate,
      granularity: this.granularityControl.value,
    };

    this.filters.set(initialFilters);
    this.loadData(initialFilters);
  }

  /**
   * Get default start date (30 days ago).
   */
  private getDefaultStartDate(): string {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
  }

  /**
   * Get default end date (today).
   */
  private getDefaultEndDate(): string {
    return new Date().toISOString().split('T')[0];
  }

  /**
   * Open filter drawer.
   */
  openFilters(): void {
    this.isFilterOpen.set(true);
  }

  /**
   * Close filter drawer.
   */
  closeFilters(): void {
    this.isFilterOpen.set(false);
  }

  /**
   * Clear all filters to defaults.
   */
  clearFilters(): void {
    this.searchControl.setValue('');
    this.granularityControl.setValue('month');
    this.startDateControl.setValue(this.getDefaultStartDate());
    this.endDateControl.setValue(this.getDefaultEndDate());
  }

  /**
   * Apply filters and reload data.
   */
  applyFilters(): void {
    const filters: ReportFilter = {
      start_date: this.startDateControl.value || '',
      end_date: this.endDateControl.value || '',
      granularity: this.granularityControl.value,
    };

    this.filters.set(filters);
    this.loadData(filters);
    this.closeFilters();
  }

  /**
   * Export report in the specified format.
   */
  onExport(payload: { format: 'csv' | 'xlsx' }): void {
    this.isExporting.set(true);
    const format = payload.format;

    this.reportsService
      .exportReport(this.getReportKey(), format, this.filters())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob: Blob) => {
          this.downloadBlob(blob, format);
          this.isExporting.set(false);
        },
        error: () => this.isExporting.set(false),
      });
  }

  /**
   * Retry loading data.
   */
  retry(): void {
    this.loadData(this.filters());
  }

  /**
   * Internal method to load data and handle responses.
   */
  private loadData(filters: ReportFilter): void {
    if (!filters.start_date || !filters.end_date) return;

    this.isLoading.set(true);
    this.hasError.set(false);

    this.loadReportData(filters)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response: ReportResponse<T>) => {
          this.data.set(response.data.data);
          this.onDataLoaded(response.data.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }

  /**
   * Download blob as file.
   */
  private downloadBlob(blob: Blob, format: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${this.getReportKey()}_${new Date().toISOString().split('T')[0]}.${format}`;
    a.click();
    URL.revokeObjectURL(url);
  }

  /**
   * Return the report key for API calls ('billing', 'chat-volume', etc.).
   *
   * @returns Report identifier
   */
  protected abstract getReportKey(): string;

  /**
   * Fetch report data from the service.
   *
   * @param filters Report filters
   * @returns Observable of report response
   */
  protected abstract loadReportData(filters: ReportFilter): Observable<ReportResponse<T>>;

  /**
   * Optional hook to process fetched data and build charts.
   * Override only if this report needs special data processing.
   *
   * @param data Fetched report data
   */
  protected onDataLoaded(data: T): void {
    // Default: no-op. Subclasses override if needed (e.g., to build charts).
  }
}
