import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { MediaTranscriptionReportComponent } from './media-transcription-report';
import { ReportsService } from '@core/services/reports.service';
import { type MediaTranscriptionReport } from '../../../../shared/models/report.model';
import { type AfReportExportPayload } from '@shared/components';

function buildReportResponse(): {
  data: MediaTranscriptionReport;
  meta: { start_date: string; end_date: string; total: number; generated_at: string };
} {
  return {
    data: {
      summary: {
        total_transcriptions: 150,
        total_tokens: 45000,
        total_cost: 12.5,
        avg_latency_ms: 3200,
      },
      by_type: [
        { media_type: 'audio', count: 80, tokens: 20000, cost: 5.0 },
        { media_type: 'image', count: 50, tokens: 15000, cost: 4.5 },
        { media_type: 'video', count: 20, tokens: 10000, cost: 3.0 },
      ],
      by_model: [
        { model_name: 'whisper-1', count: 100, tokens: 30000, cost: 8.0 },
        { model_name: 'gpt-4o', count: 50, tokens: 15000, cost: 4.5 },
      ],
      daily_cost: [
        { date: '2026-02-01', count: 10, cost: 1.2 },
        { date: '2026-02-02', count: 15, cost: 1.8 },
      ],
    },
    meta: {
      start_date: '2026-02-01',
      end_date: '2026-02-28',
      total: 150,
      generated_at: '2026-02-28T12:00:00Z',
    },
  };
}

function applyFilters(
  component: MediaTranscriptionReportComponent,
  startDate: string,
  endDate: string,
): void {
  component.startDateControl.setValue(startDate);
  component.endDateControl.setValue(endDate);
  component.applyFilters();
}

function buildServiceMock(): {
  getMediaTranscription: ReturnType<typeof vi.fn>;
  exportReport: ReturnType<typeof vi.fn>;
} {
  return {
    getMediaTranscription: vi.fn().mockReturnValue(of(buildReportResponse())),
    exportReport: vi.fn().mockReturnValue(of(new Blob())),
  };
}

describe('MediaTranscriptionReportComponent', () => {
  let fixture: ComponentFixture<MediaTranscriptionReportComponent>;
  let component: MediaTranscriptionReportComponent;
  let serviceMock: ReturnType<typeof buildServiceMock>;

  beforeEach(async () => {
    serviceMock = buildServiceMock();

    await TestBed.configureTestingModule({
      imports: [MediaTranscriptionReportComponent],
      providers: [{ provide: ReportsService, useValue: serviceMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(MediaTranscriptionReportComponent);
    component = fixture.componentInstance;
  });

  it('carrega dados automaticamente com datas padrão', () => {
    // Constructor auto-loads data with default dates (last 30 days)
    expect(serviceMock.getMediaTranscription).toHaveBeenCalled();
    expect(component.data()).not.toBeNull();
    expect(component.isLoading()).toBe(false);
  });

  it('carrega dados ao aplicar filtros', () => {
    applyFilters(component, '2026-02-01', '2026-02-28');

    expect(serviceMock.getMediaTranscription).toHaveBeenCalledWith(
      expect.objectContaining({ start_date: '2026-02-01', end_date: '2026-02-28' }),
    );
    expect(component.isLoading()).toBe(false);
    expect(component.data()).not.toBeNull();
    expect(component.data()!.summary.total_transcriptions).toBe(150);
  });

  it('monta gráficos por tipo de mídia', () => {
    applyFilters(component, '2026-02-01', '2026-02-28');

    expect(component.byTypeSeries().length).toBeGreaterThan(0);
    expect(component.byTypeCategories().length).toBe(3);
  });

  it('monta gráficos de custo diário', () => {
    applyFilters(component, '2026-02-01', '2026-02-28');

    expect(component.dailySeries().length).toBeGreaterThan(0);
    expect(component.dailyCategories().length).toBe(2);
  });

  it('entra em estado de erro quando a API falha', () => {
    serviceMock.getMediaTranscription.mockReturnValueOnce(throwError(() => new Error('API error')));

    applyFilters(component, '2026-02-01', '2026-02-28');

    expect(component.hasError()).toBe(true);
    expect(component.isLoading()).toBe(false);
  });

  it('retry recarrega os dados com os mesmos filtros', () => {
    applyFilters(component, '2026-02-01', '2026-02-28');

    serviceMock.getMediaTranscription.mockClear();
    component.retry();

    expect(serviceMock.getMediaTranscription).toHaveBeenCalled();
  });

  it('formata moeda corretamente', () => {
    const formatted = component.formatCurrency(12.5);
    expect(formatted).toContain('12');
  });

  it('exporta relatório quando solicitado', () => {
    applyFilters(component, '2026-02-01', '2026-02-28');

    const exportPayload: AfReportExportPayload = { format: 'csv' };
    component.onExport(exportPayload);

    expect(serviceMock.exportReport).toHaveBeenCalledWith(
      'media-transcription',
      'csv',
      expect.objectContaining({
        start_date: '2026-02-01',
        end_date: '2026-02-28',
      }),
    );
    expect(component.isExporting()).toBe(false);
  });
});
