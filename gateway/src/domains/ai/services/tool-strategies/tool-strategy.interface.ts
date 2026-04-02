import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

export interface ToolStrategy {
  readonly name: string;
  execute(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>>;
}
