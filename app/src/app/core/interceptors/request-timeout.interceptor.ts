import { HttpContextToken, HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { TimeoutError, catchError, throwError, timeout } from 'rxjs';

const REQUEST_TIMEOUT_MS = 15000;

/**
 * Token de contexto HTTP para pular o timeout global em requisições específicas.
 *
 * @example
 * ```ts
 * this.http.get('/endpoint', { context: new HttpContext().set(SKIP_REQUEST_TIMEOUT, true) });
 * ```
 */
export const SKIP_REQUEST_TIMEOUT = new HttpContextToken<boolean>(() => false);

/**
 * Aplica um timeout global de 15 segundos em todas as requisições HTTP
 * para evitar que requests pendentes congelem o estado da UI.
 *
 * Requisições de upload (com `reportProgress`) e uploads de mídia do chat
 * são automaticamente isentas do timeout. Use `SKIP_REQUEST_TIMEOUT` para
 * isentar outras requisições de longa duração.
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
