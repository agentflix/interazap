import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { importProvidersFrom } from '@angular/core';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of, Subject, throwError } from 'rxjs';
import { DashboardComponent } from './dashboard';
import { DashboardService } from './services/dashboard.service';
import { type DashboardData } from './models/dashboard.model';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  let dashboardServiceMock: { getData: ReturnType<typeof vi.fn> };

  const mockData: DashboardData = {
    summary: {
      total_revenue_won: 1200,
      pipeline_open_value: 450,
      active_tickets_count: 3,
      csat_average: 4.2,
    },
    funnel: [
      {
        step_name: 'Contato',
        step_color: '#2b7fff',
        count: 4,
        total_amount: 2000,
      },
    ],
    revenue: [
      {
        month: 1,
        year: 2026,
        won_amount: 1200,
        open_amount: 300,
      },
    ],
    negotiations: {
      by_status: { open: 2, won: 1, lost: 1 },
      top_loss_reasons: [{ name: 'Preco', count: 2 }],
    },
    tickets: {
      daily_volume: [{ date: '2026-02-01', count: 3 }],
      by_priority: { low: 1, normal: 2, high: 0, urgent: 0 },
      sla_compliance_rate: 92,
      avg_first_response_minutes: 15,
    },
    csat: {
      average_rating: 4.3,
      total_evaluations: 12,
      distribution: { 1: 0, 2: 1, 3: 2, 4: 4, 5: 5 },
    },
    activities: [
      {
        type: 'negotiation',
        title: 'Deal A',
        description: 'Value: 500',
        created_at: '2026-02-01T10:00:00Z',
        icon: 'lucideHandshake',
      },
    ],
  };

  beforeEach(async () => {
    dashboardServiceMock = {
      getData: vi.fn().mockReturnValue(of({ data: mockData })),
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: DashboardService, useValue: dashboardServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
  });

  it('should create the component', () => {
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should show loading state initially', () => {
    const pending = new Subject<{ data: DashboardData }>();
    dashboardServiceMock.getData.mockReturnValue(pending);

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.isLoading()).toBe(true);
  });

  it('should display KPI cards after data loads', () => {
    fixture.detectChanges();

    expect(component.data()).toEqual(mockData);
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Receita Ganha');
    expect(compiled.textContent).toContain('Tickets Ativos');
  });

  it('should handle error state', () => {
    dashboardServiceMock.getData.mockReturnValue(throwError(() => new Error('Network error')));

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.hasError()).toBe(true);
  });

  it('should call DashboardService on init', () => {
    fixture.detectChanges();
    expect(dashboardServiceMock.getData).toHaveBeenCalledWith(undefined, undefined, 30);
  });
});
