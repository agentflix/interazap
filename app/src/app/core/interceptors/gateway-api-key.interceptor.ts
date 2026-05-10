import { type HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export const gatewayApiKeyInterceptor: HttpInterceptorFn = (req, next) => {
  const { url, internalApiKey } = environment.gateway;
  if (url && internalApiKey && req.url.startsWith(url)) {
    return next(req.clone({ setHeaders: { 'x-api-key': internalApiKey } }));
  }
  return next(req);
};
