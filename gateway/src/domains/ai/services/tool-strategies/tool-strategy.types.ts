import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

export type { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Runtime injetado nas estratégias de tool para acesso a operações de I/O do gateway.
 *
 * Permite que as estratégias deleguem operações cross-cutting (RPC, HTTP fallback,
 * normalização de argumentos e delegação de agentes) sem acoplar-se ao `ToolExecutorService`.
 */
export interface ToolStrategyRuntime {
  /**
   * Normaliza os argumentos da tool `send_message` preenchendo campos obrigatórios ausentes.
   * @param args    - Argumentos originais da tool
   * @param context - Contexto operacional da run atual
   * @returns Argumentos normalizados com `content` e `ticket_id` resolvidos
   */
  normalizeSendMessageArgs(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Record<string, unknown>;

  /**
   * Tenta executar a tool via Redis RPC com fallback para HTTP quando o RPC falhar.
   * @param name    - Nome da tool a ser executada
   * @param args    - Argumentos normalizados da tool
   * @param context - Contexto operacional da run atual
   * @returns Resultado da execução via RPC ou HTTP
   */
  executeToolRpcWithHttpFallback(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>>;

  /**
   * Executa a delegação de execução para outro agente com verificações de profundidade e ciclo.
   * @param args    - Argumentos da tool contendo `target_agent_id` e opções de delegação
   * @param context - Contexto operacional da run atual
   * @returns Resultado da delegação ou payload de erro estruturado
   */
  executeDelegateToAgent(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>>;
}
