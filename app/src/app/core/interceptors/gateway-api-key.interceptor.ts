import { type HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../../environments/environment';

/**
 * Injeta o header `x-api-key` em todas as requisições destinadas ao Gateway NestJS.
 *
 * Atua somente quando a URL da request começa com `environment.gateway.url`.
 * No web, tanto `url` quanto `internalApiKey` devem estar configurados no
 * environment para que o interceptor tenha efeito.
 */
export const gatewayApiKeyInterceptor: HttpInterceptorFn = (req, next) => {
  const { url, internalApiKey } = environment.gateway;
  if (url && internalApiKey && req.url.startsWith(url)) {
    return next(req.clone({ setHeaders: { 'x-api-key': internalApiKey } }));
  }
  return next(req);
};
