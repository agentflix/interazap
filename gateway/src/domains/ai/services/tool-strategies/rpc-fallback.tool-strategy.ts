import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Estratégia padrão utilizada para qualquer tool não registrada explicitamente.
 *
 * Responde ao nome `*` e delega ao executor RPC do runtime com fallback para HTTP,
 * permitindo que tools arbitrárias sejam chamadas sem necessidade de uma estratégia dedicada.
 */
export class RpcFallbackToolStrategy implements ToolStrategy {
  readonly name = '*';

  /**
   * Delega a execução ao runtime via RPC com fallback HTTP.
   *
   * @param name    - Nome da tool a ser invocada
   * @param args    - Argumentos da tool
   * @param context - Contexto operacional da run atual
   * @param runtime - Runtime com suporte a RPC e fallback HTTP
   * @returns Resultado da invocação da tool
   */
  async execute(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    return runtime.executeToolRpcWithHttpFallback(name, args, context);
  }
}
