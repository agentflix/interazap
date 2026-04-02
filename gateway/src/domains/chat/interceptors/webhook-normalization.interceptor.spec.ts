import { ExecutionContext, CallHandler } from '@nestjs/common';
import { of } from 'rxjs';
import { WebhookNormalizationInterceptor } from './webhook-normalization.interceptor';

describe('WebhookNormalizationInterceptor', () => {
  let interceptor: WebhookNormalizationInterceptor;
  let mockExecutionContext: ExecutionContext;
  let mockCallHandler: CallHandler;
  let mockRequest: { body: unknown };

  beforeEach(() => {
    interceptor = new WebhookNormalizationInterceptor();

    mockRequest = {
      body: {},
    };

    mockExecutionContext = {
      switchToHttp: () => ({
        getRequest: () => mockRequest,
      }),
    } as unknown as ExecutionContext;

    mockCallHandler = {
      handle: jest.fn(() => of({})),
    };
  });

  const bodyAsRecord = (): Record<string, unknown> => {
    return (mockRequest.body ?? {}) as Record<string, unknown>;
  };

  it('should add raw property to body if missing', () => {
    mockRequest.body = { data: 'test' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().raw).toEqual({ data: 'test' });
  });

  it('should not override existing raw property', () => {
    mockRequest.body = { data: 'test', raw: { existing: 'raw' } };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().raw).toEqual({ existing: 'raw' });
  });

  it('should normalize EventType to event_type', () => {
    mockRequest.body = { EventType: 'message.new' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().event_type).toBe('message.new');
  });

  it('should normalize eventType to event_type', () => {
    mockRequest.body = { eventType: 'message.sent' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().event_type).toBe('message.sent');
  });

  it('should normalize event to event_type', () => {
    mockRequest.body = { event: 'connection.status' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().event_type).toBe('connection.status');
  });

  it('should normalize type to event_type', () => {
    mockRequest.body = { type: 'webhook.received' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().event_type).toBe('webhook.received');
  });

  it('should not override existing event_type', () => {
    mockRequest.body = { event_type: 'existing', EventType: 'should-ignore' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().event_type).toBe('existing');
  });

  it('should prioritize EventType over other candidates', () => {
    mockRequest.body = {
      EventType: 'priority',
      eventType: 'lower',
      event: 'lowest',
    };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(bodyAsRecord().event_type).toBe('priority');
  });

  it('should handle non-object body gracefully', () => {
    mockRequest.body = 'string body';

    expect(() => {
      interceptor.intercept(mockExecutionContext, mockCallHandler);
    }).not.toThrow();
  });

  it('should handle null body gracefully', () => {
    mockRequest.body = null;

    expect(() => {
      interceptor.intercept(mockExecutionContext, mockCallHandler);
    }).not.toThrow();
  });

  it('should call next.handle()', () => {
    mockRequest.body = { data: 'test' };

    interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(mockCallHandler.handle).toHaveBeenCalled();
  });

  it('should return observable from next.handle()', () => {
    mockRequest.body = { data: 'test' };

    const result = interceptor.intercept(mockExecutionContext, mockCallHandler);

    expect(result).toBeDefined();
  });
});
