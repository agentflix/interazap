import { IsIn, IsInt, IsOptional, IsString, Max, Min } from 'class-validator';

/**
 * Payload for sending a transient presence update via Uazapi.
 * Represents typing or recording indicators, not persistent presence state.
 */
export class SendPresenceDto {
  @IsString()
  number!: string;

  @IsString()
  @IsIn(['composing', 'recording', 'paused'])
  presence!: string;

  @IsOptional()
  @IsInt()
  @Min(0)
  @Max(300000)
  delay?: number;
}
