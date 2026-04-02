import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { of } from 'rxjs';
import { LucideAngularModule, icons } from 'lucide-angular';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { UsageDashboardComponent } from './usage-dashboard';
import { AiUsageService } from '@ai/services/ai-usage.service';

describe('UsageDashboardComponent', () => {
  let fixture: ComponentFixture<UsageDashboardComponent>;

  const usageServiceMock = {
    getSummary: vi.fn(),
    getDaily: vi.fn(),
    getTopAgents: vi.fn(),
    getBudgetStatus: vi.fn(),
    getVoiceUsage: vi.fn(),
  };

  beforeEach(async () => {
    usageServiceMock.getSummary.mockReturnValue(
      of({
        total_tokens: 12000,
        total_requests: 200,
        avg_latency_ms: 420,
        total_cost: 12.3,
        cost_change_percent: -3.2,
      }),
    );

    usageServiceMock.getDaily.mockReturnValue(
      of([
        { date: '2026-03-01', cost: 3.2, tokens: 3000 },
        { date: '2026-03-02', cost: 4.1, tokens: 4200 },
      ]),
    );

    usageServiceMock.getTopAgents.mockReturnValue(
      of([{ agent_name: 'Support L1', total_cost: 8.1 }]),
    );

    usageServiceMock.getBudgetStatus.mockReturnValue(
      of({
        daily_used: 12000,
        daily_budget: 50000,
        daily_percent: 24,
        daily_remaining: 38000,
        avg_tokens_per_run: 460,
        per_run_budget: 2000,
      }),
    );

    usageServiceMock.getVoiceUsage.mockReturnValue(
      of({
        stt_cost: 1,
        stt_requests: 10,
        stt_minutes: 8.5,
        tts_cost: 0.6,
        tts_requests: 12,
        tts_characters: 5300,
        total_voice_cost: 1.6,
      }),
    );

    await TestBed.configureTestingModule({
      imports: [UsageDashboardComponent],
      providers: [
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: AiUsageService, useValue: usageServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(UsageDashboardComponent);
  });

  it('loads dashboard data and renders KPI values', () => {
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('R$');
    expect(element.textContent).toContain('12.0K');
    expect(element.textContent).toContain('Support L1');
    expect(usageServiceMock.getVoiceUsage).toHaveBeenCalledWith(30);
  });
});
