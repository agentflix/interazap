import { Global, Module } from '@nestjs/common';
import { StructuredLoggerService } from './structured-logger.service';
import { BusinessEventLogger } from './business-event.logger';

/**
 * Módulo global de logging do gateway.
 *
 * Exporta StructuredLoggerService (JSON logger com AsyncLocalStorage)
 * e BusinessEventLogger para uso em qualquer módulo sem importação explícita.
 */
@Global()
@Module({
  providers: [StructuredLoggerService, BusinessEventLogger],
  exports: [StructuredLoggerService, BusinessEventLogger],
})
export class LoggerModule {}
