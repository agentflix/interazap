import { IsIn, IsNotEmpty, IsString } from 'class-validator';

/**
 * Payload for updating the persistent presence status of a Uazapi instance.
 * Controls whether the WhatsApp account appears available or unavailable.
 */
export class UpdatePresenceDto {
  @IsString()
  @IsNotEmpty()
  @IsIn(['available', 'unavailable'])
  presence!: 'available' | 'unavailable';
}
