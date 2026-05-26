import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Estratégia responsável por delegar a execução para outro agente via runtime.
 *
 * A lógica real de delegação (rastreamento de profundidade e gerenciamento de pilha)
 * reside em `ToolStrategyRuntime.executeDelegateToAgent`.
 */
export class DelegateToAgentToolStrategy implements ToolStrategy {
  readonly name = 'delegate_to_agent';

  /**
   * Delega a execução ao runtime e retorna o resultado da delegação.
   *
   * @param _name   - Não utilizado; presente para satisfazer o contrato `ToolStrategy`
   * @param args    - Argumentos de delegação (agente alvo, prompt, etc.)
   * @param context - Contexto operacional da run atual
   * @param runtime - Runtime com suporte a delegação entre agentes
   * @returns Resultado da delegação retornado pelo runtime
   */
  async execute(
    _name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    return runtime.executeDelegateToAgent(args, context);
  }
}
