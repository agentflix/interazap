import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { Component } from '@angular/core';
import { AfReportLoadingComponent, type ReportLoadingLayout } from './report-loading';

/** Wrapper to test input signals via template binding */
@Component({
  standalone: true,
  imports: [AfReportLoadingComponent],
  template: `
    <af-report-loading [kpiCount]="kpiCount" [layout]="layout" [tableRows]="tableRows" />
  `,
})
class TestHostComponent {
  kpiCount = 4;
  layout: ReportLoadingLayout = 'kpi+chart';
  tableRows = 5;
}

describe('AfReportLoadingComponent', () => {
  let host: TestHostComponent;
  let fixture: ComponentFixture<TestHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfReportLoadingComponent, TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('deve criar o componente', () => {
    expect(host).toBeTruthy();
  });

  it('deve renderizar skeletons de KPI + chart para layout kpi+chart', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');
    // kpi+chart: 4 KPI cards + 1 chart card = 5 cards
    expect(cards.length).toBe(5);
  });

  it('deve renderizar skeletons de KPI + table para layout kpi+table', () => {
    host.layout = 'kpi+table';
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');
    // kpi+table: 4 KPI cards + 1 table card = 5 cards
    expect(cards.length).toBe(5);
  });

  it('deve renderizar apenas skeleton de tabela para layout table', () => {
    host.layout = 'table';
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');
    // table only: 1 table card
    expect(cards.length).toBe(1);
  });

  it('deve renderizar apenas skeleton de chart para layout chart', () => {
    host.layout = 'chart';
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');
    // chart only: 1 chart card
    expect(cards.length).toBe(1);
  });

  it('deve respeitar kpiCount customizado', () => {
    host.kpiCount = 6;
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');
    // 6 KPI cards + 1 chart card = 7 cards
    expect(cards.length).toBe(7);
  });

  it('deve respeitar tableRows customizado', () => {
    host.layout = 'table';
    host.tableRows = 3;
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const skeletons = compiled.querySelectorAll('af-skeleton');
    // 3 row skeletons
    expect(skeletons.length).toBe(3);
  });

  it('deve renderizar skeletons dentro de af-card para KPIs', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const cards = compiled.querySelectorAll('af-card');
    // Each KPI card should contain a skeleton
    cards.forEach((card) => {
      const skeleton = card.querySelector('af-skeleton');
      expect(skeleton).toBeTruthy();
    });
  });

  it('deve renderizar skeletons de texto para table layout', () => {
    host.layout = 'table';
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const skeletons = compiled.querySelectorAll('af-skeleton');
    expect(skeletons.length).toBeGreaterThan(0);
  });
});
