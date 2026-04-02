import { Type } from 'class-transformer';
import {
  IsInt,
  IsOptional,
  IsString,
  Min,
  Validate,
  ValidatorConstraint,
  ValidatorConstraintInterface,
} from 'class-validator';

/**
 * Validates that a value is either a non-empty string or a non-empty array
 * of non-empty strings — the accepted shapes for embeddings input.
 */
@ValidatorConstraint({ name: 'isStringOrNonEmptyStringArray', async: false })
class IsStringOrNonEmptyStringArrayConstraint implements ValidatorConstraintInterface {
  /**
   * Returns true when value is a non-blank string or an array of non-blank strings.
   */
  validate(value: string | string[] | undefined): boolean {
    if (typeof value === 'string') {
      return value.trim().length > 0;
    }

    if (Array.isArray(value)) {
      return (
        value.length > 0 &&
        value.every(
          (item) => typeof item === 'string' && item.trim().length > 0,
        )
      );
    }

    return false;
  }

  /**
   * Returns the validation error message shown when the constraint fails.
   */
  defaultMessage(): string {
    return 'input must be a non-empty string or an array of non-empty strings';
  }
}

/**
 * Request payload for AI embeddings generation.
 *
 * Accepts either a single text input or an array of texts, validated by
 * IsStringOrNonEmptyStringArrayConstraint.
 */
export class AIEmbeddingsRequestDto {
  @Validate(IsStringOrNonEmptyStringArrayConstraint)
  input!: string | string[];

  @IsOptional()
  @IsString()
  model?: string;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  dimensions?: number;
}
