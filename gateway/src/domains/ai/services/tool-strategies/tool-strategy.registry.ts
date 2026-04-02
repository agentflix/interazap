import { Injectable } from '@nestjs/common';
import type { ToolStrategy } from './tool-strategy.interface';
import { ClassifyIntentToolStrategy } from './classify-intent.tool-strategy';
import { DelegateToAgentToolStrategy } from './delegate-to-agent.tool-strategy';
import { RequestHumanApprovalToolStrategy } from './request-human-approval.tool-strategy';
import { RpcFallbackToolStrategy } from './rpc-fallback.tool-strategy';
import { SendMessageToolStrategy } from './send-message.tool-strategy';
import { SummarizeConversationToolStrategy } from './summarize-conversation.tool-strategy';

/**
 * Registro responsável por mapear nomes de tools para suas estratégias de execução.
 */
@Injectable()
export class ToolStrategyRegistry {
  private readonly byName = new Map<string, ToolStrategy>();

  /**
   * Cria o registro padrão com as estratégias nativas do domínio AI.
   * @returns Instância configurada com fallback padrão e estratégias conhecidas.
   */
  static createDefault(): ToolStrategyRegistry {
    return new ToolStrategyRegistry(new RpcFallbackToolStrategy(), [
      new SendMessageToolStrategy(),
      new DelegateToAgentToolStrategy(),
      new ClassifyIntentToolStrategy(),
      new SummarizeConversationToolStrategy(),
      new RequestHumanApprovalToolStrategy(),
    ]);
  }

  constructor(
    private readonly defaultStrategy: ToolStrategy,
    strategies: ToolStrategy[] = [],
  ) {
    for (const strategy of strategies) {
      this.register(strategy);
    }
  }

  /**
   * Registra uma estratégia para um nome de tool específico.
   * @param strategy - Estratégia a ser vinculada ao nome da tool.
   * @returns Não retorna valor.
   */
  register(strategy: ToolStrategy): void {
    const strategyName = strategy.name.trim();
    if (strategyName === '' || strategyName === '*') {
      throw new Error('Invalid tool strategy name.');
    }

    if (this.byName.has(strategyName)) {
      throw new Error(`Duplicate tool strategy registration: ${strategyName}`);
    }

    this.byName.set(strategyName, strategy);
  }

  /**
   * Resolve a estratégia apropriada para uma tool, usando fallback quando necessário.
   * @param name - Nome da tool a ser resolvida.
   * @returns Estratégia registrada para a tool ou a estratégia padrão.
   */
  resolve(name: string): ToolStrategy {
    return this.byName.get(name) ?? this.defaultStrategy;
  }
}
