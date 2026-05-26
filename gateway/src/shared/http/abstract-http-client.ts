import { HttpException, Logger } from '@nestjs/common';
import axios, { AxiosError, AxiosInstance, AxiosRequestConfig } from 'axios';
import { maskSecrets } from '../utils/secret-masker';

/**
 * Abstração base para clientes HTTP de saída do gateway.
 *
 * Contexto: herdada por clientes HTTP concretos (ex.: UazapiHttpClient, OpenAIHttpClient)
 * para padronizar logging com mascaramento de secrets, tratamento de erros Axios
 * e normalização de exceções para HttpException do NestJS.
 */
export abstract class AbstractHttpClient {
  protected abstract readonly logger: Logger;

  /**
   * Cria e configura uma instância Axios com interceptors de requisição e resposta.
   *
   * O interceptor de requisição registra chamadas de saída com headers mascarados.
   * O interceptor de resposta normaliza erros para objetos Error simples para que
   * os chamadores nunca precisem lidar com tipos brutos do Axios.
   *
   * @param config - Configuração Axios passada para `axios.create()`
   * @returns Instância AxiosInstance configurada com logging e normalização de erros
   */
  protected createAxiosInstance(config: AxiosRequestConfig): AxiosInstance {
    const instance = axios.create(config);

    if (instance.interceptors?.request?.use) {
      instance.interceptors.request.use((request) => {
        this.logger.debug('HTTP request', {
          method: request.method,
          url: request.url,
          headers: maskSecrets(request.headers),
        });

        return request;
      });
    }

    if (instance.interceptors?.response?.use) {
      instance.interceptors.response.use(
        (response) => response,
        (error: unknown) =>
          Promise.reject(
            error instanceof Error
              ? error
              : new Error('Unexpected HTTP client error'),
          ),
      );
    }

    return instance;
  }

  /**
   * Normaliza um erro desconhecido em HttpException e registra no log.
   *
   * Extrai código de status e mensagem de erros Axios; para erros desconhecidos
   * retorna 500 com a mensagem de fallback fornecida. Secrets nos payloads de erro
   * são mascarados antes do logging.
   *
   * @param method - Nome legível do método chamador (usado no contexto do log)
   * @param error - Erro capturado (erro Axios ou desconhecido)
   * @param fallbackMessage - Mensagem a retornar quando não há dados de erro estruturados
   * @throws HttpException com o status e a mensagem extraídos (ou de fallback)
   */
  protected throwHttpError(
    method: string,
    error: unknown,
    fallbackMessage: string,
  ): never {
    if (axios.isAxiosError(error)) {
      const axiosError = error as AxiosError<unknown>;
      const status = axiosError.response?.status ?? 500;
      const message = this.extractErrorMessage(axiosError) ?? fallbackMessage;

      this.logger.error(`HTTP client error on ${method}`, {
        status,
        message,
        data: maskSecrets(axiosError.response?.data),
      });

      throw new HttpException(message, status);
    }

    if (error instanceof Error) {
      this.logger.error(`Unexpected HTTP error on ${method}: ${error.message}`);
      throw new HttpException(fallbackMessage, 500);
    }

    this.logger.error(`Unknown HTTP error on ${method}`);
    throw new HttpException(fallbackMessage, 500);
  }

  /**
   * Extrai uma mensagem legível do corpo da resposta de um erro Axios.
   *
   * Verifica `data` como string bruta, depois procura pelos campos `message` ou `error`
   * dentro de um objeto parseado. Usa como fallback a própria mensagem do erro Axios.
   *
   * @param error - Erro Axios contendo a resposta
   * @returns String com a mensagem extraída, ou null quando nada útil é encontrado
   */
  private extractErrorMessage(error: AxiosError<unknown>): string | null {
    const payload = error.response?.data;
    if (typeof payload === 'string' && payload.trim() !== '') {
      return payload;
    }

    if (payload && typeof payload === 'object') {
      const record = payload as Record<string, unknown>;
      const message = record['message'];
      if (typeof message === 'string' && message.trim() !== '') {
        return message;
      }

      const errorMessage = record['error'];
      if (typeof errorMessage === 'string' && errorMessage.trim() !== '') {
        return errorMessage;
      }
    }

    return error.message ?? null;
  }
}
