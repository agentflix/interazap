import { ToolStrategy } from './tool-strategy.interface';
import { ToolStrategyRuntime } from './tool-strategy.types';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';

/**
 * Estratégia responsável por enviar uma mensagem para um ticket ou conversa via RPC com fallback HTTP.
 *
 * Primeiro normaliza os argumentos brutos da tool usando o runtime, depois tenta uma chamada
 * RPC. Se o RPC falhar, usa um POST HTTP para o serviço de chat como fallback. Retorna uma
 * resposta normalizada de sucesso ou erro independente do transporte utilizado.
 */
export class SendMessageToolStrategy implements ToolStrategy {
  readonly name = 'send_message';

  /**
   * Normaliza os argumentos e executa o envio da mensagem via RPC ou HTTP.
   *
   * @param _name   - Não utilizado; presente para satisfazer o contrato `ToolStrategy`
   * @param args    - Argumentos da tool (ticket_id, content, etc.)
   * @param context - Contexto operacional da run atual
   * @param runtime - Runtime com suporte a RPC e fallback HTTP
   * @returns Resultado normalizado com flag de sucesso e mensagem de erro opcional
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
