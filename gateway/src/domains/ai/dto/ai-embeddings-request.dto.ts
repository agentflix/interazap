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
 * Valida que o valor é uma string não vazia ou um array de strings não vazias,
 * conforme os formatos aceitos pelo campo `input` de embeddings.
 */
@ValidatorConstraint({ name: 'isStringOrNonEmptyStringArray', async: false })
class IsStringOrNonEmptyStringArrayConstraint implements ValidatorConstraintInterface {
  /**
   * Retorna `true` quando o valor é uma string não-vazia ou um array de strings não-vazias.
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
   * Retorna a mensagem de erro de validação exibida quando a restrição falha.
   */
  defaultMessage(): string {
    return 'input must be a non-empty string or an array of non-empty strings';
  }
}

/**
 * Payload de requisição para geração de embeddings de AI.
 *
 * Aceita uma única string ou um array de strings como entrada, validado pela
 * restrição `IsStringOrNonEmptyStringArrayConstraint`.
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
