import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Estratégia responsável por produzir um resumo conciso do conteúdo da conversa.
 *
 * Atualmente realiza uma simples truncagem para 500 caracteres em vez de invocar
 * um modelo de AI. Uma versão futura poderá chamar um endpoint dedicado de sumarização.
 */
export class SummarizeConversationToolStrategy implements ToolStrategy {
  readonly name = 'summarize_conversation';

  /**
   * Trunca o conteúdo da conversa em até 500 caracteres como resumo.
   *
   * @param _name   - Não utilizado; presente para satisfazer o contrato `ToolStrategy`
   * @param args    - Deve conter a string `content` a ser resumida
   * @param context - Não utilizado
   * @param runtime - Não utilizado
   * @returns Objeto com flag de sucesso e resumo truncado
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
