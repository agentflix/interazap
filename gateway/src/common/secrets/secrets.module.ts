import { Global, Module } from '@nestjs/common';
import { SecretsService } from './secrets.service';

/**
 * Módulo global de gerenciamento de segredos do gateway.
 *
 * Disponibiliza o SecretsService globalmente para resolução de segredos
 * via AWS Secrets Manager, Vault ou variáveis de ambiente.
 */
@Global()
@Module({
  providers: [SecretsService],
  exports: [SecretsService],
})
export class SecretsModule {}
