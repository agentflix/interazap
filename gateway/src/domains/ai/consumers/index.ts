/**
 * Índice dos consumers do domínio AI.
 *
 * Re-exporta os consumers de Redis Streams para uso no `AIModule`.
 */
export { AiRunRequestConsumer } from './ai-completion.consumer';
export { ClassifierConsumer } from './classifier.consumer';
