import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Delegates execution to another agent by forwarding the call to the runtime.
 *
 * @remarks
 * The actual delegation logic (depth tracking, stack management) lives in
 * ToolStrategyRuntime.executeDelegateToAgent.
 */
export class DelegateToAgentToolStrategy implements ToolStrategy {
  readonly name = 'delegate_to_agent';

  /**
   * @param _name     - Unused; present to satisfy ToolStrategy contract
   * @param args      - Delegation arguments (target agent, prompt, etc.)
   * @param context   - Execution context
   * @param runtime   - Runtime with delegation support
   * @returns Delegation result from the runtime
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
