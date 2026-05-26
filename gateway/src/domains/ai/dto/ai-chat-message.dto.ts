import { IsIn, IsNotEmpty, IsString } from 'class-validator';

/**
 * Representa uma única mensagem em uma conversa de chat com AI.
 *
 * As mensagens são tipadas por papel para que o provider de AI diferencie
 * instruções de sistema, entradas do usuário e respostas do assistente.
 */
export class AIChatMessageDto {
  @IsString()
  @IsIn(['system', 'user', 'assistant'])
  role!: 'system' | 'user' | 'assistant';

  @IsString()
  @IsNotEmpty()
  content!: string;
}
