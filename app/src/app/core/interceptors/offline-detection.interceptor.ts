import {
  HttpErrorResponse,
  HttpEventType,
  type HttpInterceptorFn,
  type HttpResponse,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, tap, throwError } from 'rxjs';
import { NetworkStatusService } from '../services/network-status.service';

/**
 * Atualiza o estado de conectividade da aplicação com base no resultado real
 * das requests HTTP.
 */
export const offlineDetectionInterceptor: HttpInterceptorFn = (req, next) => {
  const networkStatus = inject(NetworkStatusService);

  return next(req).pipe(
    tap((event) => {
      if (event.type === HttpEventType.Response) {
        const response = event as HttpResponse<object>;
        if (response.ok) {
          networkStatus.markOnline();
        }
      }
    }),
    catchError((error: HttpErrorResponse) => {
      if (error.status === 0 || error.status === 408) {
        networkStatus.markOffline();
      }
      return throwError(() => error);
    }),
  );
};
