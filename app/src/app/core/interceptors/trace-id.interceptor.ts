import type { HttpInterceptorFn } from '@angular/common/http';

/**
 * Gera um UUID v4 aleatório para uso como trace ID.
 * @returns String no formato xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 */
function generateTraceId(): string {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Trace ID fixo por sessão do navegador, usado para correlacionar múltiplas
 * requisições originadas da mesma sessão em ferramentas de observabilidade.
 */
let sessionTraceId: string | null = null;

/** Retorna (ou cria) o trace ID da sessão atual do navegador. */
function getSessionTraceId(): string {
  if (!sessionTraceId) {
    sessionTraceId = generateTraceId();
  }
  return sessionTraceId;
}

/**
 * Adiciona os headers `X-Trace-ID` e `X-Session-ID` a todas as requisições HTTP.
 *
 * `X-Trace-ID` é único por request; `X-Session-ID` é fixo por sessão do navegador.
 * Ambos permitem correlacionar logs distribuídos em ferramentas de observabilidade.
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
