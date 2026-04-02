import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

export type { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

export interface ToolStrategyRuntime {
  normalizeSendMessageArgs(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Record<string, unknown>;
  executeToolRpcWithHttpFallback(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>>;
  executeDelegateToAgent(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>>;
}
