import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Estratégia responsável por sinalizar que uma aprovação humana é necessária antes de continuar.
 *
 * Esta estratégia não realiza nenhuma operação de I/O. O runtime ou orquestrador é
 * responsável por expor o estado pendente ao usuário final e retomar a run após aprovação.
 */
export class RequestHumanApprovalToolStrategy implements ToolStrategy {
  readonly name = 'request_human_approval';

  /**
   * Retorna um status de aprovação pendente sem executar nenhuma ação.
   *
   * @param name    - Não utilizado
   * @param args    - Não utilizado
   * @param context - Não utilizado
   * @param runtime - Não utilizado
   * @returns Objeto com flag de sucesso e status `pending_human_approval`
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
