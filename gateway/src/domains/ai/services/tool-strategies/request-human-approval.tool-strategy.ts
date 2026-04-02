import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Signals that a human approval step is required before the run can continue.
 *
 * @remarks
 * This strategy does not perform any actual I/O. The runtime or orchestrator
 * is responsible for surfacing the pending state to the end user and resuming
 * the run once approval is granted.
 */
export class RequestHumanApprovalToolStrategy implements ToolStrategy {
  readonly name = 'request_human_approval';

  /**
   * Returns a pending-approval status without performing any action.
   *
   * @param name     - Unused
   * @param args     - Unused
   * @param context  - Unused
   * @param runtime  - Unused
   * @returns Object with success flag and pending_human_approval status
   */
  execute(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    void name;
    void args;
    void context;
    void runtime;

    return Promise.resolve({
      success: true,
      status: 'pending_human_approval',
    });
  }
}
