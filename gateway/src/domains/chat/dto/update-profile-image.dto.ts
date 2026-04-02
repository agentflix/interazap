import { IsNotEmpty, IsString } from 'class-validator';

/**
 * Payload for updating the profile picture of a Uazapi instance.
 * The image field accepts a URL or base64-encoded string.
 */
export class UpdateProfileImageDto {
  @IsString()
  @IsNotEmpty()
  image!: string;
}
