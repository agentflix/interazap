import { Global, Module } from '@nestjs/common';
import { RedisService } from './redis.service';
import { RedisStreamsService } from './redis-streams.service';

/**
 * Global NestJS module that manages Redis connections for the gateway.
 *
 * Provides three dedicated Redis clients (command, pub/sub, and blocking) via
 * RedisService, and the RedisStreamsService for stream operations.
 */
@Global()
@Module({
  providers: [RedisService, RedisStreamsService],
  exports: [RedisService, RedisStreamsService],
})
export class RedisModule {}
