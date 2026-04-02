/**
 * Modelos de chat do gateway.
 * Define tipos relacionados a mensagens e papéis em conversas de IA.
 */

/**
 * Papel de uma mensagem em uma conversa de chat com IA.
 * - system: instrução do sistema para o modelo
 * - user: mensagem enviada pelo usuário
 * - assistant: resposta gerada pelo modelo
 */
export type ChatMessageRole = 'system' | 'user' | 'assistant';
