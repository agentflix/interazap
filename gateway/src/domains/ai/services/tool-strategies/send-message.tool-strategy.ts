import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Sends a message to a ticket or conversation via RPC with HTTP fallback.
 *
 * @remarks
 * First normalizes the raw tool arguments using the runtime, then attempts
 * an RPC call. If the RPC fails, falls back to an HTTP POST to the chat service.
 * Returns a normalized success/error response regardless of transport.
 */
export class SendMessageToolStrategy implements ToolStrategy {
  readonly name = 'send_message';

  /**
   * @param _name     - Unused; present to satisfy ToolStrategy contract
   * @param args      - Tool arguments (ticket_id, content, etc.)
   * @param context   - Execution context
   * @param runtime   - Runtime with RPC/HTTP fallback support
   * @returns Normalized result with success flag and optional error message
   */
  async execute(
    _name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    const normalizedArgs = runtime.normalizeSendMessageArgs(args, context);
    const remoteResult = await runtime.executeToolRpcWithHttpFallback(
      this.name,
      normalizedArgs,
      context,
    );

    if (remoteResult['success'] === false) {
      return {
        success: false,
        error:
          (typeof remoteResult['error'] === 'string' &&
            remoteResult['error']) ||
          (typeof remoteResult['message'] === 'string' &&
            remoteResult['message']) ||
          'send_message execution failed.',
        data: remoteResult,
      };
    }

    return {
      success: true,
      data: remoteResult,
    };
  }
}
