import { StructuredLoggerService } from './structured-logger.service';

describe('StructuredLoggerService', () => {
  let service: StructuredLoggerService;
  let stdoutSpy: jest.SpyInstance;

  beforeEach(() => {
    service = new StructuredLoggerService();
    stdoutSpy = jest
      .spyOn(process.stdout, 'write')
      .mockImplementation(() => true);
  });

  afterEach(() => {
    stdoutSpy.mockRestore();
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

  describe('log', () => {
    it('should log info message as JSON to stdout', () => {
      service.log('test message');
      expect(stdoutSpy).toHaveBeenCalled();
      const output = stdoutSpy.mock.calls[0][0] as string;
      const parsed = JSON.parse(output);
      expect(parsed.level).toBe('info');
      expect(parsed.message).toBe('test message');
      expect(parsed.service).toBe('telegram-gateway');
      expect(parsed.timestamp).toBeDefined();
      expect(parsed.traceId).toBe('no-trace');
      expect(parsed.spanId).toBe('no-span');
    });

    it('should log with context', () => {
      service.setContext('TestContext');
      service.log('test message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.context).toBe('TestContext');
    });

    it('should log with optional string context', () => {
      service.log('message', 'ExtraContext');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.context).toBe('ExtraContext');
    });

    it('should log with optional object params', () => {
      service.log('message', { extra: 'data', userId: 123 });
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.extra).toBe('data');
      expect(parsed.userId).toBe(123);
    });

    it('should handle Error objects in message', () => {
      const error = new Error('Test error');
      service.log(error);
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.message).toBe('Test error');
    });

    it('should handle object messages', () => {
      service.log({ action: 'test', value: 42 });
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.message).toContain('test');
    });

    it('should handle circular object gracefully', () => {
      const circular: Record<string, unknown> = { a: 1 };
      circular.self = circular;
      expect(() => service.log(circular)).not.toThrow();
    });
  });

  describe('error', () => {
    it('should log error message', () => {
      service.error('error message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.level).toBe('error');
      expect(parsed.message).toBe('error message');
    });

    it('should log Error object with stack', () => {
      const error = new Error('Something went wrong');
      service.error(error);
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.message).toBe('Something went wrong');
      expect(parsed.stack).toBeDefined();
    });

    it('should log with stack trace string', () => {
      service.error('error', 'stack trace here');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.stack).toBe('stack trace here');
    });

    it('should log error with context object', () => {
      service.error('error message', { errorCode: 'E001', userId: 'u-1' });
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.errorCode).toBe('E001');
    });
  });

  describe('warn', () => {
    it('should log warning message', () => {
      service.warn('warning message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.level).toBe('warn');
    });

    it('should log warning with context', () => {
      service.setContext('WarnContext');
      service.warn('warning', { severity: 'high' });
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.severity).toBe('high');
    });
  });

  describe('debug', () => {
    it('should log debug message', () => {
      service.debug('debug message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.level).toBe('debug');
    });

    it('should log debug with extra data', () => {
      service.debug('debug info', { requestId: 'req-123' });
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.requestId).toBe('req-123');
    });
  });

  describe('verbose', () => {
    it('should log verbose message', () => {
      service.verbose('verbose message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.level).toBe('verbose');
    });
  });

  describe('fatal', () => {
    it('should log fatal message', () => {
      service.fatal('fatal message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.level).toBe('fatal');
    });

    it('should log fatal with Error object', () => {
      const error = new Error('Critical failure');
      service.fatal(error);
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.message).toBe('Critical failure');
    });
  });

  describe('AsyncLocalStorage trace context', () => {
    it('should include traceId and spanId when running inside runWithTrace', () => {
      StructuredLoggerService.runWithTrace('trace-abc', 'span-12', () => {
        service.log('traced message');
      });
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.traceId).toBe('trace-abc');
      expect(parsed.spanId).toBe('span-12');
    });

    it('should return no-trace / no-span when outside trace context', () => {
      service.log('untraced message');
      const parsed = JSON.parse(stdoutSpy.mock.calls[0][0] as string);
      expect(parsed.traceId).toBe('no-trace');
      expect(parsed.spanId).toBe('no-span');
    });

    it('getTraceId should return the current trace id', () => {
      StructuredLoggerService.runWithTrace('tid-1', 'sid-1', () => {
        expect(StructuredLoggerService.getTraceId()).toBe('tid-1');
      });
    });

    it('getSpanId should return the current span id', () => {
      StructuredLoggerService.runWithTrace('tid-2', 'sid-2', () => {
        expect(StructuredLoggerService.getSpanId()).toBe('sid-2');
      });
    });

    it('getTraceId should return undefined outside context', () => {
      expect(StructuredLoggerService.getTraceId()).toBeUndefined();
    });
  });

  describe('maskSensitiveData', () => {
    it('should mask Telegram bot tokens', () => {
      const input = 'Token: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz12345678';
      const result = StructuredLoggerService.maskSensitiveData(input);
      expect(result).toContain('123456789:***');
      expect(result).not.toContain('ABCdef');
    });

    it('should mask API keys', () => {
      const input = 'api_key=super_secret_key_12345678';
      const result = StructuredLoggerService.maskSensitiveData(input);
      expect(result).toContain('***');
      expect(result).not.toContain('super_secret');
    });

    it('should mask Bearer tokens', () => {
      const input =
        'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
      const result = StructuredLoggerService.maskSensitiveData(input);
      expect(result).toContain('Bearer ***');
      expect(result).not.toContain('eyJhbGci');
    });

    it('should not mask non-sensitive data', () => {
      const input = 'User logged in successfully';
      const result = StructuredLoggerService.maskSensitiveData(input);
      expect(result).toBe(input);
    });
  });
});
