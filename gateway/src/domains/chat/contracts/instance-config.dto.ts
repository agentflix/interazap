/**
 * DTOs de configuracao de instancia para o dominio Chat.
 */
import { IsString, IsOptional, IsEnum, IsObject } from 'class-validator';
import type { ProviderType, ResolvedInstance } from '../models/instance.model';

/**
 * Dados de configuracao de uma instancia de chat.
 */
export class InstanceConfigDto {
  @IsString()
  /** Identificador unico da instancia. */
  id!: string;

  @IsString()
  /** Identificador do tenant proprietario da instancia. */
  tenantId!: string;

  @IsEnum(['uazapi', 'zapi'])
  /** Provedor de mensageria configurado para a instancia. */
  provider!: ProviderType;

  @IsString()
  /** Token de webhook exclusivo da instancia. */
  webhookToken!: string;

  @IsOptional()
  @IsString()
  /** Token de autenticacao no provedor externo quando disponivel. */
  instanceToken?: string;

  @IsOptional()
  @IsString()
  /** Numero de telefone associado a instancia. */
  phone?: string;

  @IsOptional()
  @IsObject()
  /** Configuracoes extras da instancia. */
  settings?: Record<string, unknown>;
}

export type { ProviderType, ResolvedInstance };
