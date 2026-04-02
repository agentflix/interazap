import { type AfSelectOption } from '@shared/components';

/**
 * Opções de modelos de IA disponíveis para agentes.
 * Fonte única de verdade — usado em criação e edição de agentes.
 */
export const AI_MODEL_OPTIONS: AfSelectOption[] = [
  // OpenAI
  { value: 'gpt-4o', label: 'GPT-4o (Avançado)' },
  { value: 'gpt-4o-mini', label: 'GPT-4o Mini (Rápido)' },

  // Google Gemini
  { value: 'gemini-2.5-pro', label: 'Gemini 2.5 Pro (Avançado)' },
  { value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash (Rápido)' },
  { value: 'gemini-3.1-pro-preview', label: 'Gemini 3.1 Pro (Avançado)' },
  { value: 'gemini-3.1-flash-lite-preview', label: 'Gemini 3.1 Flash Lite (Rápido)' },

  // MiniMax
  { value: 'MiniMax-M2.5', label: 'MiniMax M2.5 (Avançado)' },
  { value: 'MiniMax-M2.5-highspeed', label: 'MiniMax M2.5 Highspeed (Rápido)' },
];
