import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Estratégia responsável por classificar a intenção do usuário a partir do conteúdo fornecido.
 *
 * Retorna a intenção `cancelation` quando o conteúdo menciona a palavra "cancel";
 * caso contrário retorna `general`. Trata-se de um classificador leve baseado em regras.
 */
export class ClassifyIntentToolStrategy implements ToolStrategy {
  readonly name = 'classify_intent';

  /**
   * Executa a classificação de intenção por correspondência de palavras-chave.
   *
   * @param _name   - Não utilizado; presente para satisfazer o contrato `ToolStrategy`
   * @param args    - Deve conter a string `content` a ser classificada
   * @param context - Não utilizado
   * @param runtime - Não utilizado
   * @returns Objeto com flag de sucesso e intenção detectada
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
