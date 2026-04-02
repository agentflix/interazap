import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { GuardrailEvaluationResult } from '../models/guardrail.model';

/**
 * Serviço responsável por avaliar guardrails simples sobre tool calls e saídas finais.
 */
@Injectable()
export class GuardrailEvaluatorService {
  private readonly forbiddenPatterns: RegExp[];

  constructor(private readonly configService: ConfigService) {
    const patterns = (
      this.configService.get<string>('AI_GUARDRAIL_PATTERNS') ??
      'api_key,password,token,secret'
    ).split(',');
    this.forbiddenPatterns = patterns.map(
      (p) => new RegExp(`\\b${p.trim()}\\b\\s*[:=]`, 'i'),
    );
  }

  /**
   * Avalia se uma chamada de tool contém padrões bloqueados pela configuração do gateway.
   * @param toolName - Nome da tool a ser executada.
   * @param args - Argumentos informados para a tool.
   * @returns Resultado da avaliação com indicação de permissão e motivo opcional.
   */
  evaluateToolCall(
    toolName: string,
    args: Record<string, unknown>,
  ): GuardrailEvaluationResult {
    const serialized = JSON.stringify({ toolName, args });
    for (const pattern of this.forbiddenPatterns) {
      if (pattern.test(serialized)) {
        return {
          allowed: false,
          reason: `blocked_pattern:${pattern.source}`,
        };
      }
    }

    return { allowed: true };
  }

  /**
   * Avalia se a saída final do modelo contém padrões bloqueados pelo guardrail.
   * @param output - Texto final produzido pelo modelo.
   * @returns Resultado da avaliação com indicação de permissão e motivo opcional.
   */
  evaluateFinalOutput(output: string): GuardrailEvaluationResult {
    for (const pattern of this.forbiddenPatterns) {
      if (pattern.test(output)) {
        return {
          allowed: false,
          reason: `blocked_output:${pattern.source}`,
        };
      }
    }

    return { allowed: true };
  }
}
