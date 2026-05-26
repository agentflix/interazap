import { IsBoolean, IsNotEmpty, IsString } from 'class-validator';

/**
 * DTO para marcar mensagens como lidas em uma conversa Uazapi.
 */
export class MarkReadDto {
  @IsString()
  @IsNotEmpty()
  number!: string;

  @IsBoolean()
  read!: boolean;
}
