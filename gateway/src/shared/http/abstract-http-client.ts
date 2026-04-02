import { HttpException, Logger } from '@nestjs/common';
import axios, { AxiosError, AxiosInstance, AxiosRequestConfig } from 'axios';
import { maskSecrets } from '../utils/secret-masker';

/**
 * Shared abstraction for outbound HTTP clients.
 */
export abstract class AbstractHttpClient {
  protected abstract readonly logger: Logger;

  /**
   * Creates and configures an Axios instance with request/response interceptors.
   *
   * Request interceptor logs outbound calls with masked headers.
   * Response interceptor normalises errors to plain Error objects so
   * callers never deal with raw Axios types.
   *
   * @param config - Axios configuration passed to `axios.create()`
   * @returns Configured AxiosInstance with logging and error normalisation
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
   * Normalises an unknown error into an HttpException and logs it.
   *
   * Extracts status code and message from Axios errors; for unknown errors
   * returns a 500 with the supplied fallback message. Secrets in error
   * payloads are masked before logging.
   *
   * @param method - Human-readable name of the calling method (used in log context)
   * @param error - The caught error (Axios error or unknown)
   * @param fallbackMessage - Message to return when no structured error data is available
   * @throws HttpException with the extracted (or fallback) status and message
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
   * Extracts a human-readable message from an Axios error response body.
   *
   * Checks `data` as a raw string, then looks for `message` or `error` fields
   * inside a parsed object. Falls back to the Axios error's own message.
   *
   * @param error - Axios error containing the response
   * @returns The extracted message string, or null when nothing useful is found
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
