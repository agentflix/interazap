import { Global, Module } from '@nestjs/common';
import { DatabaseService } from './database.service';

/**
 * Global NestJS module that manages the PostgreSQL database connection pool.
 *
 * Provides the DatabaseService throughout the application as a singleton,
 * initialized from the DATABASE_URL environment variable.
 */
@Global()
@Module({
  providers: [DatabaseService],
  exports: [DatabaseService],
})
export class DatabaseModule {}
