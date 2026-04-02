/**
 * Modelos do domínio AI do gateway.
 * Centraliza contratos de completion, uso de tokens e respostas HTTP do controller.
 */

/**
 * Papéis suportados nas mensagens trocadas com o modelo.
 */
export type ChatMessageRole = 'system' | 'user' | 'assistant';

/**
 * Representa uma mensagem enviada ao modelo de linguagem.
 */
export interface AICompletionMessage {
  /** Papel exercido pela mensagem no histórico da conversa. */
  role: ChatMessageRole;
  /** Conteúdo textual enviado ao modelo. */
  content: string;
  /** Nome opcional do participante associado à mensagem. */
  name?: string;
}

/**
 * Descreve a função exposta como tool para o modelo.
 */
export interface AICompletionToolFunction {
  /** Nome único da função. */
  name: string;
  /** Descrição textual da capacidade disponibilizada. */
  description: string;
  /** Schema JSON com os parâmetros aceitos pela tool. */
  parameters: Record<string, unknown>;
}

/**
 * Representa uma tool enviada no formato esperado pelo provider.
 */
export interface AICompletionTool {
  /** Tipo da tool disponibilizada ao modelo. */
  type: 'function';
  /** Definição da função exposta para execução. */
  function: AICompletionToolFunction;
}

/**
 * Representa a requisição normalizada de completion usada internamente no gateway.
 */
export interface AICompletionRequest {
  /** Histórico de mensagens usado como contexto da completion. */
  messages: AICompletionMessage[];
  /** Modelo solicitado para a geração. */
  model?: string;
  /** Limite máximo de tokens da resposta. */
  maxTokens?: number;
  /** Temperatura aplicada na geração. */
  temperature?: number;
  /** Indica se a resposta deve ser transmitida em streaming. */
  stream?: boolean;
  /** Valor de top-p usado no nucleus sampling. */
  topP?: number;
  /** Penalidade por frequência de tokens repetidos. */
  frequencyPenalty?: number;
  /** Penalidade por presença de tokens já usados. */
  presencePenalty?: number;
  /** Lista de tools disponíveis durante a geração. */
  tools?: AICompletionTool[];
}

/**
 * Motivos possíveis para o encerramento de uma completion.
 */
export type FinishReason =
  | 'stop'
  | 'length'
  | 'content_filter'
  | 'tool_calls'
  | null;

/**
 * Representa a resposta normalizada retornada por um provider de AI.
 */
export interface AICompletionResponseDto {
  /** Conteúdo textual retornado pelo modelo. */
  content: string;
  /** Quantidade de tokens consumidos no prompt. */
  promptTokens: number;
  /** Quantidade de tokens consumidos na resposta. */
  completionTokens: number;
  /** Soma total de tokens da chamada. */
  totalTokens: number;
  /** Identificador do modelo efetivamente utilizado. */
  model: string;
  /** Motivo informado pelo provider para o encerramento. */
  finishReason: FinishReason;
}

/**
 * Representa o uso agregado de tokens de uma completion.
 */
export interface TokenUsage {
  /** Quantidade de tokens consumidos no prompt. */
  promptTokens: number;
  /** Quantidade de tokens consumidos na resposta. */
  completionTokens: number;
  /** Quantidade total de tokens consumidos. */
  totalTokens: number;
}

/**
 * Representa a resposta HTTP simplificada do endpoint de chat interno.
 */
export interface ChatCompletionResponse {
  /** Indica se a operação foi concluída com sucesso. */
  success: boolean;
  /** Conteúdo textual produzido pela AI. */
  content: string;
  /** Motivo de término retornado pelo provider. */
  finishReason?: FinishReason;
  /** Tokens consumidos no prompt. */
  promptTokens?: number;
  /** Tokens consumidos na completion. */
  completionTokens?: number;
  /** Total de tokens consumidos. */
  totalTokens?: number;
  /** Modelo utilizado para gerar a resposta. */
  model?: string;
}
