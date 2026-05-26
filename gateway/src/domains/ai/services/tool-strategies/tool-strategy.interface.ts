import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Contrato que toda estratégia de execução de tool deve implementar.
 *
 * Cada implementação encapsula a lógica específica de uma tool (ex.: `send_message`,
 * `delegate_to_agent`) e é resolvida pelo `ToolStrategyRegistry` durante a execução.
 */
export interface ToolStrategy {
  /**
   * Nome único da tool gerenciada por esta estratégia.
   * Usado como chave de lookup no `ToolStrategyRegistry`.
   */
  readonly name: string;

  /**
   * Executa a lógica da tool e retorna o resultado serializável.
   *
   * @param name    - Nome da tool sendo executada
   * @param args    - Argumentos normalizados enviados para a tool
   * @param context - Contexto operacional da run atual
   * @param runtime - Runtime com acesso a RPC, HTTP e delegação
   * @returns Resultado da execução com ao menos `success` booleano
   */
  execute(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>>;
}
