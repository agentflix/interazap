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
 * Classe base para todos os componentes de relatório.
 *
 * Fornece signals comuns, controles de formulário, gerenciamento de filtros, exportação e lógica de carregamento.
 * Subclasses devem:
 * - Chamar `this.setupReport()` no construtor após injetar as dependências específicas
 * - Implementar `getReportKey()` para retornar o identificador do tipo de relatório ('billing', 'chat-volume', etc.)
 * - Implementar `loadReportData(filters)` para buscar dados do relatório específico
 * - Implementar `onDataLoaded(data)` para processar os dados e construir gráficos quando necessário
 *
 * @template T Tipo do payload de dados do relatório
 * @template TChartSeries Tipo do payload de séries do gráfico
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
   * Inicializa as datas padrão (últimos 30 dias) e carrega os dados iniciais.
   * Deve ser chamado no construtor da subclasse após injetar as dependências.
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

  /** Retorna a data de início padrão (30 dias atrás). */
  private getDefaultStartDate(): string {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
  }

  /** Retorna a data de fim padrão (hoje). */
  private getDefaultEndDate(): string {
    return new Date().toISOString().split('T')[0];
  }

  /** Abre o painel de filtros. */
  openFilters(): void {
    this.isFilterOpen.set(true);
  }

  /** Fecha o painel de filtros. */
  closeFilters(): void {
    this.isFilterOpen.set(false);
  }

  /** Limpa todos os filtros retornando aos valores padrão. */
  clearFilters(): void {
    this.searchControl.setValue('');
    this.granularityControl.setValue('month');
    this.startDateControl.setValue(this.getDefaultStartDate());
    this.endDateControl.setValue(this.getDefaultEndDate());
  }

  /** Aplica os filtros e recarrega os dados. */
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
   * Exporta o relatório no formato especificado.
   * @param payload Objeto com o formato de exportação ('csv' ou 'xlsx')
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

  /** Tenta carregar os dados novamente após falha. */
  retry(): void {
    this.loadData(this.filters());
  }

  /** Carrega os dados do relatório e trata as respostas. */
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

  /** Faz o download de um blob como arquivo. */
  private downloadBlob(blob: Blob, format: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${this.getReportKey()}_${new Date().toISOString().split('T')[0]}.${format}`;
    a.click();
    URL.revokeObjectURL(url);
  }

  /**
   * Retorna a chave do relatório para chamadas à API ('billing', 'chat-volume', etc.).
   * @returns Identificador do relatório
   */
  protected abstract getReportKey(): string;

  /**
   * Busca os dados do relatório no service.
   * @param filters Filtros do relatório
   * @returns Observable da resposta do relatório
   */
  protected abstract loadReportData(filters: ReportFilter): Observable<ReportResponse<T>>;

  /**
   * Hook opcional para processar os dados carregados e construir gráficos.
   * Sobrescrever somente quando o relatório precisar de processamento especial.
   * @param data Dados do relatório carregados
   */
  protected onDataLoaded(data: T): void {
    // Padrão: sem operação. Subclasses sobrescrevem se necessário (ex.: para construir gráficos).
  }
}
