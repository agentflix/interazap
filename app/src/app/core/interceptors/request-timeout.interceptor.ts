import { HttpContextToken, HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { TimeoutError, catchError, throwError, timeout } from 'rxjs';

const REQUEST_TIMEOUT_MS = 15000;

/**
 * Allows opt-out for requests that must not be timed out globally.
 */
export const SKIP_REQUEST_TIMEOUT = new HttpContextToken<boolean>(() => false);

/**
 * Enforces a default timeout to prevent pending requests from freezing UI state.
 */
export const requestTimeoutInterceptor: HttpInterceptorFn = (req, next) => {
  const skipTimeout = req.context.get(SKIP_REQUEST_TIMEOUT);
  const isMediaUploadRequest = req.url.includes('/chat/media') && req.method === 'POST';
  const isUploadRequest = req.reportProgress || isMediaUploadRequest;

  if (skipTimeout || isUploadRequest) {
    return next(req);
  }

  return next(req).pipe(
    timeout(REQUEST_TIMEOUT_MS),
    catchError((error: unknown) => {
      if (error instanceof TimeoutError) {
        const timeoutError = new HttpErrorResponse({
          error: { message: 'Tempo limite excedido' },
          status: 408,
          statusText: 'Request Timeout',
          url: req.url,
        });

        return throwError(() => timeoutError);
      }

      return throwError(() => error);
    }),
  );
};
