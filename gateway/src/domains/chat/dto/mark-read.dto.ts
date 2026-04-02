import { IsBoolean, IsNotEmpty, IsString } from 'class-validator';

/**
 * DTO to mark messages as read.
 */
export class MarkReadDto {
  @IsString()
  @IsNotEmpty()
  number!: string;

  @IsBoolean()
  read!: boolean;
}
