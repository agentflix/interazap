import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Classifies user intent from the provided content string.
 *
 * @remarks
 * Returns 'cancelation' intent when the content mentions 'cancel';
 * otherwise returns 'general'. This is a lightweight rule-based classifier.
 */
export class ClassifyIntentToolStrategy implements ToolStrategy {
  readonly name = 'classify_intent';

  /**
   * Executes intent classification based on keyword matching.
   *
   * @param _name     - Unused; present to satisfy ToolStrategy contract
   * @param args      - Must contain 'content' string
   * @param context   - Unused
   * @param runtime   - Unused
   * @returns Object with success flag and detected intent
   */
  execute(
    _name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
    runtime: ToolStrategyRuntime,
  ): Promise<Record<string, unknown>> {
    void context;
    void runtime;
    const content =
      typeof args['content'] === 'string' ? args['content'].toLowerCase() : '';

    return Promise.resolve({
      success: true,
      intent: content.includes('cancel') ? 'cancelation' : 'general',
    });
  }
}
