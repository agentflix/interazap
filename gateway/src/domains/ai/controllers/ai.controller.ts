import {
  Body,
  Controller,
  HttpException,
  HttpStatus,
  Logger,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { AIProviderFactory } from '../providers/ai-provider.factory';
import { AIChatRequestDto } from '../dto/ai-chat-request.dto';
import { AIEmbeddingsRequestDto } from '../dto/ai-embeddings-request.dto';
import {
  OpenAIProviderAdapter,
  OpenAIProviderError,
} from '../providers/openai/openai-provider.adapter';
import type {
  AICompletionRequest,
  ChatCompletionResponse,
} from '../models/ai-completion.model';
import type { EmbeddingsResponseBody } from '../models/embeddings.model';
/**
 * Controller responsável por expor endpoints internos de chat e embeddings do domínio AI.
 */
@Controller({ version: '1', path: 'ai/openai' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class AIController {
  private readonly logger = new Logger(AIController.name);

  constructor(
    private readonly providerFactory: AIProviderFactory,
    private readonly openaiProvider: OpenAIProviderAdapter,
  ) {}

  /**
   * Executa uma completion síncrona usando o provider padrão configurado.
   * @param body - Payload validado da requisição de chat.
   * @returns Resposta simplificada com conteúdo, tokens e modelo utilizados.
   */
  @Post('chat')
  async chat(@Body() body: AIChatRequestDto): Promise<ChatCompletionResponse> {
    const provider = this.providerFactory.getDefaultProvider();

    const request: AICompletionRequest = {
      messages: body.messages,
      model: body.model,
      temperature: body.temperature,
      maxTokens: body.maxTokens,
    };

    try {
      const result = await provider.complete(request);

      return {
        success: true,
        content: result.content,
        finishReason: result.finishReason,
        promptTokens: result.promptTokens,
        completionTokens: result.completionTokens,
        totalTokens: result.totalTokens,
        model: result.model,
      };
    } catch (error: unknown) {
      const status = this.mapProviderErrorToHttpStatus(error);
      this.logProviderError('Chat completion failed', error, status);
      throw new HttpException(this.getPublicErrorMessage(status), status);
    }
  }

  /**
   * Gera embeddings para uma ou mais entradas textuais via provider OpenAI.
   * @param body - Payload validado da requisição de embeddings.
   * @returns Corpo HTTP normalizado com vetores, modelo e uso de tokens.
   */
  @Post('embeddings')
  async embeddings(
    @Body() body: AIEmbeddingsRequestDto,
  ): Promise<EmbeddingsResponseBody> {
    const input = Array.isArray(body.input) ? body.input : [body.input];

    this.logger.log(`Generating embeddings for ${input.length} item(s)`);

    try {
      const response = await this.openaiProvider.createEmbeddings({
        input,
        model: body.model,
        dimensions: body.dimensions,
      });

      return {
        object: response.object,
        data: response.data.map((item) => ({
          object: item.object,
          embedding: item.embedding,
          index: item.index,
        })),
        model: response.model,
        usage: response.usage
          ? {
              prompt_tokens: response.usage.prompt_tokens,
              total_tokens: response.usage.total_tokens,
            }
          : undefined,
      };
    } catch (error: unknown) {
      const status = this.mapProviderErrorToHttpStatus(error);
      this.logProviderError('Embeddings generation failed', error, status);
      throw new HttpException(this.getPublicErrorMessage(status), status);
    }
  }

  /**
   * Mapeia o status HTTP para uma mensagem de erro pública sem expor detalhes internos.
   * @param status - Código de status HTTP a ser mapeado.
   * @returns Mensagem de erro genérica adequada para resposta ao cliente.
   */
  private getPublicErrorMessage(status: HttpStatus): string {
    switch (status) {
      case HttpStatus.BAD_REQUEST:
        return 'Invalid AI request';
      case HttpStatus.UNAUTHORIZED:
        return 'AI provider authentication failed';
      case HttpStatus.TOO_MANY_REQUESTS:
        return 'AI provider rate limit reached';
      case HttpStatus.BAD_GATEWAY:
        return 'AI provider temporarily unavailable';
      default:
        return 'Failed to process AI request';
    }
  }

  /**
   * Registra o erro do provider no logger com contexto estruturado.
   * @param context - Texto descritivo da operação que falhou.
   * @param error - Erro capturado durante a execução.
   * @param status - Código de status HTTP resolvido para o erro.
   * @returns Não retorna valor.
   */
  private logProviderError(
    context: string,
    error: unknown,
    status: HttpStatus,
  ): void {
    if (error instanceof OpenAIProviderError) {
      this.logger.error(
        `${context}: code=${error.code} status=${status} message=${error.message}`,
      );
      return;
    }

    if (error instanceof Error) {
      this.logger.error(
        `${context}: status=${status} message=${error.message}`,
        error.stack,
      );
      return;
    }

    this.logger.error(`${context}: status=${status} unknown error`);
  }

  /**
   * Converte um erro do provider no código de status HTTP correspondente.
   * @param error - Erro produzido pelo provider de AI.
   * @returns Código de status HTTP mapeado para o tipo de erro recebido.
   */
  private mapProviderErrorToHttpStatus(error: unknown): HttpStatus {
    if (!(error instanceof OpenAIProviderError)) {
      return HttpStatus.INTERNAL_SERVER_ERROR;
    }

    switch (error.code) {
      case 'PROVIDER_AUTH_ERROR':
        return HttpStatus.UNAUTHORIZED;
      case 'PROVIDER_RATE_LIMIT':
        return HttpStatus.TOO_MANY_REQUESTS;
      case 'PROVIDER_TIMEOUT':
      case 'CIRCUIT_BREAKER_OPEN':
      case 'PROVIDER_SERVER_ERROR':
        return HttpStatus.BAD_GATEWAY;
      case 'INVALID_REQUEST':
      case 'PROVIDER_CONTENT_FILTER':
        return HttpStatus.BAD_REQUEST;
      default:
        return HttpStatus.INTERNAL_SERVER_ERROR;
    }
  }
}
