import { ExecutionContext, CallHandler } from '@nestjs/common';
import { of, throwError } from 'rxjs';
import {
  TraceIdInterceptor,
  TRACE_ID_HEADER,
  getTraceId,
} from './trace-id.interceptor';
import { StructuredLoggerService } from '../logger/structured-logger.service';

describe('TraceIdInterceptor', () => {
  let interceptor: TraceIdInterceptor;

  const mockRequest: Record<string, unknown> = {
    headers: {},
    method: 'GET',
    url: '/test',
  };

  const mockResponse = {
    setHeader: jest.fn(),
    statusCode: 200,
  };

  const mockExecutionContext = {
    switchToHttp: () => ({
      getRequest: () => mockRequest,
      getResponse: () => mockResponse,
    }),
  } as ExecutionContext;

  const mockCallHandler: CallHandler = {
    handle: () => of({ data: 'test' }),
  };

  beforeEach(() => {
    interceptor = new TraceIdInterceptor();
    mockRequest.headers = {};
    mockRequest['traceId'] = undefined;
    jest.clearAllMocks();
  });

  describe('intercept()', () => {
    it('should generate trace id when not provided', (done) => {
      interceptor.intercept(mockExecutionContext, mockCallHandler).subscribe({
        complete: () => {
          expect(mockRequest['traceId']).toBeDefined();
          expect(mockRequest['traceId']).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
          );
          expect(mockResponse.setHeader).toHaveBeenCalledWith(
            TRACE_ID_HEADER,
            expect.any(String),
          );
          done();
        },
      });
    });

    it('should use existing trace id from request header', (done) => {
      const existingTraceId = 'existing-trace-id-123';
      (mockRequest.headers as Record<string, string>)[
        TRACE_ID_HEADER.toLowerCase()
      ] = existingTraceId;

      interceptor.intercept(mockExecutionContext, mockCallHandler).subscribe({
        complete: () => {
          expect(mockRequest['traceId']).toBe(existingTraceId);
          expect(mockResponse.setHeader).toHaveBeenCalledWith(
            TRACE_ID_HEADER,
            existingTraceId,
          );
          done();
        },
      });
    });

    it('should add trace id to response headers', (done) => {
      interceptor.intercept(mockExecutionContext, mockCallHandler).subscribe({
        complete: () => {
          expect(mockResponse.setHeader).toHaveBeenCalledWith(
            TRACE_ID_HEADER,
            expect.any(String),
          );
          done();
        },
      });
    });

    it('should propagate traceId via AsyncLocalStorage during handler execution', (done) => {
      let capturedTraceId: string | undefined;

      const handlerWithCapture: CallHandler = {
        handle: () => {
          capturedTraceId = StructuredLoggerService.getTraceId();
          return of({ data: 'ok' });
        },
      };

      interceptor
        .intercept(mockExecutionContext, handlerWithCapture)
        .subscribe({
          complete: () => {
            expect(capturedTraceId).toBeDefined();
            expect(capturedTraceId).toMatch(
              /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
            );
            done();
          },
        });
    });

    it('should handle errors and still log', (done) => {
      const errorHandler: CallHandler = {
        handle: () => throwError(() => new Error('Test error')),
      };

      interceptor.intercept(mockExecutionContext, errorHandler).subscribe({
        error: () => {
          expect(mockRequest['traceId']).toBeDefined();
          done();
        },
      });
    });
  });

  describe('getTraceId()', () => {
    it('should return trace id from request', () => {
      mockRequest['traceId'] = 'test-trace-id';
      const result = getTraceId(mockRequest as never);
      expect(result).toBe('test-trace-id');
    });

    it('should return undefined when no trace id', () => {
      const result = getTraceId(mockRequest as never);
      expect(result).toBeUndefined();
    });
  });
});
