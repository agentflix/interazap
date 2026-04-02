import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Catch-all strategy for any tool not explicitly registered.
 *
 * @remarks
 * Matches on name '*' and delegates to the runtime's RPC executor with HTTP fallback,
 * allowing arbitrary tools to be called without a dedicated strategy.
 */
export class RpcFallbackToolStrategy implements ToolStrategy {
  readonly name = '*';

  /**
   * @param name     - Tool name to invoke
   * @param args     - Tool arguments
   * @param context  - Execution context
   * @param runtime  - Runtime with RPC + HTTP fallback support
   * @returns Result of the tool invocation
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
