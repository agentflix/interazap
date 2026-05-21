/**
 * AI Module
 *
 * Módulo principal que orquestra todo o domínio AI.
 * Importa providers e registra consumers.
 */

import { Module } from '@nestjs/common';
import { RedisModule } from '../../infrastructure/redis/redis.module';
import { MetricsModule } from '../../metrics/metrics.module';

// Controllers
import { AIController } from './controllers/ai.controller';

// Providers
import { OpenAIProviderModule } from './providers/openai/openai.module';
import { GeminiProviderModule } from './providers/google/gemini.module';
import { MiniMaxProviderModule } from './providers/minimax/minimax.module';
import { AIProviderFactory } from './providers/ai-provider.factory';
import { InternalAiClientService } from './services/internal-ai-client.service';
import { PromptAssemblerService } from './services/prompt-assembler.service';
import { ContextWindowService } from './services/context-window.service';
import { ToolExecutorService } from './services/tool-executor.service';
import { GuardrailEvaluatorService } from './services/guardrail-evaluator.service';
import { StreamHandlerService } from './services/stream-handler.service';
import { AiRunOrchestratorService } from './services/ai-run-orchestrator.service';
import { AiRunRequestConsumer, ClassifierConsumer } from './consumers/index';
import { AiMetricsService } from './services/ai-metrics.service';
import { AiCancellationRegistry } from './ai-cancellation.registry';
import { AiRunCancelListener } from './listeners/ai-run-cancel.listener';
import { ToolStrategyRegistry } from './services/tool-strategies/tool-strategy.registry';
import { RpcFallbackToolStrategy } from './services/tool-strategies/rpc-fallback.tool-strategy';
import { SendMessageToolStrategy } from './services/tool-strategies/send-message.tool-strategy';
import { DelegateToAgentToolStrategy } from './services/tool-strategies/delegate-to-agent.tool-strategy';
import { ClassifyIntentToolStrategy } from './services/tool-strategies/classify-intent.tool-strategy';
import { SummarizeConversationToolStrategy } from './services/tool-strategies/summarize-conversation.tool-strategy';
import { RequestHumanApprovalToolStrategy } from './services/tool-strategies/request-human-approval.tool-strategy';

const AI_TOOL_DEFAULT_STRATEGY = 'AI_TOOL_DEFAULT_STRATEGY';
const AI_TOOL_STRATEGIES = 'AI_TOOL_STRATEGIES';

@Module({
  imports: [
    RedisModule,
    OpenAIProviderModule,
    GeminiProviderModule,
    MiniMaxProviderModule,
    MetricsModule,
  ],
  controllers: [AIController],
  providers: [
    AIProviderFactory,
    AiMetricsService,
    InternalAiClientService,
    PromptAssemblerService,
    ContextWindowService,
    {
      provide: AI_TOOL_DEFAULT_STRATEGY,
      useFactory: () => new RpcFallbackToolStrategy(),
    },
    {
      provide: AI_TOOL_STRATEGIES,
      useFactory: () => [
        new SendMessageToolStrategy(),
        new DelegateToAgentToolStrategy(),
        new ClassifyIntentToolStrategy(),
        new SummarizeConversationToolStrategy(),
        new RequestHumanApprovalToolStrategy(),
      ],
    },
    {
      provide: ToolStrategyRegistry,
      useFactory: (
        defaultStrategy: RpcFallbackToolStrategy,
        strategies: [
          SendMessageToolStrategy,
          DelegateToAgentToolStrategy,
          ClassifyIntentToolStrategy,
          SummarizeConversationToolStrategy,
          RequestHumanApprovalToolStrategy,
        ],
      ) => new ToolStrategyRegistry(defaultStrategy, strategies),
      inject: [AI_TOOL_DEFAULT_STRATEGY, AI_TOOL_STRATEGIES],
    },
    ToolExecutorService,
    GuardrailEvaluatorService,
    StreamHandlerService,
    AiCancellationRegistry,
    AiRunCancelListener,
    AiRunOrchestratorService,
    AiRunRequestConsumer,
    ClassifierConsumer,
  ],
  exports: [AIProviderFactory],
})
export class AIModule {}
