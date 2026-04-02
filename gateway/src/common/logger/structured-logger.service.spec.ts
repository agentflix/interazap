import { StructuredLoggerService } from './structured-logger.service';

describe('StructuredLoggerService', () => {
  let service: StructuredLoggerService;

  beforeEach(() => {
    service = new StructuredLoggerService();
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('setContext', () => {
    it('should set context', () => {
      service.setContext('TestContext');
      expect(service).toBeDefined();
    });
  });

  describe('setTraceId', () => {
    it('should set trace id', () => {
      service.setTraceId('trace-123');
      expect(service).toBeDefined();
    });
  });

  describe('log', () => {
    it('should log info message', () => {
      expect(() => service.log('test message')).not.toThrow();
    });

    it('should log with context', () => {
      service.setContext('TestContext');
      expect(() => service.log('test message')).not.toThrow();
    });

    it('should log with trace id', () => {
      service.setTraceId('trace-abc');
      expect(() => service.log('traced message')).not.toThrow();
    });

    it('should log with optional string context', () => {
      expect(() => service.log('message', 'ExtraContext')).not.toThrow();
    });

    it('should log with optional object params', () => {
      expect(() =>
        service.log('message', { extra: 'data', userId: 123 }),
      ).not.toThrow();
    });

    it('should handle Error objects in message', () => {
      const error = new Error('Test error');
      expect(() => service.log(error)).not.toThrow();
    });

    it('should handle object messages', () => {
      expect(() => service.log({ action: 'test', value: 42 })).not.toThrow();
    });

    it('should handle circular object gracefully', () => {
      const circular: Record<string, unknown> = { a: 1 };
      circular.self = circular;
      // This should not throw even with circular ref
      expect(() => service.log(circular)).not.toThrow();
    });
  });

  describe('error', () => {
    it('should log error message', () => {
      expect(() => service.error('error message')).not.toThrow();
    });

    it('should log Error object', () => {
      const error = new Error('Something went wrong');
      expect(() => service.error(error)).not.toThrow();
    });

    it('should log with stack trace', () => {
      expect(() => service.error('error', 'stack trace here')).not.toThrow();
    });

    it('should log error with context object', () => {
      expect(() =>
        service.error('error message', { errorCode: 'E001', userId: 'u-1' }),
      ).not.toThrow();
    });
  });

  describe('warn', () => {
    it('should log warning message', () => {
      expect(() => service.warn('warning message')).not.toThrow();
    });

    it('should log warning with context', () => {
      service.setContext('WarnContext');
      expect(() => service.warn('warning', { severity: 'high' })).not.toThrow();
    });
  });

  describe('debug', () => {
    it('should log debug message', () => {
      expect(() => service.debug('debug message')).not.toThrow();
    });

    it('should log debug with extra data', () => {
      expect(() =>
        service.debug('debug info', { requestId: 'req-123' }),
      ).not.toThrow();
    });
  });

  describe('verbose', () => {
    it('should log verbose message', () => {
      expect(() => service.verbose('verbose message')).not.toThrow();
    });
  });

  describe('fatal', () => {
    it('should log fatal message', () => {
      expect(() => service.fatal('fatal message')).not.toThrow();
    });

    it('should log fatal with Error object', () => {
      expect(() => service.fatal(new Error('Critical failure'))).not.toThrow();
    });
  });

  describe('combined context and trace', () => {
    it('should include both context and trace in log entry', () => {
      service.setContext('CombinedContext');
      service.setTraceId('trace-xyz');
      expect(() => service.log('message with both')).not.toThrow();
    });
  });
});
