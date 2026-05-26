import { IsIn, IsNotEmpty, IsString } from 'class-validator';

/**
 * Payload para atualizar o status de presenca global persistente de uma instancia Uazapi.
 * Controla se a conta WhatsApp aparece disponivel ou indisponivel.
 */
export class UpdatePresenceDto {
  @IsString()
  @IsNotEmpty()
  @IsIn(['available', 'unavailable'])
  presence!: 'available' | 'unavailable';
}
