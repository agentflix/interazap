import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Produces a brief summary of the conversation content.
 *
 * @remarks
 * Currently performs a simple truncation to 500 characters rather than
 * invoking an AI model. A future version may call a dedicated summarization endpoint.
 */
export class SummarizeConversationToolStrategy implements ToolStrategy {
  readonly name = 'summarize_conversation';

  /**
   * @param _name     - Unused; present to satisfy ToolStrategy contract
   * @param args      - Must contain 'content' string to summarize
   * @param context   - Unused
   * @param runtime   - Unused
   * @returns Object with success flag and truncated summary
   */
  execute(
    _name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    void context;
    void runtime;
    const content = typeof args['content'] === 'string' ? args['content'] : '';
    return Promise.resolve({
      success: true,
      summary: content.slice(0, 500),
    });
  }
}
