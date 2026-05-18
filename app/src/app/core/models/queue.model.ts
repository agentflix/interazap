export interface QueueMetrics {
  name: string;
  waiting: number;
  active: number;
  completed: number;
  failed: number;
  delayed: number;
  paused: boolean;
  latency?: number;
  throughput?: number;
}

export interface QueueOverview {
  queues: QueueMetrics[];
  totalJobs: number;
  totalFailed: number;
  totalCompleted: number;
  uptime: number;
  redis: {
    connected: boolean;
    memory: string;
  };
}

export interface DeadLetterJob {
  id: string;
  name: string;
  queue: string;
  data: Record<string, unknown>;
  failedReason: string;
  stacktrace: string[];
  attemptsMade: number;
  maxAttempts: number;
  failedAt: string;
  tenant_id?: string;
  processedOn?: string;
  finishedOn?: string;
}

export interface DeadLetterQueueResponse {
  jobs: DeadLetterJob[];
  total: number;
  page: number;
  per_page: number;
  totalPages: number;
}

export interface DeadLetterFilters {
  queue?: string;
  jobName?: string;
  tenant_id?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
}

export type CircuitState = 'CLOSED' | 'OPEN' | 'HALF_OPEN';

export interface CircuitBreakerStatus {
  name: string;
  state: CircuitState;
  failures: number;
  successes: number;
  lastFailure?: string;
  lastSuccess?: string;
  nextRetryAt?: string;
  openedAt?: string;
  halfOpenAt?: string;
  history: CircuitStateChange[];
}

export interface CircuitStateChange {
  from: CircuitState;
  to: CircuitState;
  timestamp: string;
  reason?: string;
}

export interface CircuitBreakerOverview {
  circuits: CircuitBreakerStatus[];
  totalOpen: number;
  totalHalfOpen: number;
  totalClosed: number;
}

export interface QueueActionResult {
  success: boolean;
  message: string;
  affected?: number;
}
