import { IsNotEmpty, IsNumber, IsOptional, IsString } from 'class-validator';

/**
 * DTO para criação de produto (item de cobrança pré-definido) no Asaas.
 *
 * Produtos no Asaas representam itens de cobrança avulsos ou recorrentes
 * que podem ser referenciados na criação de cobranças ou assinaturas.
 */
export class CreateAsaasProductDto {
  @IsString()
  @IsNotEmpty()
  name!: string;

  @IsString()
  @IsNotEmpty()
  description!: string;

  @IsNumber()
  value!: number;

  @IsString()
  @IsNotEmpty()
  externalReference!: string;
}

/**
 * DTO para atualização de produto existente no Asaas.
 *
 * Todos os campos principais são obrigatórios. O campo externalReference
 * é opcional para permitir atualizações sem alterar a referência externa.
 */
export class UpdateAsaasProductDto {
  @IsString()
  @IsNotEmpty()
  name!: string;

  @IsString()
  @IsNotEmpty()
  description!: string;

  @IsNumber()
  value!: number;

  @IsOptional()
  @IsString()
  externalReference?: string;
}
