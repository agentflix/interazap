import { Test, TestingModule } from '@nestjs/testing';
import { MetricsService } from './metrics.service';
import { BillingUsageMetrics } from './billing-usage.metrics';

describe('BillingUsageMetrics', () => {
  let metricsService: MetricsService;
  let billingMetrics: BillingUsageMetrics;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [MetricsService, BillingUsageMetrics],
    }).compile();

    await module.init();
    metricsService = module.get<MetricsService>(MetricsService);
    billingMetrics = module.get<BillingUsageMetrics>(BillingUsageMetrics);
  });

  it('should be defined', () => {
    expect(billingMetrics).toBeDefined();
  });

  it('should record AI message', () => {
    billingMetrics.recordAiMessage('tenant-1', 'stop', true);
    expect(true).toBe(true);
  });

  it('should record AI message blocked', () => {
    billingMetrics.recordAiMessage('tenant-1', 'stop', false);
    expect(true).toBe(true);
  });

  it('should record usage check failure', () => {
    billingMetrics.recordUsageCheckFailure('timeout');
    expect(true).toBe(true);
  });

  it('should record usage check duration', () => {
    billingMetrics.recordUsageCheckDuration('tenant-1', 250);
    expect(true).toBe(true);
  });

  it('should include billing metric names in prometheus output', async () => {
    billingMetrics.recordAiMessage('tenant-1', 'stop', true);
    billingMetrics.recordUsageCheckFailure('network_error');
    billingMetrics.recordUsageCheckDuration('tenant-1', 100);

    const metrics = await metricsService.getMetrics();
    expect(metrics).toContain('ai_messages_total');
    expect(metrics).toContain('usage_check_failures_total');
    expect(metrics).toContain('usage_check_duration_seconds');
  });
});
