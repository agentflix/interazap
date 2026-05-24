/** Resposta genérica bem-sucedida do cliente HTTP interno. */
export interface InternalApiResponse<T> {
  data: T;
  statusCode: number;
}

/** Erro estruturado retornado pelo cliente HTTP interno. */
export interface InternalApiError {
  message: string;
  statusCode: number;
  operation: string;
  attempt: number;
}
