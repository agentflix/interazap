import { toRecord } from '../../../../shared/utils/payload-reader.util';
import type {
  AICompletionMessage,
  AICompletionToolFunction,
} from '../../models/ai-completion.model';
import type { OrchestratorRequestLike } from '../../models/orchestrator.model';

/**
 * Serviço responsável por montar mensagens e prompts iniciais para execuções orquestradas.
 */
export class MessageBuilderService {
  /**
   * Monta a lista de mensagens que será enviada ao provider.
   * @param prompt - Prompt base já resolvido para a execução.
   * @param context - Contexto estruturado disponível para a run.
   * @param request - Entrada parcial da orquestração contendo histórico e prompts adicionais.
   * @param tools - Lista bruta de tools disponíveis para a execução.
   * @returns Histórico final de mensagens normalizado para envio ao modelo.
   */
  buildMessages(
    prompt: string,
    context: Record<string, unknown>,
    request: OrchestratorRequestLike,
    tools: unknown[],
  ): AICompletionMessage[] {
    if (request.messages && request.messages.length > 0) {
      const providedMessages = request.messages.filter(
        (message) =>
          typeof message.content === 'string' &&
          (message.role === 'system' ||
            message.role === 'user' ||
            message.role === 'assistant'),
      );

      const alreadyContainsPrompt = providedMessages.some(
        (message) =>
          message.role === 'system' &&
          message.content.trim() === prompt.trim() &&
          message.content.trim() !== '',
      );

      if (alreadyContainsPrompt) {
        return providedMessages;
      }

      return [
        {
          role: 'system',
          content: prompt,
        },
        ...providedMessages,
      ];
    }

    const conversationHistory = this.readConversationHistory(context);
    const contextWithoutHistory = this.omitConversationHistory(context);
    const historyMessages = this.expandConversationHistory(conversationHistory);
    const lastHistoryMessage = historyMessages[historyMessages.length - 1];
    const shouldSkipInputText =
      typeof request.inputText === 'string' &&
      request.inputText.trim() !== '' &&
      lastHistoryMessage?.role === 'user' &&
      lastHistoryMessage.content.trim() === request.inputText.trim();

    const base: AICompletionMessage[] = [
      {
        role: 'system',
        content: prompt,
      },
      {
        role: 'system',
        content: `context:${JSON.stringify(contextWithoutHistory)}`,
      },
      {
        role: 'system',
        content: (() => {
          const toolNames = this.listToolNames(tools);
          const hasSendMessage = toolNames.includes('send_message');
          const toolInstruction = hasSendMessage
            ? 'You MUST use the send_message tool to respond to the user. NEVER respond with text directly - always use send_message to deliver your response to the customer.'
            : 'Use the available tools as needed to fulfill the user request. When done, respond with a clear text message for the user.';
          return `${toolInstruction}\n\nAvailable tools: ${toolNames.join(', ')}`;
        })(),
      },
      ...historyMessages,
    ];

    if (request.inputText && !shouldSkipInputText) {
      base.push({
        role: 'user',
        content: request.inputText,
      });
    }

    return base;
  }

  /**
   * Combina o prompt base com prompts de camadas superiores sem duplicar conteúdo.
   * @param basePrompt - Prompt base resolvido para a execução.
   * @param request - Entrada contendo prompts complementares do agente.
   * @returns Prompt final composto para a primeira chamada ao modelo.
   */
  composeFirstCallPrompt(
    basePrompt: string,
    request: OrchestratorRequestLike,
  ): string {
    const tierChunks = [
      request.superPrompt,
      request.segmentPrompt,
      request.planPrompt,
    ].filter(
      (chunk): chunk is string =>
        typeof chunk === 'string' &&
        chunk.trim().length > 0 &&
        !this.containsPromptChunk(basePrompt, chunk),
    );

    const composedPrompt = [
      ...tierChunks,
      request.agentSystemPrompt,
      ...(request.agentFilePrompts ?? []),
      basePrompt,
    ]
      .filter(
        (chunk): chunk is string =>
          typeof chunk === 'string' && chunk.trim().length > 0,
      )
      .join('\n\n');

    return composedPrompt.trim() !== '' ? composedPrompt : basePrompt;
  }

  /**
   * Normaliza uma definição de tool para o formato de função aceito pela completion.
   * @param tool - Definição bruta de tool recebida da API interna ou do snapshot.
   * @returns Tool normalizada ou `null` quando o valor informado é inválido.
   */
  toFunctionTool(tool: unknown): AICompletionToolFunction | null {
    if (!tool || typeof tool !== 'object' || Array.isArray(tool)) {
      return null;
    }

    const record = tool as Record<string, unknown>;
    const directName = this.readOptionalString(record['name']);

    if (directName && directName.trim() !== '') {
      return {
        name: directName,
        description: this.readOptionalString(record['description']) ?? '',
        parameters: toRecord(record['parameters']),
      };
    }

    const type = this.readOptionalString(record['type']);
    const functionRecord = toRecord(record['function']);
    const nestedName = this.readOptionalString(functionRecord['name']);

    if (type !== 'function' || !nestedName || nestedName.trim() === '') {
      return null;
    }

    return {
      name: nestedName,
      description: this.readOptionalString(functionRecord['description']) ?? '',
      parameters: toRecord(functionRecord['parameters']),
    };
  }

  /**
   * Lista os nomes das tools válidas disponíveis para a execução.
   * @param tools - Coleção bruta de tools a ser inspecionada.
   * @returns Lista somente com os nomes das tools válidas.
   */
  listToolNames(tools: unknown[]): string[] {
    return tools
      .map((tool) => this.toFunctionTool(tool))
      .filter((tool): tool is AICompletionToolFunction => tool !== null)
      .map((tool) => tool.name);
  }

  /**
   * Verifica se um trecho de prompt já está contido no prompt base para evitar duplicados.
   * @param basePrompt - Prompt base contra o qual o trecho será comparado.
   * @param chunk - Trecho a ser buscado dentro do prompt base.
   * @returns `true` quando o trecho já existe no prompt base.
   */
  private containsPromptChunk(basePrompt: string, chunk: string): boolean {
    const normalizedBasePrompt = basePrompt.trim();
    const normalizedChunk = chunk.trim();

    if (normalizedBasePrompt === '' || normalizedChunk === '') {
      return false;
    }

    return normalizedBasePrompt.includes(normalizedChunk);
  }

  /**
   * Converte um valor desconhecido em string opcional para leitura segura de campos.
   * @param value - Valor de entrada de tipo desconhecido.
   * @returns String quando o valor é uma string válida, `undefined` caso contrário.
   */
  private readOptionalString(value: unknown): string | undefined {
    if (typeof value === 'string') {
      return value;
    }

    return undefined;
  }

  /**
   * Extrai o histórico de conversa serializado do contexto estruturado.
   * @param context - Contexto operacional da run com possível `conversation_history`
   * @returns Lista de strings representando as linhas do histórico
   */
  private readConversationHistory(context: Record<string, unknown>): string[] {
    const history = context['conversation_history'];
    if (!Array.isArray(history)) {
      return [];
    }

    return history.filter((line): line is string => typeof line === 'string');
  }

  /**
   * Remove o campo `conversation_history` do contexto para evitar duplicidade nas mensagens.
   * @param context - Contexto estruturado da run
   * @returns Contexto sem o campo `conversation_history`
   */
  private omitConversationHistory(
    context: Record<string, unknown>,
  ): Record<string, unknown> {
    const { conversation_history: _conversationHistory, ...rest } = context;
    return rest;
  }

  /**
   * Converte as linhas do histórico de conversa em mensagens tipadas para o modelo.
   * @param history - Linhas do histórico no formato "User: ..." ou "Agent: ..."
   * @returns Lista de mensagens com papel `user` ou `assistant`
   */
  private expandConversationHistory(history: string[]): AICompletionMessage[] {
    return history.reduce<AICompletionMessage[]>((messages, line) => {
      if (line.startsWith('User: ')) {
        messages.push({ role: 'user', content: line.slice(6) });
      } else if (line.startsWith('Agent: ')) {
        messages.push({ role: 'assistant', content: line.slice(7) });
      }

      return messages;
    }, []);
  }
}
