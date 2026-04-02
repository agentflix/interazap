import { IsIn, IsNotEmpty, IsString } from 'class-validator';

/**
 * Represents a single message in an AI chat conversation.
 *
 * @remarks
 * Messages are typed by role to allow the AI provider to distinguish between
 * system instructions, user input, and assistant responses.
 */
export class AIChatMessageDto {
  @IsString()
  @IsIn(['system', 'user', 'assistant'])
  role!: 'system' | 'user' | 'assistant';

  @IsString()
  @IsNotEmpty()
  content!: string;
}
