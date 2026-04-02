import type { HttpInterceptorFn } from '@angular/common/http';

/**
 * Generates a UUID v4 for trace ID.
 */
function generateTraceId(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Session-scoped trace ID for correlating requests within a browser session.
 */
let sessionTraceId: string | null = null;

function getSessionTraceId(): string {
  if (!sessionTraceId) {
    sessionTraceId = generateTraceId();
  }
  return sessionTraceId;
}

/**
 * HTTP Interceptor that adds X-Trace-ID header to all outgoing requests.
 *
 * The trace ID is unique per request but includes a session prefix
 * for correlating multiple requests from the same browser session.
 */
export const traceIdInterceptor: HttpInterceptorFn = (req, next) => {
  const traceId = generateTraceId();
  const sessionId = getSessionTraceId();

  const tracedReq = req.clone({
    setHeaders: {
      'X-Trace-ID': traceId,
      'X-Session-ID': sessionId,
    },
  });

  return next(tracedReq);
};
